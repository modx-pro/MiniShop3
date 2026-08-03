<?php

/**
 * Regression #408: cart/get must not be public (guest GET auto-mints);
 * publicRoutes prefixes stay in sync; ApiClient token keys stay machine-stable.
 *
 * Run: php tests/TokenMiddlewarePublicRoutesTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$middlewareSrc = file_get_contents(__DIR__ . '/../src/Middleware/TokenMiddleware.php');
$cartSrc = file_get_contents(__DIR__ . '/../src/Controllers/Api/Web/CartController.php');

if ($middlewareSrc === false || $cartSrc === false) {
    $fail('unable to read source files');
}

if (!preg_match('/private array \$publicRoutes\s*=\s*\[(.*?)\];/s', $middlewareSrc, $match)) {
    $fail('publicRoutes array not found');
}

if (!preg_match_all("/'([^']+)'/", $match[1], $keys)) {
    $fail('no publicRoutes entries');
}

$publicRoutes = $keys[1];

if (in_array('/api/v1/cart/get', $publicRoutes, true)) {
    $fail('cart/get must not be public — otherwise guest GET never auto-mints a token (#408)');
}

foreach (
    [
        '/api/v1/product/get/',
        '/api/v1/product/list',
        '/api/v1/customer/token/get',
        '/api/v1/health',
    ] as $prefix
) {
    if (!in_array($prefix, $publicRoutes, true)) {
        $fail("publicRoutes missing expected prefix: {$prefix}");
    }
}

if (
    str_contains($cartSrc, "'Token is required'")
    || str_contains($cartSrc, '"Token is required"')
) {
    $fail('CartController must not hardcode Token is required — use lexicon');
}

if (!str_contains($cartSrc, "lexicon('ms3_customer_err_token_required')")) {
    $fail('CartController must resolve ms3_customer_err_token_required');
}

// ApiClient.isTokenError() matches raw keys in message — do not lexicon()-resolve them
if (str_contains($middlewareSrc, "lexicon('ms3_err_token_invalid')")) {
    $fail('TokenMiddleware must return raw key ms3_err_token_invalid (ApiClient contract)');
}

if (!str_contains($middlewareSrc, "Response::error('ms3_err_token_invalid'")) {
    $fail('TokenMiddleware must Response::error with raw ms3_err_token_invalid');
}

if (!str_contains($middlewareSrc, "Response::error('ms3_customer_err_token_create'")) {
    $fail('TokenMiddleware mint failure must use ms3_customer_err_token_create');
}

fwrite(STDOUT, "OK TokenMiddlewarePublicRoutesTest\n");
exit(0);
