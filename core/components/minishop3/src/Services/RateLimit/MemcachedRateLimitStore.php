<?php

namespace MiniShop3\Services\RateLimit;

/**
 * Memcached-backed rate limit store for multi-node deployments (requires ext-memcached).
 */
class MemcachedRateLimitStore implements RateLimitStoreInterface
{
    private const KEY_PREFIX = 'ms3_rate_limit_';

    public function __construct(
        private \Memcached $memcached,
        private int $defaultWindowSeconds = 60,
    ) {
    }

    public function read(string $key): array
    {
        $attempts = (int) $this->memcached->get($this->attemptsKey($key));
        $resetAt = (int) $this->memcached->get($this->resetKey($key));

        if ($resetAt <= 0) {
            $resetAt = time() + $this->defaultWindowSeconds;
        }

        return [
            'attempts' => $attempts,
            'reset_at' => $resetAt,
        ];
    }

    public function increment(string $key, int $windowSeconds): array
    {
        $attemptsKey = $this->attemptsKey($key);
        $resetKey = $this->resetKey($key);

        $attempts = $this->memcached->increment($attemptsKey, 1);
        if ($attempts === false) {
            $this->memcached->set($attemptsKey, 1, $windowSeconds);
            $attempts = 1;
        }

        $resetAt = (int) $this->memcached->get($resetKey);
        if ($attempts === 1 || $resetAt <= 0) {
            $resetAt = time() + $windowSeconds;
            $this->memcached->set($resetKey, $resetAt, $windowSeconds);
        }

        return [
            'attempts' => $attempts,
            'reset_at' => $resetAt,
        ];
    }

    public function reset(string $key): void
    {
        $this->memcached->delete($this->attemptsKey($key));
        $this->memcached->delete($this->resetKey($key));
    }

    private function attemptsKey(string $key): string
    {
        return self::KEY_PREFIX . hash('sha256', $key) . '_attempts';
    }

    private function resetKey(string $key): string
    {
        return self::KEY_PREFIX . hash('sha256', $key) . '_reset';
    }
}
