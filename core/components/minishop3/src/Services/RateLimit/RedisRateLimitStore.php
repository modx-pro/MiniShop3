<?php

namespace MiniShop3\Services\RateLimit;

/**
 * Redis-backed rate limit store for multi-node deployments (requires ext-redis).
 *
 * Uses a single key per rate-limit bucket with a TTL window. Increment and TTL
 * (re)arm happen atomically via a Lua script, so there is no TOCTOU window
 * between INCR and EXPIRE and no separate "reset" key that can drift out of
 * sync with the counter.
 */
class RedisRateLimitStore implements RateLimitStoreInterface
{
    private const KEY_PREFIX = 'ms3:rate_limit:';

    /**
     * Atomically INCR the bucket key and arm its TTL on the first hit.
     *
     * Returns `{attempts, ttl}` where ttl is seconds remaining until expiry.
     */
    private const INCREMENT_SCRIPT = <<<'LUA'
local attempts = redis.call('INCR', KEYS[1])
if attempts == 1 then
  redis.call('EXPIRE', KEYS[1], ARGV[1])
end
local ttl = redis.call('TTL', KEYS[1])
if ttl < 0 then
  ttl = tonumber(ARGV[1])
  redis.call('EXPIRE', KEYS[1], ARGV[1])
end
return {attempts, ttl}
LUA;

    public function __construct(
        private \Redis $redis,
        private int $defaultWindowSeconds = 60,
    ) {
    }

    public function read(string $key): array
    {
        $bucketKey = $this->bucketKey($key);
        $attempts = (int) $this->redis->get($bucketKey);
        $ttl = (int) $this->redis->ttl($bucketKey);

        if ($attempts <= 0) {
            return [
                'attempts' => 0,
                'reset_at' => time() + $this->defaultWindowSeconds,
            ];
        }

        if ($ttl < 0) {
            $ttl = $this->defaultWindowSeconds;
        }

        return [
            'attempts' => $attempts,
            'reset_at' => time() + $ttl,
        ];
    }

    public function increment(string $key, int $windowSeconds): array
    {
        $result = $this->redis->rawCommand(
            'EVAL',
            self::INCREMENT_SCRIPT,
            1,
            $this->bucketKey($key),
            $windowSeconds
        );

        if (!is_array($result)) {
            return [
                'attempts' => 1,
                'reset_at' => time() + $windowSeconds,
            ];
        }

        $attempts = (int) $result[0];
        $ttl = (int) $result[1];
        if ($ttl < 0) {
            $ttl = $windowSeconds;
        }

        return [
            'attempts' => $attempts,
            'reset_at' => time() + $ttl,
        ];
    }

    public function reset(string $key): void
    {
        $this->redis->del($this->bucketKey($key));
    }

    private function bucketKey(string $key): string
    {
        return self::KEY_PREFIX . hash('sha256', $key);
    }
}
