<?php

/**
 * Router-level check: /api/mgr/config writes require mssetting_save; reads stay Auth-only (#381).
 *
 * Run: php tests/ConfigRoutePermissionsTest.php
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

$permissionFromMiddlewares = static function (array $middlewares): ?string {
    $found = null;
    foreach ($middlewares as $middleware) {
        if (!$middleware instanceof PermissionMiddleware) {
            continue;
        }
        $reflection = new ReflectionProperty(PermissionMiddleware::class, 'permission');
        $permission = $reflection->getValue($middleware);
        $found = $permission;
    }

    return $found;
};

$_SERVER['HTTP_MODAUTH'] = 'test-modauth-token';

$modx = new modX();
$router = new Router($modx);
$router->loadRoutes(dirname(__DIR__) . '/config/routes/manager.php');
$router->build();

$routesProperty = new ReflectionProperty(Router::class, 'routes');
$registered = $routesProperty->getValue($router);

$expected = [
    'GET /api/mgr/config/page-fields/{page_key}' => null,
    'GET /api/mgr/config/page-fields/{page_key}/all' => null,
    'GET /api/mgr/config/sections/{page_key}' => null,
    'PUT /api/mgr/config/page-fields/{page_key}' => 'mssetting_save',
    'PUT /api/mgr/config/sections/{page_key}' => 'mssetting_save',
    'DELETE /api/mgr/config/sections/{page_key}/{section_key}' => 'mssetting_save',
];

$actual = [];
foreach ($registered as $route) {
    $pattern = $route['pattern'] ?? '';
    if (!str_starts_with($pattern, '/api/mgr/config')) {
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

$removedFieldOverride = 'DELETE /api/mgr/config/page-fields/{page_key}/{field_name}';
if (array_key_exists($removedFieldOverride, $actual)) {
    $fail("route {$removedFieldOverride} should be absent after #347");
}

$unknown = array_diff(array_keys($actual), array_keys($expected));
if ($unknown !== []) {
    $fail('unexpected config routes: ' . implode(', ', $unknown));
}

$assertDenied = static function (
    Router $router,
    string $method,
    string $uri,
    string $label
) use ($assertSame, $fail): void {
    $response = $router->dispatch($uri, $method);
    $data = $response->getData();
    $assertSame(403, $response->getStatusCode(), "{$label}: status");
    $assertSame(false, $data['success'] ?? null, "{$label}: success");
    if (!str_contains((string) ($data['message'] ?? ''), 'mssetting_save')) {
        $fail("{$label}: message should mention mssetting_save");
    }
};

// Runtime: no mssetting_save → write 403; GET still reaches handler (not 403 from permission)
$modx->setPermissions([]);
$assertDenied($router, 'PUT', '/api/mgr/config/page-fields/order', 'PUT page-fields');
$assertDenied($router, 'DELETE', '/api/mgr/config/sections/order/main', 'DELETE section');
$assertDenied($router, 'PUT', '/api/mgr/config/sections/order', 'PUT sections');

fwrite(STDOUT, "OK ConfigRoutePermissionsTest\n");
exit(0);
