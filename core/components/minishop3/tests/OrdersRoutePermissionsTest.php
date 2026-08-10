<?php

/**
 * Router-level check: /api/mgr/orders reads use msorder_list, writes use msorder_save (#377).
 *
 * Uses a stub modX so manager routes can load without a full MODX install.
 * Also dispatches sample routes to assert runtime 403 when the required permission is missing.
 *
 * Run: php tests/OrdersRoutePermissionsTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Router\Middleware\PermissionMiddleware;
use MiniShop3\Router\Router;
use MODX\Revolution\modX;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $label) use ($fail): void {
    if ($actual !== $expected) {
        $fail(sprintf(
            "%s:\nexpected: %s\nactual:   %s",
            $label,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
};

$permissionFromMiddlewares = static function (array $middlewares) use ($fail): ?string {
    $found = null;
    foreach ($middlewares as $middleware) {
        if (!$middleware instanceof PermissionMiddleware) {
            continue;
        }
        $reflection = new ReflectionProperty(PermissionMiddleware::class, 'permission');
        $permission = $reflection->getValue($middleware);
        if ($found !== null && $found !== $permission) {
            $fail("conflicting PermissionMiddleware values: {$found} vs {$permission}");
        }
        $found = $permission;
    }

    return $found;
};

$buildRouter = static function (modX $modx): Router {
    $router = new Router($modx);
    $router->loadRoutes(dirname(__DIR__) . '/config/routes/manager.php');
    $router->build();

    return $router;
};

$assertDenied = static function (
    Router $router,
    string $method,
    string $uri,
    string $requiredPermission,
    string $label
) use ($assertSame, $fail): void {
    $response = $router->dispatch($uri, $method);
    $data = $response->getData();
    $assertSame(403, $response->getStatusCode(), "{$label}: status");
    $assertSame(false, $data['success'] ?? null, "{$label}: success");
    $assertSame(403, $data['code'] ?? null, "{$label}: body code");
    $message = (string) ($data['message'] ?? '');
    if (!str_contains($message, $requiredPermission)) {
        $fail("{$label}: message should mention {$requiredPermission}, got: {$message}");
    }
};

$_SERVER['HTTP_MODAUTH'] = 'test-modauth-token';

$modx = new modX();
$router = $buildRouter($modx);

$routesProperty = new ReflectionProperty(Router::class, 'routes');
$registered = $routesProperty->getValue($router);

$expected = [
    'GET /api/mgr/orders' => 'msorder_list',
    'GET /api/mgr/orders/filters' => 'msorder_list',
    'GET /api/mgr/orders/stats' => 'msorder_list',
    'GET /api/mgr/orders/{id}' => 'msorder_list',
    'GET /api/mgr/orders/{id}/products' => 'msorder_list',
    'GET /api/mgr/orders/{id}/logs' => 'msorder_list',
    'POST /api/mgr/orders' => 'msorder_save',
    'DELETE /api/mgr/orders/bulk' => 'msorder_save',
    'POST /api/mgr/orders/{id}/finalize' => 'msorder_save',
    'POST /api/mgr/orders/{id}/recalculate-cost' => 'msorder_save',
    'PUT /api/mgr/orders/{id}' => 'msorder_save',
    'DELETE /api/mgr/orders/{id}' => 'msorder_save',
    'POST /api/mgr/orders/{id}/products' => 'msorder_save',
    'PUT /api/mgr/orders/{id}/products/{product_id}' => 'msorder_save',
    'DELETE /api/mgr/orders/{id}/products/{product_id}' => 'msorder_save',
];

$actual = [];
foreach ($registered as $route) {
    $pattern = $route['pattern'] ?? '';
    if (!str_starts_with($pattern, '/api/mgr/orders')) {
        continue;
    }
    $method = is_array($route['method'] ?? null)
        ? implode('|', $route['method'])
        : (string) ($route['method'] ?? '');
    $key = strtoupper($method) . ' ' . $pattern;
    $actual[$key] = $permissionFromMiddlewares($route['middlewares'] ?? []);
}

foreach ($expected as $routeKey => $permission) {
    $assertSame($permission, $actual[$routeKey] ?? null, "route {$routeKey}");
}

$unknown = array_diff(array_keys($actual), array_keys($expected));
if ($unknown !== []) {
    $fail('unexpected orders routes: ' . implode(', ', $unknown));
}

// Runtime: list-only cannot mutate; save-only cannot read (#377)
$modx->setPermissions(['msorder_list']);
$assertDenied($router, 'PUT', '/api/mgr/orders/1', 'msorder_save', 'list-only PUT order');
$assertDenied($router, 'POST', '/api/mgr/orders/1/finalize', 'msorder_save', 'list-only finalize');
$assertDenied($router, 'DELETE', '/api/mgr/orders/1/products/2', 'msorder_save', 'list-only delete product');

$modx->setPermissions(['msorder_save']);
$assertDenied($router, 'GET', '/api/mgr/orders', 'msorder_list', 'save-only GET list');
$assertDenied($router, 'GET', '/api/mgr/orders/1', 'msorder_list', 'save-only GET order');

fwrite(STDOUT, "OK OrdersRoutePermissionsTest\n");
exit(0);
