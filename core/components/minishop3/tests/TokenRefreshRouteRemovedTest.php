<?php

/**
 * Контракт #334: POST /customer/token/refresh не зарегистрирован
 * и не отдаёт ложный success-stub.
 *
 * Запуск: php tests/TokenRefreshRouteRemovedTest.php
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

if (str_contains($webRoutes, 'token/refresh')) {
    $fail('config/routes/web.php must not register token/refresh');
}

if (str_contains($tokenMiddleware, 'token/refresh')) {
    $fail('TokenMiddleware publicRoutes must not include token/refresh');
}

if (str_contains($webRoutes, 'Customer token/refresh endpoint - not implemented yet')) {
    $fail('web.php must not contain the customer token/refresh success stub');
}

fwrite(STDOUT, "OK TokenRefreshRouteRemovedTest\n");
exit(0);
