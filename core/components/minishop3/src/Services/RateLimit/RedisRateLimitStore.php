<?php

namespace MiniShop3\Services\RateLimit;

/**
 * Redis-backed rate limit store for multi-node deployments (requires ext-redis).
 */
class RedisRateLimitStore implements RateLimitStoreInterface
{
    private const KEY_PREFIX = 'ms3:rate_limit:';

    public function __construct(
        private \Redis $redis,
        private int $defaultWindowSeconds = 60,
    ) {
    }

    public function read(string $key): array
    {
        $attempts = (int) $this->redis->get($this->attemptsKey($key));
        $resetAt = (int) $this->redis->get($this->resetKey($key));

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

        $attempts = (int) $this->redis->incr($attemptsKey);
        $resetAt = (int) $this->redis->get($resetKey);

        if ($attempts === 1 || $resetAt <= 0) {
            $resetAt = time() + $windowSeconds;
            $this->redis->setex($resetKey, $windowSeconds, (string) $resetAt);
            $this->redis->expire($attemptsKey, $windowSeconds);
        }

        return [
            'attempts' => $attempts,
            'reset_at' => $resetAt,
        ];
    }

    public function reset(string $key): void
    {
        $this->redis->del($this->attemptsKey($key), $this->resetKey($key));
    }

    private function attemptsKey(string $key): string
    {
        return self::KEY_PREFIX . hash('sha256', $key) . ':attempts';
    }

    private function resetKey(string $key): string
    {
        return self::KEY_PREFIX . hash('sha256', $key) . ':reset';
    }
}
