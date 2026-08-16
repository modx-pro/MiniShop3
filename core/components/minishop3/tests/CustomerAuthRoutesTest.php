<?php

/**
 * Router-level check: customer auth routes exposed in Web API (#422).
 *
 * Uses ModxStub so web routes load without a full MODX install.
 * Asserts route registration and middleware stack via Router introspection
 * (same pattern as OrdersRoutePermissionsTest).
 *
 * Run: php tests/CustomerAuthRoutesTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Controllers\Api\Web\CustomerAuthController;
use MiniShop3\Middleware\TokenMiddleware;
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

$hasTokenMiddleware = static function (array $middlewares): bool {
    foreach ($middlewares as $middleware) {
        if ($middleware instanceof TokenMiddleware) {
            return true;
        }
    }

    return false;
};

$handlerUsesAuthController = static function (callable $handler, string $methodName) use ($fail): void {
    if (!$handler instanceof Closure) {
        $fail('auth route handler must be a closure');
    }

    $reflection = new ReflectionFunction($handler);
    $staticVariables = $reflection->getStaticVariables();
    if (!array_key_exists('customerAuth', $staticVariables)) {
        $fail('auth route closure must capture $customerAuth factory');
    }

    $factory = $staticVariables['customerAuth'];
    if (!$factory instanceof Closure) {
        $fail('$customerAuth must be a closure factory');
    }

    $factoryReflection = new ReflectionFunction($factory);
    $factoryFile = $factoryReflection->getFileName();
    $factoryStart = $factoryReflection->getStartLine();
    $factoryEnd = $factoryReflection->getEndLine();
    if ($factoryFile === false || $factoryStart <= 0 || $factoryEnd < $factoryStart) {
        $fail('unable to inspect $customerAuth factory');
    }

    $factorySource = implode("\n", array_slice(
        file($factoryFile, FILE_IGNORE_NEW_LINES) ?: [],
        $factoryStart - 1,
        $factoryEnd - $factoryStart + 1
    ));

    if (!str_contains($factorySource, CustomerAuthController::class)) {
        $fail('$customerAuth factory must instantiate CustomerAuthController');
    }

    $file = $reflection->getFileName();
    $startLine = $reflection->getStartLine();
    $endLine = $reflection->getEndLine();
    if ($file === false || $startLine <= 0 || $endLine < $startLine) {
        $fail('unable to inspect auth route closure source');
    }

    $source = implode("\n", array_slice(
        file($file, FILE_IGNORE_NEW_LINES) ?: [],
        $startLine - 1,
        $endLine - $startLine + 1
    ));

    if (!str_contains($source, '$customerAuth()')) {
        $fail('auth route handler must delegate through $customerAuth()');
    }

    if (!str_contains($source, "->{$methodName}(")) {
        $fail("auth route handler must call CustomerAuthController::{$methodName}()");
    }
};

$modx = new modX();
$router = new Router($modx);
$router->loadRoutes(dirname(__DIR__) . '/config/routes/web.php');
$router->build();

$routesProperty = new ReflectionProperty(Router::class, 'routes');
$registered = $routesProperty->getValue($router);

$expectedRoutes = [
    'POST /api/v1/customer/login' => [
        'tokenMiddleware' => false,
        'handlerMethod' => 'loginFromRequest',
    ],
    'POST /api/v1/customer/register' => [
        'tokenMiddleware' => false,
        'handlerMethod' => 'registerFromRequest',
    ],
    'GET /api/v1/customer/me' => [
        'tokenMiddleware' => true,
        'handlerMethod' => 'me',
    ],
    'POST /api/v1/customer/logout' => [
        'tokenMiddleware' => true,
        'handlerMethod' => 'logout',
    ],
    'POST /api/v1/customer/forgot-password' => [
        'tokenMiddleware' => false,
        'handlerMethod' => 'forgotPasswordFromRequest',
    ],
    'POST /api/v1/customer/reset-password' => [
        'tokenMiddleware' => false,
        'handlerMethod' => 'resetPasswordFromRequest',
    ],
    'POST /api/v1/customer/token/refresh' => [
        'tokenMiddleware' => true,
        'handlerMethod' => 'refreshToken',
    ],
];

$actual = [];
foreach ($registered as $route) {
    $pattern = $route['pattern'] ?? '';
    if (!str_starts_with($pattern, '/api/v1/customer/')) {
        continue;
    }

    $method = is_array($route['method'] ?? null)
        ? implode('|', $route['method'])
        : (string) ($route['method'] ?? '');
    $key = strtoupper($method) . ' ' . $pattern;

    $actual[$key] = [
        'tokenMiddleware' => $hasTokenMiddleware($route['middlewares'] ?? []),
        'handler' => $route['handler'] ?? null,
    ];
}

foreach ($expectedRoutes as $routeKey => $expectation) {
    if (!array_key_exists($routeKey, $actual)) {
        $fail("missing route {$routeKey}");
    }

    $route = $actual[$routeKey];
    $assertSame(
        $expectation['tokenMiddleware'],
        $route['tokenMiddleware'],
        "TokenMiddleware on {$routeKey}"
    );

    $handler = $route['handler'];
    if (!is_callable($handler)) {
        $fail("route {$routeKey} must have callable handler");
    }

    $handlerUsesAuthController($handler, $expectation['handlerMethod']);
}

echo "OK CustomerAuthRoutesTest\n";
