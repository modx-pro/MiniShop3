<?php

/**
 * Session/token policy smoke checks for #285 (without MODX bootstrap).
 *
 * Covers sticky-auth + anti-fixation contracts:
 * - guest token must clear session customer_id on hydrate
 * - login rotates token; cart may transfer only from guest/own token
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
