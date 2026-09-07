<?php

/**
 * Router-level ACL for /api/mgr/model-fields data reads (#613 review).
 *
 * combo-options and visible execute combo sources (PII) — not mgr-auth alone.
 * Schema reads stay auth-only; writes still need mssetting_save.
 *
 * Run: php tests/ModelFieldsRoutePermissionsTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Router\Middleware\AnyPermissionMiddleware;
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
    string $label
) use ($assertSame): void {
    $response = $router->dispatch($uri, $method);
    $data = $response->getData();
    $assertSame(403, $response->getStatusCode(), "{$label}: status");
    $assertSame(false, $data['success'] ?? null, "{$label}: success");
    $assertSame(403, $data['code'] ?? null, "{$label}: body code");
};

$assertAllowedPastPermissionGate = static function (
    Router $router,
    string $method,
    string $uri,
    string $label
) use ($fail): void {
    try {
        $response = $router->dispatch($uri, $method);
    } catch (\Throwable $e) {
        // Handler may fail without full MODX DI; permission middleware already passed.
        return;
    }
    if ($response->getStatusCode() === 403) {
        $fail("{$label}: expected permission gate to pass, got 403");
    }
};

$_SERVER['HTTP_MODAUTH'] = 'test-modauth-token';

$modx = new modX();
$router = $buildRouter($modx);

$routesProperty = new ReflectionProperty(Router::class, 'routes');
$registered = $routesProperty->getValue($router);

$expectedAny = [
    'GET /api/mgr/model-fields/visible/{model}',
    'GET /api/mgr/model-fields/combo-options/{model}',
    'GET /api/mgr/model-fields/combo-options/{model}/{field_name}',
];

$expectedAuthOnly = [
    'GET /api/mgr/model-fields/models',
    'GET /api/mgr/model-fields/sections/{model}',
    'GET /api/mgr/model-fields',
    'GET /api/mgr/model-fields/{id}',
];

$expectedWrite = [
    'POST /api/mgr/model-fields/sections' => 'mssetting_save',
    'PUT /api/mgr/model-fields/sections/ranks' => 'mssetting_save',
    'PUT /api/mgr/model-fields/sections/{id}' => 'mssetting_save',
    'DELETE /api/mgr/model-fields/sections/{id}' => 'mssetting_save',
    'POST /api/mgr/model-fields' => 'mssetting_save',
    'PUT /api/mgr/model-fields/ranks' => 'mssetting_save',
    'PUT /api/mgr/model-fields/{id}' => 'mssetting_save',
    'DELETE /api/mgr/model-fields/{id}' => 'mssetting_save',
];

$requiredAnyPermissions = [
    'msorder_list',
    'msorder_view',
    'msorder_save',
    'msproduct_save',
    'mscategory_save',
    'mssetting_list',
    'mssetting_view',
    'mssetting_save',
    'view_document',
];

foreach ($registered as $route) {
    $pattern = $route['pattern'] ?? '';
    if (!str_starts_with($pattern, '/api/mgr/model-fields')) {
        continue;
    }
    $method = is_array($route['method'] ?? null)
        ? implode('|', $route['method'])
        : (string) ($route['method'] ?? '');
    $key = strtoupper($method) . ' ' . $pattern;
    $middlewares = $route['middlewares'] ?? [];

    $hasAny = false;
    $permission = null;
    foreach ($middlewares as $middleware) {
        if ($middleware instanceof AnyPermissionMiddleware) {
            $hasAny = true;
            $reflection = new ReflectionProperty(AnyPermissionMiddleware::class, 'permissions');
            $perms = $reflection->getValue($middleware);
            sort($perms);
            $expectedSorted = $requiredAnyPermissions;
            sort($expectedSorted);
            $assertSame($expectedSorted, $perms, "{$key}: AnyPermissionMiddleware set");
        }
        if ($middleware instanceof PermissionMiddleware) {
            $reflection = new ReflectionProperty(PermissionMiddleware::class, 'permission');
            $permission = $reflection->getValue($middleware);
        }
    }

    if (in_array($key, $expectedAny, true)) {
        if (!$hasAny) {
            $fail("{$key} must use AnyPermissionMiddleware");
        }
        continue;
    }

    if (in_array($key, $expectedAuthOnly, true)) {
        if ($hasAny || $permission !== null) {
            $fail("{$key} must be auth-only (no Permission/AnyPermission beyond Auth)");
        }
        // AuthMiddleware is on the outer /api/mgr group — still present on the route.
        continue;
    }

    if (array_key_exists($key, $expectedWrite)) {
        $assertSame($expectedWrite[$key], $permission, "{$key}: write permission");
        continue;
    }

    $fail("unexpected model-fields route registered: {$key}");
}

foreach ($expectedAny as $routeKey) {
    $found = false;
    foreach ($registered as $route) {
        $pattern = $route['pattern'] ?? '';
        $method = is_array($route['method'] ?? null)
            ? implode('|', $route['method'])
            : (string) ($route['method'] ?? '');
        if (strtoupper($method) . ' ' . $pattern === $routeKey) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $fail("missing registered route {$routeKey}");
    }
}

// Runtime: mgr without form/settings perms cannot hit data-bearing reads
$modx->setPermissions([]);
$assertDenied(
    $router,
    'GET',
    '/api/mgr/model-fields/combo-options/msOrder/customer_id',
    'mgr-only combo-options field'
);
$assertDenied(
    $router,
    'GET',
    '/api/mgr/model-fields/combo-options/msOrder',
    'mgr-only combo-options model'
);
$assertDenied(
    $router,
    'GET',
    '/api/mgr/model-fields/visible/msOrder',
    'mgr-only visible'
);

// Order manager without mssetting_save can pass the data-read gate (#613)
$modx->setPermissions(['msorder_save']);
$assertAllowedPastPermissionGate(
    $router,
    'GET',
    '/api/mgr/model-fields/combo-options/msOrder/customer_id',
    'msorder_save combo-options'
);
$assertAllowedPastPermissionGate(
    $router,
    'GET',
    '/api/mgr/model-fields/visible/msOrder',
    'msorder_save visible'
);

// Same order manager still cannot mutate schema
$assertDenied(
    $router,
    'POST',
    '/api/mgr/model-fields',
    'msorder_save cannot POST model-fields'
);

// Schema list stays reachable without shop permissions (auth-only)
$modx->setPermissions([]);
$assertAllowedPastPermissionGate(
    $router,
    'GET',
    '/api/mgr/model-fields/models',
    'auth-only models'
);

fwrite(STDOUT, "OK ModelFieldsRoutePermissionsTest\n");
exit(0);
