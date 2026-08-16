<?php

/**
 * Static checks for CheckoutMemberMap / CatalogLexicon (#568/#569).
 *
 * Run: php tests/CheckoutMemberMapTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Catalog\CatalogLexicon;
use MiniShop3\Services\Catalog\CheckoutMemberMap;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$mapSrc = file_get_contents(__DIR__ . '/../src/Services/Catalog/CheckoutMemberMap.php');
$lexSrc = file_get_contents(__DIR__ . '/../src/Services/Catalog/CatalogLexicon.php');
$deliverySrc = file_get_contents(__DIR__ . '/../src/Services/Delivery/DeliveryCatalogService.php');
$paymentSrc = file_get_contents(__DIR__ . '/../src/Services/Payment/PaymentCatalogService.php');

if ($mapSrc === false || $lexSrc === false || $deliverySrc === false || $paymentSrc === false) {
    $fail('unable to read source files');
}

foreach (['paymentIdsByDelivery', 'deliveryIdsByPayment', 'isActiveDelivery'] as $method) {
    if (!str_contains($mapSrc, "function {$method}")) {
        $fail("CheckoutMemberMap missing {$method}");
    }
}

if (!str_contains($mapSrc, 'Delivery.active = 1') || !str_contains($mapSrc, 'Payment.active = 1')) {
    $fail('member map must join only active delivery/payment rows');
}

if (!str_contains($lexSrc, 'function translateMs3Name')) {
    $fail('CatalogLexicon must expose translateMs3Name');
}

if (!str_contains($deliverySrc, 'paymentIdsByDelivery()')) {
    $fail('delivery list must preload paymentIdsByDelivery (no N+1)');
}
if (preg_match('/function formatItem.*?activePaymentIdsForDelivery/s', $deliverySrc)) {
    $fail('delivery formatItem must not query per row');
}

if (!str_contains($paymentSrc, 'isActiveDelivery')) {
    $fail('payment list must gate delivery_id via isActiveDelivery');
}

// CatalogLexicon is static — callable without MODX for signature check
$ref = new ReflectionMethod(CatalogLexicon::class, 'translateMs3Name');
if ($ref->getNumberOfParameters() !== 2) {
    $fail('translateMs3Name must accept modX + name');
}

$refMap = new ReflectionClass(CheckoutMemberMap::class);
if (!$refMap->hasMethod('isActiveDelivery')) {
    $fail('CheckoutMemberMap reflection missing isActiveDelivery');
}

fwrite(STDOUT, "OK CheckoutMemberMapTest\n");
exit(0);
