<?php

/**
 * Router-level check: customer auth routes exposed in Web API (#422).
 *
 * Uses ModxStub so web routes load without a full MODX install.
 *
 * Run: php tests/CustomerAuthRoutesTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/../vendor/autoload.php';

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

$modx = new modX();
$router = new Router($modx);
$router->loadRoutes(dirname(__DIR__) . '/config/routes/web.php');
$router->build();

$routesProperty = new ReflectionProperty(Router::class, 'routes');
$registered = $routesProperty->getValue($router);

$expectedRoutes = [
    'POST /api/v1/customer/login' => false,
    'POST /api/v1/customer/register' => false,
    'POST /api/v1/customer/logout' => true,
    'POST /api/v1/customer/forgot-password' => false,
    'POST /api/v1/customer/reset-password' => false,
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
    $actual[$key] = $hasTokenMiddleware($route['middlewares'] ?? []);
}

foreach ($expectedRoutes as $routeKey => $requiresTokenMiddleware) {
    if (!array_key_exists($routeKey, $actual)) {
        $fail("missing route {$routeKey}");
    }
    $assertSame($requiresTokenMiddleware, $actual[$routeKey], "TokenMiddleware on {$routeKey}");
}

$authControllerPath = dirname(__DIR__) . '/src/Controllers/Api/Web/CustomerAuthController.php';
$authControllerSrc = file_get_contents($authControllerPath);
if ($authControllerSrc === false) {
    $fail('unable to read CustomerAuthController.php');
}

foreach ([
    'function logout',
    'function forgotPassword',
    'function resetPassword',
    'Processors\Api\Customer\Logout',
    'Processors\Api\Customer\ForgotPassword',
    'Processors\Api\Customer\ResetPassword',
] as $needle) {
    if (!str_contains($authControllerSrc, $needle)) {
        $fail("CustomerAuthController must contain: {$needle}");
    }
}

$forgotSrc = file_get_contents(dirname(__DIR__) . '/src/Processors/Api/Customer/ForgotPassword.php');
if ($forgotSrc === false || !str_contains($forgotSrc, 'HttpStatus::TOO_MANY_REQUESTS')) {
    $fail('ForgotPassword must attach HttpStatus::TOO_MANY_REQUESTS on rate-limit failures');
}

echo "OK CustomerAuthRoutesTest\n";
