<?php

/**
 * Allowlist + helpers for public delivery catalog (#568).
 *
 * Run: php tests/DeliveryCatalogServiceTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Catalog\CatalogQuery;
use MiniShop3\Services\Delivery\DeliveryCatalogService;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$fields = DeliveryCatalogService::PUBLIC_FIELDS;
$required = [
    'id',
    'name',
    'description',
    'price',
    'logo',
    'position',
    'active',
    'free_delivery_amount',
];
foreach ($required as $field) {
    if (!in_array($field, $fields, true)) {
        $fail("PUBLIC_FIELDS must contain \"{$field}\"");
    }
}

$forbidden = ['properties', 'class', 'validation_rules', 'payment_link'];
foreach ($forbidden as $field) {
    if (in_array($field, $fields, true)) {
        $fail("PUBLIC_FIELDS must not contain \"{$field}\"");
    }
}

$projected = DeliveryCatalogService::projectPublicFields([
    'id' => 7,
    'name' => 'Courier',
    'description' => 'City',
    'price' => '300',
    'weight_price' => '10',
    'distance_price' => 0,
    'logo' => '/a.png',
    'position' => 2,
    'active' => 1,
    'free_delivery_amount' => 5000,
    'properties' => ['api_key' => 'secret'],
    'class' => 'MiniShop3\\Controllers\\Delivery\\Delivery',
    'validation_rules' => '{"phone":"required"}',
]);

foreach ($forbidden as $field) {
    if (array_key_exists($field, $projected)) {
        $fail("projected payload must not contain \"{$field}\"");
    }
}

if ($projected['id'] !== 7 || $projected['active'] !== true || $projected['position'] !== 2) {
    $fail('projected field casting failed');
}

if (DeliveryCatalogService::extractRequiredFieldNames('{"phone":"required|numeric","city":"nullable"}') !== ['phone']) {
    $fail('extractRequiredFieldNames must return required field names only');
}

if (DeliveryCatalogService::extractRequiredFieldNames(['email' => 'required|email']) !== ['email']) {
    $fail('extractRequiredFieldNames must accept array rules');
}

if (DeliveryCatalogService::extractRequiredFieldNames(null) !== []) {
    $fail('extractRequiredFieldNames null → []');
}

if (CatalogQuery::resolveBool([], 'include_payments', true) !== true) {
    $fail('resolveBool default true');
}
if (CatalogQuery::resolveBool(['include_payments' => '0'], 'include_payments', true) !== false) {
    $fail('resolveBool 0 → false');
}

$serviceSrc = file_get_contents(__DIR__ . '/../src/Services/Delivery/DeliveryCatalogService.php');
if ($serviceSrc === false) {
    $fail('cannot read DeliveryCatalogService.php');
}
if (str_contains($serviceSrc, 'toArray(')) {
    $fail('DeliveryCatalogService must not call toArray()');
}
if (!str_contains($serviceSrc, "'active' => 1")) {
    $fail('list/get must scope active=1');
}

fwrite(STDOUT, "OK DeliveryCatalogServiceTest\n");
exit(0);
