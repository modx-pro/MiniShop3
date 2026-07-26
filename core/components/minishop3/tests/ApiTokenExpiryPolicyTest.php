<?php

/**
 * Static checks for API token expiry policy (without MODX).
 *
 * Expired TYPE_API tokens must be rejected/removed — never silently extended
 * while keeping the same token string (issue #371).
 *
 * Run: php tests/ApiTokenExpiryPolicyTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Utils\ApiTokenExpiry;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$now = 1_700_000_000;

if (!ApiTokenExpiry::isExpiresAtBefore(date('Y-m-d H:i:s', $now - 1), $now)) {
    $fail('past expires_at must be expired');
}

if (ApiTokenExpiry::isExpiresAtBefore(date('Y-m-d H:i:s', $now + 1), $now)) {
    $fail('future expires_at must not be expired');
}

if (ApiTokenExpiry::isExpiresAtBefore(date('Y-m-d H:i:s', $now), $now)) {
    $fail('expires_at equal to now must not be expired (strict <)');
}

if (!ApiTokenExpiry::isExpiresAtBefore('not-a-date', $now)) {
    $fail('unparseable expires_at must be treated as expired');
}

$middleware = file_get_contents(dirname(__DIR__) . '/src/Middleware/TokenMiddleware.php');
if ($middleware === false) {
    $fail('cannot read TokenMiddleware.php');
}
if (str_contains($middleware, 'Auto-renew expired token')) {
    $fail('TokenMiddleware must not auto-renew expired tokens');
}
if (!str_contains($middleware, 'ms3_err_token_expired')) {
    $fail('TokenMiddleware must reject expired tokens with ms3_err_token_expired');
}
if (!str_contains($middleware, 'clearClientTokenState')) {
    $fail('TokenMiddleware must clear full client token state on reject');
}

$tokenServiceSource = file_get_contents(dirname(__DIR__) . '/src/Services/TokenService.php');
if ($tokenServiceSource === false) {
    $fail('cannot read TokenService.php');
}
if (str_contains($tokenServiceSource, 'Auto-renew expired token')) {
    $fail('TokenService must not auto-renew expired tokens');
}
if (!str_contains($tokenServiceSource, 'function resolveApiToken')) {
    $fail('TokenService must expose resolveApiToken as the canonical expiry gate');
}

fwrite(STDOUT, "OK ApiTokenExpiryPolicyTest\n");
exit(0);
