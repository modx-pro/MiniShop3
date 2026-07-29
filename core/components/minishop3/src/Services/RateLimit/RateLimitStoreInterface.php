<?php

namespace MiniShop3\Services\RateLimit;

/**
 * Pluggable storage for HTTP rate limit counters (single-node file, shared Redis, etc.).
 */
interface RateLimitStoreInterface
{
    /**
     * Read current counter state without modifying it.
     *
     * @return array{attempts: int, reset_at: int} reset_at is Unix timestamp
     */
    public function read(string $key): array;

    /**
     * Increment counter and ensure the window TTL is set on first hit.
     *
     * @return array{attempts: int, reset_at: int}
     */
    public function increment(string $key, int $windowSeconds): array;

    /**
     * Clear counter state for the key.
     */
    public function reset(string $key): void;
}
