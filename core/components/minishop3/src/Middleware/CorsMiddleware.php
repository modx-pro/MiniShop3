<?php

namespace MiniShop3\Middleware;

use MiniShop3\Router\Middleware\MiddlewareInterface;
use MiniShop3\Router\Response;

/**
 * Middleware for handling CORS (Cross-Origin Resource Sharing)
 *
 * Allows API to work with browser requests from other domains.
 * Required for headless frontend (Vue, React) on separate domains.
 */
class CorsMiddleware implements MiddlewareInterface
{
    /** @var array Allowed domains (origins) */
    private array $allowedOrigins;

    /** @var array Allowed HTTP methods */
    private array $allowedMethods;

    /** @var array Allowed headers */
    private array $allowedHeaders;

    /** @var bool Allow credentials (cookies, auth headers) */
    private bool $allowCredentials;

    /** @var int Preflight request cache time (in seconds) */
    private int $maxAge;

    /**
     * @param array $config CORS configuration
     */
    public function __construct(array $config = [])
    {
        $this->allowedOrigins = $this->normalizeToArray($config['allowed_origins'] ?? ['*']);
        $this->allowedMethods = $this->normalizeToArray($config['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']);
        $this->allowedHeaders = $this->normalizeToArray($config['allowed_headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With', 'MS3TOKEN']);
        $this->allowCredentials = (bool)($config['allow_credentials'] ?? true);
        $this->maxAge = (int)($config['max_age'] ?? 86400); // 24 hours
    }

    /**
     * Normalize value to array (handles string, JSON string, or array)
     *
     * @param mixed $value
     * @return array
     */
    private function normalizeToArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            // Try JSON decode first
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            // Comma-separated string
            if (str_contains($value, ',')) {
                return array_map('trim', explode(',', $value));
            }

            // Single value
            return [$value];
        }

        return ['*'];
    }

    /**
     * Handle request
     *
     * @param array $params URL parameters from router
     * @return Response|null Return Response to stop execution, or null to continue
     */
    public function handle(array $params)
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Check if origin is allowed
        if ($this->isOriginAllowed($origin)) {
            $this->setCorsHeaders($origin);
        }

        // For preflight requests (OPTIONS) immediately return 200
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        return null; // Continue execution
    }

    /**
     * Check if origin is allowed
     *
     * @param string $origin Origin from header
     * @return bool
     */
    private function isOriginAllowed(string $origin): bool
    {
        if (empty($origin)) {
            return false;
        }

        // If all origins are allowed
        if (in_array('*', $this->allowedOrigins)) {
            return true;
        }

        // Check exact match
        if (in_array($origin, $this->allowedOrigins)) {
            return true;
        }

        // Check wildcard patterns (e.g.: *.example.com)
        foreach ($this->allowedOrigins as $allowedOrigin) {
            if (str_contains($allowedOrigin, '*')) {
                $pattern = str_replace('*', '.*', $allowedOrigin);
                if (preg_match('#^' . $pattern . '$#', $origin)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Set CORS headers
     *
     * @param string $origin Origin for Access-Control-Allow-Origin header
     * @return void
     */
    private function setCorsHeaders(string $origin): void
    {
        // For wildcard origin (*) we cannot use credentials
        if (in_array('*', $this->allowedOrigins) && !$this->allowCredentials) {
            header('Access-Control-Allow-Origin: *');
        } else {
            // For specific origin we can use credentials
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }

        if ($this->allowCredentials) {
            header('Access-Control-Allow-Credentials: true');
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $this->allowedMethods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $this->allowedHeaders));
        header('Access-Control-Max-Age: ' . $this->maxAge);
    }
}
