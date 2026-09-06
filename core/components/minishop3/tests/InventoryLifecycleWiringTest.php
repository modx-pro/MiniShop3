<?php

/**
 * Guards inventory wiring for issue #589.
 *
 * Run: php tests/InventoryLifecycleWiringTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL InventoryLifecycleWiringTest: {$message}\n");
    exit(1);
};

$srcRoot = dirname(__DIR__) . '/src';

$status = file_get_contents($srcRoot . '/Services/Order/OrderStatusService.php');
if ($status === false) {
    $fail('cannot read OrderStatusService.php');
}
if (!str_contains($status, 'persistStatusWithInventory')) {
    $fail('OrderStatusService must persist status together with inventory');
}
if (!preg_match(
    '/persistStatusWithInventory\([\s\S]*applyStatusChange[\s\S]*\$msOrder->save\(/s',
    $status
)) {
    $fail('inventory sync must run before status save');
}

$submit = file_get_contents($srcRoot . '/Services/Order/OrderSubmitHandler.php');
if ($submit === false) {
    $fail('cannot read OrderSubmitHandler.php');
}
if (!preg_match(
    '/assertOrderAvailable\([\s\S]*runWithNextNumber/s',
    $submit
)) {
    $fail('OrderSubmitHandler must assert inventory before allocating an order number');
}
if (!str_contains($submit, 'abortInventoryHold')) {
    $fail('OrderSubmitHandler must abort inventory hold when payment send() fails');
}
if (!str_contains($submit, 'failAfterNumberAllocated')) {
    $fail('OrderSubmitHandler must revert the allocated number when submit fails after reserve check');
}
if (!str_contains($status, 'undoUncommittedNewStatus')) {
    $fail('OrderStatusService must undo New + reserve when msOnChangeOrderStatus fails');
}

$registry = file_get_contents($srcRoot . '/ServiceRegistry.php');
if ($registry === false || !str_contains($registry, "'ms3_inventory'")) {
    $fail('ServiceRegistry must register ms3_inventory');
}

$factories = file_get_contents($srcRoot . '/ServiceRegistryFactories.php');
if ($factories === false || !str_contains($factories, "'ms3_inventory'")) {
    $fail('ServiceRegistryFactories must wire ms3_inventory');
}

fwrite(STDOUT, "OK InventoryLifecycleWiringTest\n");
exit(0);
