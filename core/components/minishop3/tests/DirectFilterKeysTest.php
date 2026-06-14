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

$assertSame(
    ['query', 'status_id', 'delivery_id', 'payment_id', 'context_key', 'createdon_from', 'createdon_to'],
    OrdersController::getDirectFilterKeys(),
    'orders direct filter keys'
);

$assertSame(
    ['query'],
    CustomersController::getDirectFilterKeys(),
    'customers direct filter keys'
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
