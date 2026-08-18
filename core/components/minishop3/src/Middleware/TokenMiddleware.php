<?php

namespace MiniShop3\Middleware;

use MiniShop3\Router\Middleware\MiddlewareInterface;
use MiniShop3\Router\ApiErrorCode;
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
 * 3. httpOnly cookie `ms3_token` (injected into $_REQUEST for controllers)
 * 4. Session cache (still DB-validated)
 *
 * Query-string `ms3_token` is stripped and never accepted as API credentials (#576).
 * Cookie injection at start of handle() copies $_COOKIE['ms3_token'] → $_REQUEST['ms3_token']
 * for backward compatibility with controllers reading $_REQUEST.
 *
 * Public endpoints (catalog, health, token/get): token is optional.
 * Non-public endpoints (including cart/get) without a token auto-mint an anonymous API token.
 */
class TokenMiddleware implements MiddlewareInterface
{
    private modX $modx;

    /** @var list<string> Prefixes matched with str_starts_with; missing token skips auto-mint */
    private array $publicRoutes = [
        '/api/v1/product/get/',
        '/api/v1/product/list',
        '/api/v1/product/filters',
        '/api/v1/category/get/',
        '/api/v1/category/list',
        '/api/v1/category/tree',
        '/api/v1/delivery/get/',
        '/api/v1/delivery/list',
        '/api/v1/payment/get/',
        '/api/v1/payment/list',
        '/api/v1/customer/token/get',
        '/api/v1/customer/logout',
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
     * @param array $params URL parameters from router
     * @return Response|null Return Response to stop execution, or null to continue
     */
    public function handle(array $params)
    {
        $this->stripQueryStringApiTokens();

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

        // Hydrate request token from session cache (still validated below).
        if (
            !$isPublic
            && !empty($_SESSION['ms3']['customer_token'])
            && empty($_REQUEST['ms3_token'])
        ) {
            $_REQUEST['ms3_token'] = (string) $_SESSION['ms3']['customer_token'];
        }

        // Resolve token (middleware order; login bind uses getBindableTokenString)
        $token = TokenService::resolveTokenFromRequest();

        // If a token is present, always validate it (do not skip via session bypass).
        if (!empty($token)) {
            $resolved = $tokenService->resolveApiToken($token);

            if ($resolved['reason'] === 'ok') {
                $tokenService->syncSessionFromToken($resolved['token']);
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
                    return $this->unauthorizedError(ApiErrorCode::TOKEN_EXPIRED, 'ms3_err_token_expired');
                }
            } elseif (!$isPublic) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[TokenMiddleware] Token not found in database. Token: ' . substr($token, 0, 16) . '...'
                );
                // Keep machine-stable keys in message — ApiClient.isTokenError() matches them
                return $this->unauthorizedError(ApiErrorCode::TOKEN_INVALID, 'ms3_err_token_invalid');
            }
        } elseif (!$isPublic && !empty($_SESSION['ms3']['customer_id'])) {
            // Stale session identity without a resolvable token must not bypass revoke.
            $this->clearClientTokenState();
        }

        // No valid token found
        if (!$isPublic) {
            $result = $tokenService->generateCustomerToken();

            if (!empty($result['token'])) {
                $_REQUEST['ms3_token'] = $result['token'];
                return null;
            }

            return Response::errorWithCode(
                ApiErrorCode::INTERNAL_ERROR,
                'ms3_customer_err_token_create',
                HttpStatus::INTERNAL_SERVER_ERROR
            );
        }

        return null;
    }

    /**
     * Token reject / mint failure (401 + machine error_code).
     */
    private function unauthorizedError(string $errorCode, string $message): Response
    {
        return Response::errorWithCode($errorCode, $message, HttpStatus::UNAUTHORIZED);
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
     * Remove API session token from the query string so controllers cannot pick it up (#576).
     * Email verification uses `?token=` on a route without this middleware.
     */
    private function stripQueryStringApiTokens(): void
    {
        if (!array_key_exists('ms3_token', $_GET)) {
            return;
        }

        unset($_GET['ms3_token'], $_REQUEST['ms3_token']);
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
