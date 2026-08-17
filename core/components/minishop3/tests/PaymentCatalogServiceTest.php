<?php

/**
 * Allowlist + helpers for public payment catalog (#569).
 *
 * Run: php tests/PaymentCatalogServiceTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Payment\PaymentCatalogService;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$fields = PaymentCatalogService::PUBLIC_FIELDS;
$required = ['id', 'name', 'description', 'price', 'logo', 'position', 'active'];
foreach ($required as $field) {
    if (!in_array($field, $fields, true)) {
        $fail("PUBLIC_FIELDS must contain \"{$field}\"");
    }
}

$forbidden = ['properties', 'class', 'payment_link', 'validation_rules'];
foreach ($forbidden as $field) {
    if (in_array($field, $fields, true)) {
        $fail("PUBLIC_FIELDS must not contain \"{$field}\"");
    }
}

$projected = PaymentCatalogService::projectPublicFields([
    'id' => 2,
    'name' => 'Card',
    'description' => 'Online',
    'price' => '0%',
    'logo' => '/card.png',
    'position' => 1,
    'active' => 1,
    'properties' => ['merchant' => 'secret'],
    'class' => 'MiniShop3\\Controllers\\Payment\\Payment',
    'payment_link' => 'https://gateway.example/pay',
]);

foreach ($forbidden as $field) {
    if (array_key_exists($field, $projected)) {
        $fail("projected payload must not contain \"{$field}\"");
    }
}

if ($projected['id'] !== 2 || $projected['active'] !== true) {
    $fail('projected field casting failed');
}

$serviceSrc = file_get_contents(__DIR__ . '/../src/Services/Payment/PaymentCatalogService.php');
if ($serviceSrc === false) {
    $fail('cannot read PaymentCatalogService.php');
}
if (str_contains($serviceSrc, 'toArray(')) {
    $fail('PaymentCatalogService must not call toArray()');
}
if (!str_contains($serviceSrc, 'delivery_id')) {
    $fail('payment list must support delivery_id filter');
}
if (!str_contains($serviceSrc, 'msDeliveryMember')) {
    $fail('delivery_id filter must use msDeliveryMember');
}
if (!str_contains($serviceSrc, 'isActiveDelivery')) {
    $fail('delivery_id filter must require active delivery');
}
if (str_contains($serviceSrc, 'send(') || str_contains($serviceSrc, 'getPaymentLink')) {
    $fail('payment catalog must not generate live payment links');
}

fwrite(STDOUT, "OK PaymentCatalogServiceTest\n");
exit(0);
