<?php

/**
 * CORS config normalization guards (issue #335).
 *
 * Run: php tests/CorsConfigNormalizeTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Utils\CorsConfig;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$default = CorsConfig::normalizeCorsConfig([
    'allowed_origins' => '',
    'allow_credentials' => true,
]);

if ($default['allowed_origins'] !== []) {
    $fail('empty default origins must be []');
}
if ($default['allow_credentials'] !== true) {
    $fail('empty default may keep allow_credentials true');
}

$wildcardWithCredentials = CorsConfig::normalizeCorsConfig([
    'allowed_origins' => '*',
    'allow_credentials' => true,
]);

if ($wildcardWithCredentials['allowed_origins'] !== ['*']) {
    $fail('explicit * must remain in origins list');
}
if ($wildcardWithCredentials['allow_credentials'] !== false) {
    $fail('* + allow_credentials must normalize credentials to false');
}

$allowlist = CorsConfig::normalizeCorsConfig([
    'allowed_origins' => 'https://shop.example.com, https://app.example.com',
    'allow_credentials' => true,
]);

if ($allowlist['allowed_origins'] !== ['https://shop.example.com', 'https://app.example.com']) {
    $fail('comma-separated allowlist must parse');
}
if ($allowlist['allow_credentials'] !== true) {
    $fail('explicit allowlist must keep allow_credentials true');
}

$runtimeGuard = CorsConfig::normalizeCorsConfig([
    'allowed_origins' => ['*'],
    'allow_credentials' => true,
]);
if (CorsConfig::hasWildcardOrigin($runtimeGuard['allowed_origins']) && $runtimeGuard['allow_credentials']) {
    $fail('runtime must not allow * with credentials after normalize');
}

$middleware = file_get_contents(dirname(__DIR__) . '/src/Middleware/CorsMiddleware.php');
if ($middleware === false) {
    $fail('cannot read CorsMiddleware.php');
}
if (!str_contains($middleware, 'CorsConfig::normalizeCorsConfig')) {
    $fail('CorsMiddleware must use CorsConfig::normalizeCorsConfig');
}
if (preg_match('/in_array\s*\(\s*[\'"]\*[\'"]\s*,\s*\$this->allowedOrigins\s*\)[\s\S]*Access-Control-Allow-Origin:\s*[\'"]\s*\.\s*\$origin/s', $middleware) === 1) {
    $fail('CorsMiddleware must not reflect origin when wildcard is configured');
}

$webRoutes = file_get_contents(dirname(__DIR__) . '/config/routes/web.php');
if ($webRoutes === false) {
    $fail('cannot read web.php');
}
if (str_contains($webRoutes, "getOption('ms3_cors_allowed_origins', null, ['*']")) {
    $fail('web.php must not default ms3_cors_allowed_origins to *');
}

// #576: wildcard host patterns must not treat dots as "any char"
$pattern = 'https://*.example.com';
if (!CorsConfig::originMatchesWildcardPattern('https://shop.example.com', $pattern)) {
    $fail('https://*.example.com must allow https://shop.example.com');
}
if (CorsConfig::originMatchesWildcardPattern('https://shop.exampleXcom', $pattern)) {
    $fail('https://*.example.com must reject https://shop.exampleXcom');
}
if (CorsConfig::originMatchesWildcardPattern('https://evil.example.com.attacker.tld', $pattern)) {
    $fail('https://*.example.com must reject nested attacker tld');
}
if (CorsConfig::originMatchesWildcardPattern('https://foo.bar.example.com', $pattern)) {
    $fail('single-label * must reject multi-label subdomain');
}
if (!CorsConfig::isOriginAllowed('https://shop.example.com', [$pattern])) {
    $fail('isOriginAllowed must accept matching wildcard origin');
}
if (CorsConfig::isOriginAllowed('https://shop.exampleXcom', [$pattern])) {
    $fail('isOriginAllowed must reject spoofed wildcard origin');
}
if (!CorsConfig::isOriginAllowed('https://exact.example.com', ['https://exact.example.com'])) {
    $fail('exact origin allowlist must still work');
}

if (!str_contains($middleware, 'CorsConfig::isOriginAllowed')) {
    $fail('CorsMiddleware must delegate origin match to CorsConfig::isOriginAllowed');
}
if (str_contains($middleware, "str_replace('*', '.*'")) {
    $fail('CorsMiddleware must not use unquoted .* wildcard replacement');
}

fwrite(STDOUT, "OK CorsConfigNormalizeTest\n");
exit(0);
