<?php

/**
 * Wiring checks for delivery/payment pair validation (#374).
 *
 * Run: php tests/DeliveryPaymentAvailabilityTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertContains = static function (string $needle, string $haystack, string $case) use ($fail): void {
    if (!str_contains($haystack, $needle)) {
        $fail($case . ': missing ' . $needle);
    }
};

$root = dirname(__DIR__);

$deliveryService = file_get_contents($root . '/src/Services/Delivery/DeliveryService.php');
if ($deliveryService === false) {
    $fail('unable to read DeliveryService.php');
}
$assertContains('function getDeliveryPaymentPairError', $deliveryService, 'DeliveryService pair error helper');

$files = [
    'OrderSubmitHandler.php' => $root . '/src/Services/Order/OrderSubmitHandler.php',
    'OrderFinalizeService.php' => $root . '/src/Services/Order/OrderFinalizeService.php',
    'OrderFieldManager.php' => $root . '/src/Services/Order/OrderFieldManager.php',
    'ManagerOrderMutationService.php' => $root . '/src/Services/Order/ManagerOrderMutationService.php',
];

foreach ($files as $label => $path) {
    $src = file_get_contents($path);
    if ($src === false) {
        $fail('unable to read ' . $label);
    }
    $assertContains('getDeliveryPaymentPairError', $src, $label);
}

$order = file_get_contents($root . '/src/Controllers/Order/Order.php');
$assertContains("services->get('ms3_delivery_service')", $order, 'Order::hasPayment delegates');
$assertContains('isPaymentAvailableForDelivery', $order, 'Order::hasPayment uses DeliveryService');

fwrite(STDOUT, "OK DeliveryPaymentAvailabilityTest\n");
exit(0);
