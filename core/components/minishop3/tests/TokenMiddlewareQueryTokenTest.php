<?php

/**
 * #576: API session token must not be accepted from the query string.
 *
 * Run: php tests/TokenMiddlewareQueryTokenTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$src = file_get_contents(__DIR__ . '/../src/Middleware/TokenMiddleware.php');
if ($src === false) {
    $fail('unable to read TokenMiddleware.php');
}

if (!str_contains($src, 'stripQueryStringApiTokens')) {
    $fail('TokenMiddleware must strip query-string API tokens');
}

if (!preg_match('/function resolveToken\(\): string\s*\{([\s\S]*?)\n    \}/', $src, $resolveBody)) {
    $fail('resolveToken() body not found');
}

$resolve = $resolveBody[1];

if (!str_contains($resolve, 'HTTP_AUTHORIZATION') || !str_contains($resolve, 'Bearer ')) {
    $fail('resolveToken must accept Authorization Bearer');
}
if (!str_contains($resolve, 'HTTP_MS3TOKEN')) {
    $fail('resolveToken must accept HTTP_MS3TOKEN');
}
if (!str_contains($resolve, 'CookieHelper::getTokenFromCookie()')) {
    $fail('resolveToken must accept cookie via CookieHelper');
}
if (!str_contains($resolve, "\$_SESSION['ms3']['customer_token']")) {
    $fail('resolveToken must fall back to session cache');
}
if (str_contains($resolve, '$_REQUEST') || str_contains($resolve, '$_GET')) {
    $fail('resolveToken must not read $_REQUEST/$_GET (query is not a credential source)');
}
if (str_contains($resolve, "['token']")) {
    $fail('resolveToken must not read legacy token param');
}

if (!preg_match('/query[- ]string/i', $src)) {
    $fail('TokenMiddleware docs must mention query-string rejection');
}

fwrite(STDOUT, "OK TokenMiddlewareQueryTokenTest\n");
exit(0);
