<?php

namespace MiniShop3\Middleware;

use MiniShop3\Router\Middleware\MiddlewareInterface;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * Middleware for authorization token verification (Web API)
 *
 * Checks for token in HTTP_MS3TOKEN header and saves it to session.
 * For public endpoints (cart/get, product/get) token is optional.
 */
class TokenMiddleware implements MiddlewareInterface
{
    /** @var modX */
    private modX $modx;

    /** @var array Routes that don't require token */
    private array $publicRoutes = [
        '/api/v1/cart/get',
        '/api/v1/product/get',
        '/api/v1/product/list',
        '/api/v1/customer/token/get',
        '/api/v1/customer/token/refresh',
        '/api/v1/health',
    ];

    /**
     * @param modX $modx MODX instance
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Handle request
     *
     * @param array $params URL parameters from router
     * @return Response|null Return Response to stop execution, or null to continue
     */
    public function handle(array $params)
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Public endpoints don't require token
        if ($this->isPublicRoute($uri)) {
            return null; // Continue execution
        }

        // Check session - if customer is already authenticated, pass through
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!empty($_SESSION['ms3']['customer_id'])) {
            // Customer is authenticated in session - check that they exist
            $customer = $this->modx->getObject(\MiniShop3\Model\msCustomer::class, $_SESSION['ms3']['customer_id']);
            if ($customer) {
                return null; // Continue execution
            }
        }

        // Get token from header
        $token = $_SERVER['HTTP_MS3TOKEN'] ?? '';

        // Alternatively token can be passed in parameter (support both formats)
        if (empty($token)) {
            $token = $_REQUEST['ms3_token'] ?? $_REQUEST['token'] ?? '';
        }

        // Check token presence
        if (empty($token)) {
            return Response::error('ms3_err_token', 401);
        }

        // Check token validity and get customer_id
        $tokenObj = $this->modx->getObject(\MiniShop3\Model\msCustomerToken::class, [
            'token' => $token,
            'type' => \MiniShop3\Model\msCustomerToken::TYPE_API
        ]);

        if (!$tokenObj) {
            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_ERROR,
                "[TokenMiddleware] Token not found in database. Token: " . substr($token, 0, 16) . "... Length: " . strlen($token)
            );
            return Response::error('ms3_err_token_invalid', 401);
        }

        if ($tokenObj->isExpired()) {
            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_ERROR,
                "[TokenMiddleware] Token expired. Expires: " . $tokenObj->get('expires_at') . ", Now: " . date('Y-m-d H:i:s')
            );
            return Response::error('ms3_err_token_expired', 401);
        }

        // Save token and customer_id to session
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['ms3']['customer_token'] = $token;
        $_SESSION['ms3']['customer_id'] = $tokenObj->get('customer_id');

        return null; // Continue execution
    }

    /**
     * Check if route is public
     *
     * @param string $uri Request URI (not used, kept for compatibility)
     * @return bool
     */
    private function isPublicRoute(string $uri): bool
    {
        // Get route from request parameters (api.php?route=/api/v1/...)
        $route = $_REQUEST['route'] ?? '';

        // If route is empty, try to extract from URI
        if (empty($route)) {
            $path = parse_url($uri, PHP_URL_PATH);
            // Remove api.php from path beginning if present
            $route = preg_replace('#^/assets/components/minishop3/api\.php#', '', $path);
        }

        foreach ($this->publicRoutes as $publicRoute) {
            if (str_starts_with($route, $publicRoute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add public route
     *
     * @param string $route Route
     * @return void
     */
    public function addPublicRoute(string $route): void
    {
        $this->publicRoutes[] = $route;
    }

    /**
     * Set list of public routes
     *
     * @param array $routes Array of routes
     * @return void
     */
    public function setPublicRoutes(array $routes): void
    {
        $this->publicRoutes = $routes;
    }
}
