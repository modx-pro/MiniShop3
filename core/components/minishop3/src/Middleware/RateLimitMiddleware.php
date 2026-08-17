<?php

namespace MiniShop3\Middleware;

use MiniShop3\Router\Middleware\MiddlewareInterface;
use MiniShop3\Router\ApiErrorCode;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\RateLimit\FileRateLimitStore;
use MiniShop3\Services\RateLimit\RateLimitStoreInterface;

/**
 * Middleware for request rate limiting
 *
 * Protection against DDoS attacks and API abuse.
 * Storage backend is pluggable (file by default, optional Redis/Memcached).
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /** @var int Maximum number of requests */
    private int $maxAttempts;

    /** @var int Time period in seconds */
    private int $decaySeconds;

    private RateLimitStoreInterface $store;

    /**
     * @param int $maxAttempts Maximum number of requests (default 60)
     * @param int $decaySeconds Time period in seconds (default 60 - 1 minute)
     * @param RateLimitStoreInterface|null $store Storage backend (default: file in sys_get_temp_dir())
     */
    public function __construct(
        int $maxAttempts = 60,
        int $decaySeconds = 60,
        ?RateLimitStoreInterface $store = null,
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
        $this->store = $store ?? new FileRateLimitStore(sys_get_temp_dir(), $decaySeconds);
    }

    /**
     * Handle request
     *
     * @param array $params URL parameters from router
     * @return Response|null Return Response to stop execution, or null to continue
     */
    public function handle(array $params)
    {
        $key = $this->resolveRequestKey();

        $state = $this->store->read($key);

        if (time() >= $state['reset_at']) {
            $this->store->reset($key);
        }

        $state = $this->store->increment($key, $this->decaySeconds);

        if ($state['attempts'] > $this->maxAttempts) {
            $retryAfter = max(0, $state['reset_at'] - time());
            header("Retry-After: $retryAfter");

            return Response::errorWithCode(
                ApiErrorCode::RATE_LIMITED,
                'ms3_err_rate_limit',
                HttpStatus::TOO_MANY_REQUESTS
            );
        }

        $this->setRateLimitHeaders($state['attempts'], $state['reset_at']);

        return null;
    }

    /**
     * Client bucket key: IP only (#576).
     * Do not include client-controlled headers (e.g. MS3TOKEN) — that bypasses the limit.
     */
    private function resolveRequestKey(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        return 'rate_limit:' . md5($ip);
    }

    /**
     * Set rate limit headers
     */
    private function setRateLimitHeaders(int $attempts, int $resetTime): void
    {
        header('X-RateLimit-Limit: ' . $this->maxAttempts);
        header('X-RateLimit-Remaining: ' . max(0, $this->maxAttempts - $attempts));
        header('X-RateLimit-Reset: ' . $resetTime);
    }
}
