<?php

/**
 * Static checks for manager grid direct filter keys (without MODX).
 *
 * Run: php tests/DirectFilterKeysTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Controllers\Api\Manager\CustomersController;
use MiniShop3\Controllers\Api\Manager\GridConfigController;
use MiniShop3\Controllers\Api\Manager\OrdersController;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function (array $expected, array $actual, string $label) use ($fail): void {
    if ($actual !== $expected) {
        $fail(sprintf(
            "%s:\nexpected: %s\nactual:   %s",
            $label,
            json_encode($expected, JSON_UNESCAPED_SLASHES),
            json_encode($actual, JSON_UNESCAPED_SLASHES)
        ));
    }
};

$assertKeysInList = static function (array $keys, array $list, string $label) use ($fail): void {
    foreach ($keys as $key) {
        if (!in_array($key, $list, true)) {
            $fail("{$label}: key {$key} missing from DIRECT_FILTER_KEYS");
        }
    }
};

$ordersReflection = new ReflectionClass(OrdersController::class);
$ordersKeys = $ordersReflection->getConstant('DIRECT_FILTER_KEYS');
$ordersFieldMap = $ordersReflection->getConstant('DIRECT_FILTER_FIELD_MAP');

$assertSame($ordersKeys, OrdersController::getDirectFilterKeys(), 'orders getter returns DIRECT_FILTER_KEYS');
$assertKeysInList(array_keys($ordersFieldMap), $ordersKeys, 'orders FIELD_MAP');

foreach (['query', 'createdon_from', 'createdon_to'] as $separateKey) {
    if (array_key_exists($separateKey, $ordersFieldMap)) {
        $fail("orders key {$separateKey} must not be in DIRECT_FILTER_FIELD_MAP");
    }
}

$customersReflection = new ReflectionClass(CustomersController::class);
$assertSame(
    $customersReflection->getConstant('DIRECT_FILTER_KEYS'),
    CustomersController::getDirectFilterKeys(),
    'customers getter returns DIRECT_FILTER_KEYS'
);

$gridConfig = new ReflectionClass(GridConfigController::class);
$providers = $gridConfig->getConstant('DIRECT_FILTER_KEY_PROVIDERS');

foreach (['orders' => OrdersController::class, 'customers' => CustomersController::class] as $gridKey => $provider) {
    if (($providers[$gridKey] ?? null) !== $provider) {
        $fail("grid-config provider missing for {$gridKey}");
    }
}

fwrite(STDOUT, "OK DirectFilterKeysTest\n");
exit(0);
