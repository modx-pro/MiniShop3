<?php

/**
 * #571 / #334: POST /customer/token/refresh is a real rotate endpoint (not a success stub).
 * GET /customer/me is registered and not public.
 *
 * Run: php tests/TokenRefreshRouteRemovedTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$base = dirname(__DIR__);
$webRoutes = file_get_contents($base . '/config/routes/web.php');
if ($webRoutes === false) {
    $fail('cannot read config/routes/web.php');
}

$tokenMiddleware = file_get_contents($base . '/src/Middleware/TokenMiddleware.php');
if ($tokenMiddleware === false) {
    $fail('cannot read src/Middleware/TokenMiddleware.php');
}

$authController = file_get_contents($base . '/src/Controllers/Api/Web/CustomerAuthController.php');
if ($authController === false) {
    $fail('cannot read CustomerAuthController.php');
}

$tokenService = file_get_contents($base . '/src/Services/TokenService.php');
if ($tokenService === false) {
    $fail('cannot read TokenService.php');
}

if (!str_contains($webRoutes, "post('/token/refresh'")) {
    $fail('config/routes/web.php must register POST /customer/token/refresh');
}

if (!str_contains($webRoutes, "get('/me'")) {
    $fail('config/routes/web.php must register GET /customer/me');
}

if (str_contains($webRoutes, 'Customer token/refresh endpoint - not implemented yet')) {
    $fail('web.php must not contain the customer token/refresh success stub (#334)');
}

if (str_contains($webRoutes, "success([], 'Token refresh not implemented yet')")) {
    $fail('web.php must not return false success stub for token/refresh (#334)');
}

if (str_contains($tokenMiddleware, '/api/v1/customer/token/refresh')) {
    $fail('TokenMiddleware publicRoutes must not include token/refresh');
}

if (str_contains($tokenMiddleware, '/api/v1/customer/me')) {
    $fail('TokenMiddleware publicRoutes must not include /customer/me');
}

if (!str_contains($authController, 'function refreshToken')) {
    $fail('CustomerAuthController must implement refreshToken()');
}

if (!str_contains($authController, 'function me(')) {
    $fail('CustomerAuthController must implement me()');
}

if (!str_contains($tokenService, 'function rotateApiToken')) {
    $fail('TokenService must implement rotateApiToken()');
}

if (!str_contains($tokenService, 'function resolveTokenFromRequest')) {
    $fail('TokenService must implement resolveTokenFromRequest() for Bearer bind');
}

fwrite(STDOUT, "OK TokenRefreshRouteRemovedTest\n");
exit(0);
