<?php

namespace MiniShop3\Controllers\Auth;

use MiniShop3\Model\msCustomer;
use MODX\Revolution\modX;

/**
 * PasswordAuthProvider - authentication provider for email and password
 *
 * Most common authentication method.
 * Uses bcrypt for password verification (password_verify).
 *
 * Usage example:
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
     * Normalize email for lookup and storage.
     */
    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * Authenticate by email and password
     *
     * @param array $credentials Must contain 'email' and 'password'
     * @return msCustomer|null
     */
    public function authenticate(array $credentials): ?msCustomer
    {
        $email = self::normalizeEmail($credentials['email'] ?? '');
        $password = $credentials['password'] ?? '';

        if (empty($email) || empty($password)) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[PasswordAuthProvider] Empty email or password"
            );
            return null;
        }

        // Find customer by email
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

        // Verify password
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

        // Check if password hash needs to be updated (if bcrypt settings changed)
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
     * Get provider name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'password';
    }

    /**
     * Check credentials support
     *
     * Provider supports credentials if 'email' and 'password' are present
     *
     * @param array $credentials
     * @return bool
     */
    public function supports(array $credentials): bool
    {
        return isset($credentials['email']) && isset($credentials['password']);
    }

    /**
     * Hash password (helper method for registration)
     *
     * @param string $password Plain password
     * @return string Hashed password
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}
