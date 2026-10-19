<?php

/**
 * Ensure /api/mgr/statuses/transitions is registered before /{id} (#785).
 *
 * Run: php tests/StatusesTransitionsRouteTest.php
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

$_SERVER['HTTP_MODAUTH'] = 'test-modauth-token';

$modx = new modX();
$router = new Router($modx);
$router->loadRoutes(dirname(__DIR__) . '/config/routes/manager.php');
$router->build();

$routesProperty = new ReflectionProperty(Router::class, 'routes');
$registered = $routesProperty->getValue($router);

$foundGet = null;
$foundPut = null;
$idRouteIndex = null;
$transitionsGetIndex = null;

foreach ($registered as $index => $route) {
    $pattern = $route['pattern'] ?? '';
    $method = is_array($route['method'] ?? null)
        ? implode('|', $route['method'])
        : (string) ($route['method'] ?? '');
    $method = strtoupper($method);

    if ($pattern === '/api/mgr/statuses/transitions' && $method === 'GET') {
        $foundGet = $route;
        $transitionsGetIndex = $index;
    }
    if ($pattern === '/api/mgr/statuses/transitions' && $method === 'PUT') {
        $foundPut = $route;
    }
    if ($pattern === '/api/mgr/statuses/{id}' && $method === 'GET' && $idRouteIndex === null) {
        $idRouteIndex = $index;
    }
}

if ($foundGet === null || $foundPut === null) {
    $fail('GET/PUT /api/mgr/statuses/transitions not registered');
}

if ($idRouteIndex === null || $transitionsGetIndex === null || $transitionsGetIndex >= $idRouteIndex) {
    $fail('GET /transitions must be registered before GET /{id}');
}

$permission = null;
foreach ($foundGet['middlewares'] ?? [] as $middleware) {
    if ($middleware instanceof PermissionMiddleware) {
        $reflection = new ReflectionProperty(PermissionMiddleware::class, 'permission');
        $permission = $reflection->getValue($middleware);
    }
}

if ($permission !== 'mssetting_save') {
    $fail('transitions route must require mssetting_save, got ' . var_export($permission, true));
}

fwrite(STDOUT, "OK StatusesTransitionsRouteTest\n");
exit(0);
