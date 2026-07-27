<?php

/**
 * Static checks for payment link status gating (without MODX).
 *
 * Run: php tests/PaymentLinkResolverTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Payment\PaymentLinkResolver;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$paidId = 3;
$newId = 2;

$assertSame(true, PaymentLinkResolver::shouldResolvePaymentLink($newId, false, $paidId, $newId), 'new status allows link');
$assertSame(false, PaymentLinkResolver::shouldResolvePaymentLink($paidId, false, $paidId, $newId), 'paid status blocks link');
$assertSame(false, PaymentLinkResolver::shouldResolvePaymentLink(4, true, $paidId, $newId), 'final status blocks link');
$assertSame(false, PaymentLinkResolver::shouldResolvePaymentLink(5, false, $paidId, $newId), 'other non-final status blocks link');

fwrite(STDOUT, "OK PaymentLinkResolverTest\n");
exit(0);
