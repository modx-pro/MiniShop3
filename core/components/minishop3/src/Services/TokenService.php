<?php

namespace MiniShop3\Services;

use MODX\Revolution\modX;

/**
 * Сервис для безопасной работы с токенами
 *
 * Обеспечивает:
 * - Криптографически стойкую генерацию токенов
 * - TTL (Time-To-Live) для токенов
 * - Централизованное управление секретами
 * - Валидацию и проверку токенов
 */
class TokenService
{
    /** @var modX */
    protected modX $modx;

    /** @var string Тип токена: customer или snippet */
    const TYPE_CUSTOMER = 'customer';
    const TYPE_SNIPPET = 'snippet';

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Генерация криптографически стойкого токена для покупателя
     *
     * @param int|null $ttl TTL в секундах (null = из системных настроек)
     * @return array ['token' => string, 'expires' => int]
     */
    public function generateCustomerToken(?int $ttl = null): array
    {
        // Проверяем, авторизован ли клиент
        if (empty($_SESSION['ms3']['customer_id'])) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[TokenService] Cannot generate token: customer_id not found in session"
            );
            return ['token' => '', 'expires' => 0, 'lifetime' => 0];
        }

        $customerId = (int)$_SESSION['ms3']['customer_id'];

        // Генерируем токен через random_bytes (криптографически стойкий)
        $token = bin2hex(random_bytes(32)); // 64 символа

        // Определяем TTL (по умолчанию 24 часа)
        if ($ttl === null) {
            $ttl = (int)$this->modx->getOption('ms3_customer_token_ttl', null, 86400);
        }

        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        // Создаём запись токена в БД
        $tokenObj = $this->modx->newObject(\MiniShop3\Model\msCustomerToken::class);
        $tokenObj->set('customer_id', $customerId);
        $tokenObj->set('token', $token);
        $tokenObj->set('type', \MiniShop3\Model\msCustomerToken::TYPE_API);
        $tokenObj->set('expires_at', $expiresAt);
        $tokenObj->set('created_at', date('Y-m-d H:i:s'));

        if (!$tokenObj->save()) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[TokenService] Failed to save token to database"
            );
            return ['token' => '', 'expires' => 0, 'lifetime' => 0];
        }

        // Сохраняем в сессию для обратной совместимости
        $_SESSION['ms3']['customer_token'] = $token;
        $_SESSION['ms3']['customer_token_expires'] = time() + $ttl;

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[TokenService] Generated customer token for customer_id={$customerId}, expires: " . $expiresAt
        );

        return [
            'token' => $token,
            'expires' => time() + $ttl,
            'lifetime' => $ttl * 1000, // В миллисекундах для JS
        ];
    }

    /**
     * Обновление существующего токена покупателя (продление TTL)
     *
     * @param string $token Существующий токен
     * @param int|null $ttl TTL в секундах
     * @return array ['token' => string, 'expires' => int]
     */
    public function updateCustomerToken(string $token, ?int $ttl = null): array
    {
        if (empty($token)) {
            return $this->generateCustomerToken($ttl);
        }

        // Определяем TTL
        if ($ttl === null) {
            $ttl = (int)$this->modx->getOption('ms3_customer_token_ttl', null, 86400);
        }

        $expires = time() + $ttl;

        // Обновляем в сессии
        $_SESSION['ms3']['customer_token'] = $token;
        $_SESSION['ms3']['customer_token_expires'] = $expires;

        $this->modx->log(
            modX::LOG_LEVEL_DEBUG,
            "[TokenService] Updated customer token, expires: " . date('Y-m-d H:i:s', $expires)
        );

        return [
            'token' => $token,
            'expires' => $expires,
            'lifetime' => $ttl * 1000, // В миллисекундах для JS
        ];
    }

    /**
     * Получить токен покупателя из сессии (с проверкой валидности)
     *
     * @return string|null Токен или null если истёк/не существует
     */
    public function getCustomerToken(): ?string
    {
        $token = $_SESSION['ms3']['customer_token'] ?? null;
        $expires = $_SESSION['ms3']['customer_token_expires'] ?? null;

        // Токен не существует
        if (empty($token)) {
            return null;
        }

        // Проверяем TTL
        if ($expires !== null && $expires < time()) {
            // Токен истёк
            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                "[TokenService] Customer token expired, clearing session"
            );

            unset($_SESSION['ms3']['customer_token']);
            unset($_SESSION['ms3']['customer_token_expires']);

            return null;
        }

        return $token;
    }

    /**
     * Валидация токена покупателя
     *
     * @param string $token Токен для проверки
     * @return bool True если токен валиден
     */
    public function validateCustomerToken(string $token): bool
    {
        $sessionToken = $this->getCustomerToken();

        if ($sessionToken === null) {
            return false;
        }

        // Сравнение токенов (защита от timing attacks)
        return hash_equals($sessionToken, $token);
    }

    /**
     * Генерация токена для сниппета (кеширование параметров)
     *
     * @param array $scriptProperties Параметры сниппета
     * @return string Токен для кеша
     */
    public function generateSnippetToken(array $scriptProperties): string
    {
        // Получаем секрет из системных настроек (или генерируем)
        $secret = $this->getSnippetSecret();

        // Генерируем токен на основе параметров и секрета
        $token = 'ms3_' . hash('sha256', json_encode($scriptProperties) . $secret);

        return $token;
    }

    /**
     * Получить секрет для snippet токенов (или сгенерировать новый)
     *
     * @return string Секрет
     */
    protected function getSnippetSecret(): string
    {
        $secret = $this->modx->getOption('ms3_snippet_token_secret', null, null);

        // Если секрета нет - генерируем и сохраняем
        if (empty($secret)) {
            $secret = bin2hex(random_bytes(32)); // 64 символа

            // Сохраняем в системные настройки
            $setting = $this->modx->getObject('modSystemSetting', ['key' => 'ms3_snippet_token_secret']);

            if (!$setting) {
                $setting = $this->modx->newObject('modSystemSetting');
                $setting->set('key', 'ms3_snippet_token_secret');
                $setting->set('namespace', 'minishop3');
                $setting->set('area', 'ms3_main');
                $setting->set('xtype', 'textfield');
            }

            $setting->set('value', $secret);
            $setting->save();

            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                "[TokenService] Generated new snippet token secret"
            );

            // Очищаем кеш системных настроек
            $this->modx->cacheManager->refresh([
                'system_settings' => [],
            ]);
        }

        return $secret;
    }

    /**
     * Сохранить данные сниппета в кеш
     *
     * @param string $token Токен сниппета
     * @param array $data Данные для кеширования
     * @param int|null $ttl TTL в секундах (null = из системных настроек)
     * @return bool Успешность операции
     */
    public function cacheSnippetData(string $token, array $data, ?int $ttl = null): bool
    {
        if ($ttl === null) {
            // TTL по умолчанию: 1 час
            $ttl = (int)$this->modx->getOption('ms3_snippet_cache_ttl', null, 3600);
        }

        $options = [
            \xPDO\xPDO::OPT_CACHE_KEY => 'minishop3/snippets',
        ];

        $result = $this->modx->cacheManager->set($token, $data, $ttl, $options);

        if ($result) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[TokenService] Cached snippet data, token: {$token}, ttl: {$ttl}s"
            );
        }

        return $result;
    }

    /**
     * Получить данные сниппета из кеша
     *
     * @param string $token Токен сниппета
     * @return array|null Данные или null если не найдены
     */
    public function getSnippetData(string $token): ?array
    {
        $options = [
            \xPDO\xPDO::OPT_CACHE_KEY => 'minishop3/snippets',
        ];

        $data = $this->modx->cacheManager->get($token, $options);

        return $data ?: null;
    }

    /**
     * Очистить токен покупателя из сессии
     *
     * @return void
     */
    public function clearCustomerToken(): void
    {
        unset($_SESSION['ms3']['customer_token']);
        unset($_SESSION['ms3']['customer_token_expires']);

        $this->modx->log(
            modX::LOG_LEVEL_DEBUG,
            "[TokenService] Cleared customer token from session"
        );
    }

    /**
     * Очистить кеш сниппетов
     *
     * @param string|null $token Конкретный токен или null для очистки всех
     * @return bool
     */
    public function clearSnippetCache(?string $token = null): bool
    {
        $options = [
            \xPDO\xPDO::OPT_CACHE_KEY => 'minishop3/snippets',
        ];

        if ($token !== null) {
            // Удаляем конкретный токен
            return $this->modx->cacheManager->delete($token, $options);
        } else {
            // Очищаем весь кеш сниппетов
            return $this->modx->cacheManager->clean($options);
        }
    }
}
