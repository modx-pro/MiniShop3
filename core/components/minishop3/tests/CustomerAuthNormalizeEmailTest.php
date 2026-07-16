<?php

/**
 * Static checks for customer auth email normalization (without MODX bootstrap).
 *
 * Run: php tests/CustomerAuthNormalizeEmailTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Controllers\Auth\PasswordAuthProvider;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertSame('user@example.com', PasswordAuthProvider::normalizeEmail('User@Example.COM'), 'lowercases email');
$assertSame('user@example.com', PasswordAuthProvider::normalizeEmail('  user@example.com  '), 'trims email');
$assertSame('', PasswordAuthProvider::normalizeEmail('   '), 'blank becomes empty');

fwrite(STDOUT, "OK CustomerAuthNormalizeEmailTest\n");
exit(0);
