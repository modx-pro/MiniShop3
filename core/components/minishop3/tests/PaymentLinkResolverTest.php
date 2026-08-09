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
$eligible = [$newId];

$assertSame(true, PaymentLinkResolver::isStatusEligibleForPaymentLink($newId, false, $paidId, $eligible), 'new status allows link');
$assertSame(false, PaymentLinkResolver::isStatusEligibleForPaymentLink($paidId, false, $paidId, $eligible), 'paid status blocks link');
$assertSame(false, PaymentLinkResolver::isStatusEligibleForPaymentLink(4, true, $paidId, $eligible), 'final status blocks link');
$assertSame(false, PaymentLinkResolver::isStatusEligibleForPaymentLink(5, false, $paidId, $eligible), 'other non-final status blocks link');

$assertSame([1, 2], PaymentLinkResolver::parseEligibleStatusIds('1, 2 ,2'), 'parse csv dedupes');
$assertSame([], PaymentLinkResolver::parseEligibleStatusIds(''), 'empty csv');
$assertSame([2], PaymentLinkResolver::parseEligibleStatusIds('2, draft'), 'skip non-numeric parts');

$assertSame('https://pay.example/1', PaymentLinkResolver::normalizePaymentLink('https://pay.example/1'), 'normalize link');
$assertSame(null, PaymentLinkResolver::normalizePaymentLink(''), 'empty link is null');
$assertSame(null, PaymentLinkResolver::normalizePaymentLink(null), 'null link stays null');

$snippetSrc = file_get_contents(__DIR__ . '/../elements/snippets/ms3_get_order.php');
if ($snippetSrc === false) {
    $fail('unable to read ms3_get_order.php');
}
if (!str_contains($snippetSrc, 'ms3_payment_link_resolver')) {
    $fail('ms3_get_order.php must use ms3_payment_link_resolver');
}
if (!str_contains($snippetSrc, 'PaymentLinkResolver::parseEligibleStatusIds')) {
    $fail('ms3_get_order.php must parse payStatus via PaymentLinkResolver');
}

fwrite(STDOUT, "OK PaymentLinkResolverTest\n");
exit(0);
