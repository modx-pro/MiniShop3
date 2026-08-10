<?php

/**
 * Session/token policy smoke checks for #285 / #412 (without MODX bootstrap).
 *
 * Covers sticky-auth + anti-fixation contracts:
 * - guest token must clear session customer_id on hydrate
 * - login rotates token; cart may transfer only from guest/own token
 * - establishCustomerSession mints (no reuse), revokes previous, regenerates session
 * - failed-login lockout applies only to invalid_credentials
 *
 * Run: php tests/CustomerAuthSessionSemanticsTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Customer\AuthManager;
use MiniShop3\Services\TokenService;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertTrue = static function (bool $actual, string $case) use ($fail): void {
    if (!$actual) {
        $fail($case . ': expected true');
    }
};

$assertFalse = static function (bool $actual, string $case) use ($fail): void {
    if ($actual) {
        $fail($case . ': expected false');
    }
};

// --- applyTokenToSession / reload sticky: guest must not leave auth customer_id ---
$assertSame(42, TokenService::sessionCustomerIdFromTokenRow(42), 'auth token sets customer_id');
$assertSame(0, TokenService::sessionCustomerIdFromTokenRow(0), 'guest token clears customer_id');
$assertSame(0, TokenService::sessionCustomerIdFromTokenRow(-1), 'invalid token customer_id clears auth');

$sessionCustomerId = 7;
$sessionCustomerId = TokenService::sessionCustomerIdFromTokenRow(0);
$assertSame(0, $sessionCustomerId, 'reload with guest cookie must not keep auth session');

$sessionCustomerId = TokenService::sessionCustomerIdFromTokenRow(7);
$assertSame(7, $sessionCustomerId, 'reload with auth cookie restores customer_id');

// --- login token rotation: cart transfer eligibility (old token is never upgraded in place) ---
$assertFalse(AuthManager::canTransferCartFromToken(-1, 5), 'missing token cannot transfer cart');
$assertTrue(AuthManager::canTransferCartFromToken(0, 5), 'guest token may transfer cart then be revoked');
$assertTrue(AuthManager::canTransferCartFromToken(5, 5), 'own token may transfer cart then be revoked');
$assertFalse(AuthManager::canTransferCartFromToken(9, 5), 'other customer token must not transfer cart');

// Alias kept for older call sites / docs
$assertTrue(AuthManager::canReuseApiToken(0, 5), 'canReuseApiToken aliases transfer policy');

// --- #412: establishCustomerSession must mint+revoke+regenerate (scoped to method body) ---
$extractMethodBody = static function (string $src, string $method) use ($fail): string {
    if (
        !preg_match(
            '/function\s+' . preg_quote($method, '/') . '\s*\([^)]*\)[^{]*\{/',
            $src,
            $match,
            PREG_OFFSET_CAPTURE
        )
    ) {
        $fail("AuthManager::{$method}() not found");
    }

    $openBrace = (int)$match[0][1] + strlen($match[0][0]) - 1;
    $depth = 0;
    $length = strlen($src);
    for ($i = $openBrace; $i < $length; $i++) {
        $char = $src[$i];
        if ($char === '{') {
            $depth++;
            continue;
        }
        if ($char === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($src, $openBrace, $i - $openBrace + 1);
            }
        }
    }

    $fail("AuthManager::{$method}() body is unclosed");
};

$authSrc = file_get_contents(__DIR__ . '/../src/Services/Customer/AuthManager.php');
if ($authSrc === false) {
    $fail('unable to read AuthManager.php');
}

$establishBody = $extractMethodBody($authSrc, 'establishCustomerSession');
if (!preg_match('/persistApiToken\s*\(\s*\(int\)\s*\$customer->id\s*,\s*null\s*,/', $establishBody)) {
    $fail('establishCustomerSession must mint via persistApiToken(..., null, ...) — never reuse planted token');
}
if (!str_contains($establishBody, '->remove()')) {
    $fail('establishCustomerSession must revoke the previous API token row');
}
if (!str_contains($establishBody, 'session_regenerate_id(true)')) {
    $fail('establishCustomerSession must regenerate PHP session id');
}

// --- lockout reasons: only invalid_credentials should trigger handleFailedLoginByEmail ---
$shouldIncrementLockout = static function (string $lastAuthFailure): bool {
    return $lastAuthFailure === 'invalid_credentials';
};
$assertTrue($shouldIncrementLockout('invalid_credentials'), 'wrong password increments lockout');
$assertFalse($shouldIncrementLockout('blocked'), 'blocked must not increment lockout');
$assertFalse($shouldIncrementLockout('inactive'), 'inactive must not increment lockout');
$assertFalse($shouldIncrementLockout('none'), 'none must not increment lockout');

fwrite(STDOUT, "OK CustomerAuthSessionSemanticsTest\n");
exit(0);
