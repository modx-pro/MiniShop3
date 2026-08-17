<?php

/**
 * #576: API session token must not be accepted from the query string.
 *
 * After #571, resolution lives in TokenService::resolveTokenFromRequest();
 * TokenMiddleware still strips query tokens before resolve.
 *
 * Run: php tests/TokenMiddlewareQueryTokenTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$middleware = file_get_contents(__DIR__ . '/../src/Middleware/TokenMiddleware.php');
$tokenService = file_get_contents(__DIR__ . '/../src/Services/TokenService.php');
if ($middleware === false || $tokenService === false) {
    $fail('unable to read TokenMiddleware.php / TokenService.php');
}

if (!str_contains($middleware, 'stripQueryStringApiTokens')) {
    $fail('TokenMiddleware must strip query-string API tokens');
}

if (!str_contains($middleware, 'TokenService::resolveTokenFromRequest()')) {
    $fail('TokenMiddleware must resolve via TokenService::resolveTokenFromRequest()');
}

if (!preg_match(
    '/function resolveTokenFromRequest\(\): string\s*\{([\s\S]*?)\n    \}/',
    $tokenService,
    $resolveBody
)) {
    $fail('TokenService::resolveTokenFromRequest() body not found');
}

$resolve = $resolveBody[1];

if (!str_contains($resolve, 'resolveBearerOrLegacyHeader')
    && (!str_contains($resolve, 'HTTP_AUTHORIZATION') || !str_contains($resolve, 'Bearer '))
) {
    $fail('resolveTokenFromRequest must accept Authorization Bearer');
}
if (!str_contains($resolve, 'resolveBearerOrLegacyHeader') && !str_contains($resolve, 'HTTP_MS3TOKEN')) {
    $fail('resolveTokenFromRequest must accept HTTP_MS3TOKEN');
}
if (!str_contains($resolve, 'CookieHelper::getTokenFromCookie()')) {
    $fail('resolveTokenFromRequest must accept cookie via CookieHelper');
}
if (!str_contains($resolve, "\$_SESSION['ms3']['customer_token']")) {
    $fail('resolveTokenFromRequest must fall back to session cache');
}
if (str_contains($resolve, '$_GET')) {
    $fail('resolveTokenFromRequest must not read $_GET (query is not a credential source)');
}
if (str_contains($resolve, "['token']")) {
    $fail('resolveTokenFromRequest must not read legacy token param');
}

if (!preg_match('/query[- ]string/i', $middleware)) {
    $fail('TokenMiddleware docs must mention query-string rejection');
}

fwrite(STDOUT, "OK TokenMiddlewareQueryTokenTest\n");
exit(0);
