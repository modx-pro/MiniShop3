<?php

namespace MiniShop3\Middleware;

use MiniShop3\Router\Middleware\MiddlewareInterface;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\TokenService;
use MiniShop3\Utils\CookieHelper;
use MiniShop3\Utils\SessionHelper;
use MODX\Revolution\modX;

/**
 * Middleware for authorization token verification (Web API)
 *
 * Token resolution order:
 * 1. Authorization: Bearer header (mobile apps)
 * 2. HTTP_MS3TOKEN header (legacy)
 * 3. $_REQUEST['ms3_token'] (includes httpOnly cookie via injection)
 *
 * Cookie injection at start of handle() copies $_COOKIE['ms3_token'] → $_REQUEST['ms3_token']
 * for backward compatibility with controllers reading $_REQUEST.
 *
 * For public endpoints (cart/get, product/get) token is optional.
 * For non-public endpoints without token — auto-creates anonymous token.
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
     * Token resolution order:
     * 1. Authorization: Bearer header (for mobile apps)
     * 2. HTTP_MS3TOKEN header (legacy)
     * 3. $_REQUEST['ms3_token'] (includes httpOnly cookie via injection + legacy URL param)
     *
     * Cookie injection: copies $_COOKIE['ms3_token'] → $_REQUEST['ms3_token']
     * so all controllers (CartController, OrderController, etc.) work without changes.
     *
     * @param array $params URL parameters from router
     * @return Response|null Return Response to stop execution, or null to continue
     */
    public function handle(array $params)
    {
        // Cookie injection: make cookie token available via $_REQUEST for backward compat
        $cookieToken = CookieHelper::getTokenFromCookie();
        if (!empty($cookieToken) && empty($_REQUEST['ms3_token'])) {
            $_REQUEST['ms3_token'] = $cookieToken;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $isPublic = $this->isPublicRoute($uri);

        SessionHelper::ensureActive();

        /** @var TokenService $tokenService */
        $tokenService = $this->modx->services->get('ms3_token_service');

        // Resolve token from multiple sources
        $token = $this->resolveToken();

        // If a token is present, always validate it (do not skip via session bypass).
        if (!empty($token)) {
            $resolved = $tokenService->resolveApiToken($token);

            if ($resolved['reason'] === 'ok') {
                $tokenObj = $resolved['token'];

                if (!isset($_SESSION['ms3'])) {
                    $_SESSION['ms3'] = [];
                }
                $_SESSION['ms3']['customer_token'] = $token;
                $_SESSION['ms3']['customer_id'] = $tokenObj->get('customer_id');
                $_SESSION['ms3']['customer_token_expires'] = strtotime($tokenObj->get('expires_at'));

                CookieHelper::setTokenCookie($this->modx, $token);
                $_REQUEST['ms3_token'] = $token;

                return null;
            }

            $this->clearClientTokenState();

            if ($resolved['reason'] === 'expired') {
                if (!$isPublic) {
                    $this->modx->log(
                        modX::LOG_LEVEL_INFO,
                        '[TokenMiddleware] Rejected expired API token: ' . substr($token, 0, 16) . '...'
                    );
                    return Response::error('ms3_err_token_expired', HttpStatus::UNAUTHORIZED);
                }
            } elseif (!$isPublic) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[TokenMiddleware] Token not found in database. Token: ' . substr($token, 0, 16) . '...'
                );
                return Response::error('ms3_err_token_invalid', HttpStatus::UNAUTHORIZED);
            }
        } elseif (!$isPublic && !empty($_SESSION['ms3']['customer_id'])) {
            // No token in request: allow existing session customer (browser session).
            $customer = $this->modx->getObject(\MiniShop3\Model\msCustomer::class, $_SESSION['ms3']['customer_id']);
            if ($customer && $this->isCustomerSessionAllowed($customer)) {
                return null;
            }

            unset($_SESSION['ms3']['customer_id']);
        }

        // No valid token found
        if (!$isPublic) {
            $result = $tokenService->generateCustomerToken();

            if (!empty($result['token'])) {
                $_REQUEST['ms3_token'] = $result['token'];
                return null;
            }

            return Response::error('ms3_err_token', HttpStatus::UNAUTHORIZED);
        }

        return null;
    }

    /**
     * Clear cookie, request and session token identity after reject/expiry.
     */
    private function clearClientTokenState(): void
    {
        CookieHelper::clearTokenCookie($this->modx);
        unset(
            $_REQUEST['ms3_token'],
            $_SESSION['ms3']['customer_token'],
            $_SESSION['ms3']['customer_token_expires'],
            $_SESSION['ms3']['customer_id']
        );
    }

    /**
     * Resolve token from request sources
     *
     * @return string Token or empty string
     */
    private function resolveToken(): string
    {
        // 1. Authorization: Bearer header (for mobile apps)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            if (!empty($token)) {
                return $token;
            }
        }

        // 2. HTTP_MS3TOKEN header (legacy)
        $token = $_SERVER['HTTP_MS3TOKEN'] ?? '';
        if (!empty($token)) {
            return $token;
        }

        // 3. $_REQUEST (includes cookie via injection + legacy URL param)
        return $_REQUEST['ms3_token'] ?? $_REQUEST['token'] ?? '';
    }

    /**
     * Session shortcut is valid only for active, non-blocked customers.
     */
    private function isCustomerSessionAllowed(\MiniShop3\Model\msCustomer $customer): bool
    {
        if (!$customer->get('is_active')) {
            return false;
        }

        if (!$customer->get('is_blocked')) {
            return true;
        }

        $blockedUntil = $customer->get('blocked_until');
        if ($blockedUntil && strtotime((string)$blockedUntil) > time()) {
            return false;
        }

        return true;
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
