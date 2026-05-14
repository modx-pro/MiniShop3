<?php

namespace MiniShop3\Middleware;

use MiniShop3\Router\Middleware\MiddlewareInterface;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;

/**
 * Middleware for request rate limiting
 *
 * Protection against DDoS attacks and API abuse.
 * Uses simple file-based cache mechanism.
 *
 * TODO: In production it's recommended to use Redis/Memcached for rate limiting
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /** @var int Maximum number of requests */
    private int $maxAttempts;

    /** @var int Time period in seconds */
    private int $decaySeconds;

    /** @var string Path to directory for storing rate limit data */
    private string $storagePath;

    /**
     * @param int $maxAttempts Maximum number of requests (default 60)
     * @param int $decaySeconds Time period in seconds (default 60 - 1 minute)
     * @param string $storagePath Path to storage directory (default sys_get_temp_dir())
     */
    public function __construct(
        int $maxAttempts = 60,
        int $decaySeconds = 60,
        string $storagePath = ''
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
        $this->storagePath = !empty($storagePath) ? $storagePath : sys_get_temp_dir();
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

        $attempts = $this->getAttempts($key);
        $resetTime = $this->getResetTime($key);

        // If time expired, reset counter
        if (time() >= $resetTime) {
            $this->resetAttempts($key);
            $attempts = 0;
        }

        // Check limit
        if ($attempts >= $this->maxAttempts) {
            $retryAfter = $resetTime - time();
            header("Retry-After: $retryAfter");
            return Response::error('ms3_err_rate_limit', HttpStatus::TOO_MANY_REQUESTS);
        }

        // Increment counter
        $this->incrementAttempts($key);

        // Set rate limit headers
        $this->setRateLimitHeaders($attempts + 1, $resetTime);

        return null; // Continue execution
    }

    /**
     * Get key for client identification
     *
     * @return string
     */
    private function resolveRequestKey(): string
    {
        // Use combination of IP and token (if available)
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $token = $_SERVER['HTTP_MS3TOKEN'] ?? '';

        return 'rate_limit:' . md5($ip . ':' . $token);
    }

    /**
     * Get number of attempts
     *
     * @param string $key Key
     * @return int
     */
    private function getAttempts(string $key): int
    {
        $file = $this->getFilePath($key, 'attempts');

        if (!file_exists($file)) {
            return 0;
        }

        $content = file_get_contents($file);
        return (int)$content;
    }

    /**
     * Get counter reset time
     *
     * @param string $key Key
     * @return int Unix timestamp
     */
    private function getResetTime(string $key): int
    {
        $file = $this->getFilePath($key, 'reset');

        if (!file_exists($file)) {
            return time() + $this->decaySeconds;
        }

        $content = file_get_contents($file);
        return (int)$content;
    }

    /**
     * Increment attempts counter
     *
     * @param string $key Key
     * @return void
     */
    private function incrementAttempts(string $key): void
    {
        $attempts = $this->getAttempts($key) + 1;
        $resetTime = $this->getResetTime($key);

        // If this is first attempt in period, set reset time
        if ($attempts === 1) {
            $resetTime = time() + $this->decaySeconds;
        }

        file_put_contents($this->getFilePath($key, 'attempts'), $attempts);
        file_put_contents($this->getFilePath($key, 'reset'), $resetTime);
    }

    /**
     * Reset attempts counter
     *
     * @param string $key Key
     * @return void
     */
    private function resetAttempts(string $key): void
    {
        @unlink($this->getFilePath($key, 'attempts'));
        @unlink($this->getFilePath($key, 'reset'));
    }

    /**
     * Get file path for data storage
     *
     * @param string $key Key
     * @param string $type Data type (attempts or reset)
     * @return string
     */
    private function getFilePath(string $key, string $type): string
    {
        return $this->storagePath . '/' . $key . '_' . $type . '.tmp';
    }

    /**
     * Set rate limit headers
     *
     * @param int $attempts Current number of attempts
     * @param int $resetTime Counter reset time
     * @return void
     */
    private function setRateLimitHeaders(int $attempts, int $resetTime): void
    {
        header('X-RateLimit-Limit: ' . $this->maxAttempts);
        header('X-RateLimit-Remaining: ' . max(0, $this->maxAttempts - $attempts));
        header('X-RateLimit-Reset: ' . $resetTime);
    }
}
