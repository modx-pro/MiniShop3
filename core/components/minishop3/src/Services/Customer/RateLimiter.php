<?php

namespace MiniShop3\Services\Customer;

use MODX\Revolution\modX;

/**
 * RateLimiter - request rate limiting service
 *
 * Protection against bruteforce, DDoS and abuse.
 * Uses MODX cache to store attempt counters.
 *
 * Usage examples:
 * ```php
 * $limiter = $modx->services->get('ms3_rate_limiter');
 *
 * // Check before login
 * if (!$limiter->check('login', $_SERVER['REMOTE_ADDR'], 5, 300)) {
 *     die('Too many login attempts. Try again in 5 minutes.');
 * }
 *
 * // Check email sending
 * if (!$limiter->check('email_send', $customerEmail, 3, 3600)) {
 *     die('Too many emails sent. Try again in 1 hour.');
 * }
 *
 * // Reset counter on successful login
 * $limiter->reset('login', $_SERVER['REMOTE_ADDR']);
 * ```
 *
 * @package MiniShop3\Services\Customer
 */
class RateLimiter
{
    /** @var modX */
    protected modX $modx;

    /** @var string Cache key prefix */
    protected string $prefix = 'ms3_rate_limit_';

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Check request limit and increment counter
     *
     * @param string $action Action type (login, register, email_send, etc.)
     * @param string $identifier Identifier (IP, email, customer_id, etc.)
     * @param int $maxAttempts Maximum number of attempts
     * @param int $windowSeconds Time window in seconds
     * @return bool true if limit not exceeded
     */
    public function check(string $action, string $identifier, int $maxAttempts, int $windowSeconds): bool
    {
        $key = $this->getKey($action, $identifier);

        $attempts = (int)$this->modx->cacheManager->get($key);

        if ($attempts >= $maxAttempts) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                "[RateLimiter] Limit exceeded: {$action} for {$identifier} ({$attempts}/{$maxAttempts})"
            );
            return false;
        }

        $i = $attempts + 1;
        $this->modx->cacheManager->set($key, $i, $windowSeconds);

        return true;
    }

    /**
     * Get current number of attempts
     *
     * @param string $action Action type
     * @param string $identifier Identifier
     * @return int Number of attempts
     */
    public function getAttempts(string $action, string $identifier): int
    {
        $key = $this->getKey($action, $identifier);
        return (int)$this->modx->cacheManager->get($key);
    }

    /**
     * Check if identifier is blocked
     *
     * @param string $action Action type
     * @param string $identifier Identifier
     * @param int $maxAttempts Maximum number of attempts
     * @return bool true if blocked
     */
    public function isBlocked(string $action, string $identifier, int $maxAttempts): bool
    {
        return $this->getAttempts($action, $identifier) >= $maxAttempts;
    }

    /**
     * Reset attempt counter
     *
     * Used after successful operation (e.g., successful login)
     *
     * @param string $action Action type
     * @param string $identifier Identifier
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
     * Get time until unblock (in seconds)
     *
     * @param string $action Action type
     * @param string $identifier Identifier
     * @return int|null Seconds until unblock, null if not blocked
     */
    public function getTimeUntilUnblock(string $action, string $identifier): ?int
    {
        $key = $this->getKey($action, $identifier);

        $cacheOptions = [];
        $value = $this->modx->cacheManager->get($key, $cacheOptions);

        if ($value === null) {
            return null;
        }

        return null;
    }

    /**
     * Check with automatic counter increment on limit exceeded
     *
     * Convenient method for bruteforce protection
     *
     * @param string $action Action type
     * @param string $identifier Identifier
     * @param int $maxAttempts Maximum number of attempts
     * @param int $windowSeconds Time window in seconds
     * @param bool $increment Increment counter even on limit exceeded
     * @return bool true if limit not exceeded
     */
    public function attempt(string $action, string $identifier, int $maxAttempts, int $windowSeconds, bool $increment = true): bool
    {
        $key = $this->getKey($action, $identifier);
        $attempts = (int)$this->modx->cacheManager->get($key);

        if ($attempts >= $maxAttempts) {
            if ($increment) {
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
     * Generate cache key
     *
     * @param string $action Action type
     * @param string $identifier Identifier
     * @return string
     */
    protected function getKey(string $action, string $identifier): string
    {
        return $this->prefix . $action . '_' . md5($identifier);
    }

    /**
     * Clear all counters for specific action
     *
     * @param string $action Action type
     * @return void
     */
    public function clearAction(string $action): void
    {
        $this->modx->log(
            modX::LOG_LEVEL_DEBUG,
            "[RateLimiter] Clear action not fully implemented: {$action}"
        );
    }
}
