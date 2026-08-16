<?php

namespace MiniShop3\Middleware;

use MiniShop3\Router\Middleware\MiddlewareInterface;
use MiniShop3\Router\Response;
use MiniShop3\Utils\CorsConfig;

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
        $normalized = CorsConfig::normalizeCorsConfig($config);
        $this->allowedOrigins = $normalized['allowed_origins'];
        $this->allowedMethods = $normalized['allowed_methods'];
        $this->allowedHeaders = $normalized['allowed_headers'];
        $this->allowCredentials = $normalized['allow_credentials'];
        $this->maxAge = $normalized['max_age'];
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

        if (CorsConfig::isOriginAllowed($origin, $this->allowedOrigins)) {
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
     * Set CORS headers
     *
     * @param string $origin Origin for Access-Control-Allow-Origin header
     * @return void
     */
    private function setCorsHeaders(string $origin): void
    {
        if (CorsConfig::hasWildcardOrigin($this->allowedOrigins)) {
            header('Access-Control-Allow-Origin: *');
        } else {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');

            if ($this->allowCredentials) {
                header('Access-Control-Allow-Credentials: true');
            }
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $this->allowedMethods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $this->allowedHeaders));
        header('Access-Control-Max-Age: ' . $this->maxAge);
    }
}
