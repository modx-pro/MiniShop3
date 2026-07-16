<?php

namespace MiniShop3\Processors\Api;

use MiniShop3\Router\Router as ApiRouter;

/**
 * Loads manager-only routes for connector.php processors (Index / Router).
 *
 * Storefront (/api/v1) stays on api.php — never load web.php here (#384).
 */
final class ManagerConnectorRouteLoader
{
    /**
     * Paths that must never be loaded by the manager connector (#384).
     *
     * @return list<string> suffixes relative to core/ or component root
     */
    public static function forbiddenPathSuffixes(): array
    {
        return [
            'config/routes/web.php',
            'config/ms3_routes_web.custom.php',
            'config/ms3.routes.d/web',
        ];
    }

    /**
     * @throws \RuntimeException when required manager.php is missing
     */
    public static function load(ApiRouter $router, string $componentPath, string $corePath): void
    {
        $componentPath = rtrim($componentPath, '/\\') . DIRECTORY_SEPARATOR;
        $corePath = rtrim($corePath, '/\\') . DIRECTORY_SEPARATOR;

        $managerRoutes = $componentPath . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'manager.php';
        // Caller should pre-check; keep throw as safety net for direct use.
        if (!file_exists($managerRoutes)) {
            throw new \RuntimeException('System routes not found: ' . $managerRoutes);
        }

        $router->loadRoutes($managerRoutes);

        $customRoutes = $corePath . 'config' . DIRECTORY_SEPARATOR . 'ms3_routes_manager.custom.php';
        if (file_exists($customRoutes)) {
            $router->loadRoutes($customRoutes);
        }

        $addonDir = (defined('MODX_CORE_PATH')
            && rtrim((string)MODX_CORE_PATH, '/\\') === rtrim($corePath, '/\\'))
            ? ApiRouter::coreAddonRoutesDirectory('manager')
            : $corePath . 'config' . DIRECTORY_SEPARATOR . 'ms3.routes.d' . DIRECTORY_SEPARATOR . 'manager';

        $router->loadRoutesFromDirectory($addonDir);
    }

    /**
     * Storefront routes must not be served through connector Index/Router.
     */
    public static function isStorefrontRoute(string $route): bool
    {
        return str_starts_with($route, '/api/v1');
    }
}
