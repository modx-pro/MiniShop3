<?php

namespace MiniShop3\Controllers\Auth;

use MiniShop3\Model\msCustomer;
use MODX\Revolution\modX;

/**
 * PasswordAuthProvider - провайдер аутентификации по email и паролю
 *
 * Самый распространенный метод аутентификации.
 * Использует bcrypt для проверки паролей (password_verify).
 *
 * Пример использования:
 * ```php
 * $provider = new PasswordAuthProvider($modx);
 *
 * $customer = $provider->authenticate([
 *     'email' => 'user@example.com',
 *     'password' => 'secret123'
 * ]);
 *
 * if ($customer) {
 *     echo "Welcome, {$customer->get('first_name')}!";
 * }
 * ```
 *
 * @package MiniShop3\Controllers\Auth
 */
class PasswordAuthProvider implements AuthProviderInterface
{
    /** @var modX */
    protected modX $modx;

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Аутентификация по email и паролю
     *
     * @param array $credentials Должен содержать 'email' и 'password'
     * @return msCustomer|null
     */
    public function authenticate(array $credentials): ?msCustomer
    {
        $email = trim($credentials['email'] ?? '');
        $password = $credentials['password'] ?? '';

        if (empty($email) || empty($password)) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[PasswordAuthProvider] Empty email or password"
            );
            return null;
        }

        // Поиск клиента по email
        /** @var msCustomer $customer */
        $customer = $this->modx->getObject(msCustomer::class, [
            'email' => $email,
        ]);

        if (!$customer) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[PasswordAuthProvider] Customer not found: {$email}"
            );
            return null;
        }

        // Проверка пароля
        $hashedPassword = $customer->get('password');

        if (empty($hashedPassword)) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                "[PasswordAuthProvider] Customer #{$customer->id} has no password set"
            );
            return null;
        }

        if (!password_verify($password, $hashedPassword)) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[PasswordAuthProvider] Invalid password for customer #{$customer->id}"
            );
            return null;
        }

        // Проверка, нужно ли обновить хеш пароля (если изменились настройки bcrypt)
        if (password_needs_rehash($hashedPassword, PASSWORD_BCRYPT)) {
            $newHash = password_hash($password, PASSWORD_BCRYPT);
            $customer->set('password', $newHash);
            $customer->save();

            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                "[PasswordAuthProvider] Password rehashed for customer #{$customer->id}"
            );
        }

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[PasswordAuthProvider] Customer #{$customer->id} authenticated successfully"
        );

        return $customer;
    }

    /**
     * Получить имя провайдера
     *
     * @return string
     */
    public function getName(): string
    {
        return 'password';
    }

    /**
     * Проверить поддержку credentials
     *
     * Провайдер поддерживает credentials, если есть 'email' и 'password'
     *
     * @param array $credentials
     * @return bool
     */
    public function supports(array $credentials): bool
    {
        return isset($credentials['email']) && isset($credentials['password']);
    }

    /**
     * Хеширование пароля (вспомогательный метод для регистрации)
     *
     * @param string $password Открытый пароль
     * @return string Хешированный пароль
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}
