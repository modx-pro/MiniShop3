<?php

namespace MiniShop3\Services\Customer;

use MODX\Revolution\modX;

/**
 * RateLimiter - сервис ограничения частоты запросов
 *
 * Защита от брутфорса, DDoS и злоупотреблений.
 * Использует кеш MODX для хранения счетчиков попыток.
 *
 * Примеры использования:
 * ```php
 * $limiter = $modx->services->get('ms3_rate_limiter');
 *
 * // Проверка перед входом
 * if (!$limiter->check('login', $_SERVER['REMOTE_ADDR'], 5, 300)) {
 *     die('Too many login attempts. Try again in 5 minutes.');
 * }
 *
 * // Проверка отправки email
 * if (!$limiter->check('email_send', $customerEmail, 3, 3600)) {
 *     die('Too many emails sent. Try again in 1 hour.');
 * }
 *
 * // Сброс счетчика при успешном входе
 * $limiter->reset('login', $_SERVER['REMOTE_ADDR']);
 * ```
 *
 * @package MiniShop3\Services\Customer
 */
class RateLimiter
{
    /** @var modX */
    protected modX $modx;

    /** @var string Префикс ключей кеша */
    protected string $prefix = 'ms3_rate_limit_';

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Проверить лимит запросов и увеличить счетчик
     *
     * @param string $action Тип действия (login, register, email_send и т.д.)
     * @param string $identifier Идентификатор (IP, email, customer_id и т.д.)
     * @param int $maxAttempts Максимальное количество попыток
     * @param int $windowSeconds Временное окно в секундах
     * @return bool true, если лимит не превышен
     */
    public function check(string $action, string $identifier, int $maxAttempts, int $windowSeconds): bool
    {
        $key = $this->getKey($action, $identifier);

        // Получаем текущий счетчик
        $attempts = (int)$this->modx->cacheManager->get($key);

        // Проверка лимита
        if ($attempts >= $maxAttempts) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                "[RateLimiter] Limit exceeded: {$action} for {$identifier} ({$attempts}/{$maxAttempts})"
            );
            return false;
        }

        // Увеличиваем счетчик
        $i = $attempts + 1;
        $this->modx->cacheManager->set($key, $i, $windowSeconds);

        return true;
    }

    /**
     * Получить текущее количество попыток
     *
     * @param string $action Тип действия
     * @param string $identifier Идентификатор
     * @return int Количество попыток
     */
    public function getAttempts(string $action, string $identifier): int
    {
        $key = $this->getKey($action, $identifier);
        return (int)$this->modx->cacheManager->get($key);
    }

    /**
     * Проверить, заблокирован ли идентификатор
     *
     * @param string $action Тип действия
     * @param string $identifier Идентификатор
     * @param int $maxAttempts Максимальное количество попыток
     * @return bool true, если заблокирован
     */
    public function isBlocked(string $action, string $identifier, int $maxAttempts): bool
    {
        return $this->getAttempts($action, $identifier) >= $maxAttempts;
    }

    /**
     * Сбросить счетчик попыток
     *
     * Используется после успешной операции (например, успешного входа)
     *
     * @param string $action Тип действия
     * @param string $identifier Идентификатор
     * @return void
     */
    public function reset(string $action, string $identifier): void
    {
        $key = $this->getKey($action, $identifier);
        $this->modx->cacheManager->delete($key);

        $this->modx->log(
            modX::LOG_LEVEL_DEBUG,
            "[RateLimiter] Reset counter: {$action} for {$identifier}"
        );
    }

    /**
     * Получить время до разблокировки (в секундах)
     *
     * @param string $action Тип действия
     * @param string $identifier Идентификатор
     * @return int|null Секунды до разблокировки, null если не заблокирован
     */
    public function getTimeUntilUnblock(string $action, string $identifier): ?int
    {
        $key = $this->getKey($action, $identifier);

        // Проверяем TTL кеша
        $cacheOptions = [];
        $value = $this->modx->cacheManager->get($key, $cacheOptions);

        if ($value === null) {
            return null;
        }

        // Если кеш еще есть, значит блокировка активна
        // Но у modX cacheManager нет метода getTTL, поэтому возвращаем приблизительное значение
        // TODO: Улучшить определение TTL (можно хранить timestamp вместе со счетчиком)
        return null;
    }

    /**
     * Проверка с автоматическим увеличением счетчика при превышении лимита
     *
     * Удобный метод для защиты от брутфорса
     *
     * @param string $action Тип действия
     * @param string $identifier Идентификатор
     * @param int $maxAttempts Максимальное количество попыток
     * @param int $windowSeconds Временное окно в секундах
     * @param bool $increment Увеличивать счетчик даже при превышении лимита
     * @return bool true, если лимит не превышен
     */
    public function attempt(string $action, string $identifier, int $maxAttempts, int $windowSeconds, bool $increment = true): bool
    {
        $key = $this->getKey($action, $identifier);
        $attempts = (int)$this->modx->cacheManager->get($key);

        if ($attempts >= $maxAttempts) {
            if ($increment) {
                // Продлеваем блокировку при каждой новой попытке
                $i = $attempts + 1;
                $this->modx->cacheManager->set($key, $i, $windowSeconds);
            }

            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                "[RateLimiter] Attempt blocked: {$action} for {$identifier} ({$attempts}/{$maxAttempts})"
            );

            return false;
        }

        return true;
    }

    /**
     * Генерация ключа кеша
     *
     * @param string $action Тип действия
     * @param string $identifier Идентификатор
     * @return string
     */
    protected function getKey(string $action, string $identifier): string
    {
        // Используем md5 для identifier, чтобы избежать проблем с спецсимволами в ключах кеша
        return $this->prefix . $action . '_' . md5($identifier);
    }

    /**
     * Очистить все счетчики определенного действия
     *
     * @param string $action Тип действия
     * @return void
     */
    public function clearAction(string $action): void
    {
        // MODX cacheManager не поддерживает wildcard удаление
        // Этот метод оставлен для будущей реализации
        $this->modx->log(
            modX::LOG_LEVEL_DEBUG,
            "[RateLimiter] Clear action not fully implemented: {$action}"
        );
    }
}
