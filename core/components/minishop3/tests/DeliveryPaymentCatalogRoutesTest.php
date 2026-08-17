<?php

/**
 * Smoke: public delivery/payment routes + DI (#568, #569).
 *
 * Run: php tests/DeliveryPaymentCatalogRoutesTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$webRoutes = file_get_contents(__DIR__ . '/../config/routes/web.php');
$deliveryController = file_get_contents(__DIR__ . '/../src/Controllers/Api/Web/DeliveryController.php');
$paymentController = file_get_contents(__DIR__ . '/../src/Controllers/Api/Web/PaymentController.php');
$registry = file_get_contents(__DIR__ . '/../src/ServiceRegistry.php');
$factories = file_get_contents(__DIR__ . '/../src/ServiceRegistryFactories.php');

if (
    $webRoutes === false
    || $deliveryController === false
    || $paymentController === false
    || $registry === false
    || $factories === false
) {
    $fail('unable to read source files');
}

foreach (
    [
        "group('/delivery'",
        "group('/payment'",
        'DeliveryController',
        'PaymentController',
    ] as $needle
) {
    if (!str_contains($webRoutes, $needle)) {
        $fail("web.php missing: {$needle}");
    }
}

foreach (['function get(', 'function getList('] as $method) {
    if (!str_contains($deliveryController, $method)) {
        $fail("DeliveryController missing {$method}");
    }
    if (!str_contains($paymentController, $method)) {
        $fail("PaymentController missing {$method}");
    }
}

if (!str_contains($deliveryController, 'ms3_delivery_catalog')) {
    $fail('DeliveryController must resolve ms3_delivery_catalog');
}
if (!str_contains($paymentController, 'ms3_payment_catalog')) {
    $fail('PaymentController must resolve ms3_payment_catalog');
}

foreach (['ms3_delivery_catalog', 'ms3_payment_catalog'] as $key) {
    if (!str_contains($registry, "'{$key}'")) {
        $fail("ServiceRegistry missing {$key}");
    }
    if (!str_contains($factories, "'{$key}'")) {
        $fail("ServiceRegistryFactories missing {$key}");
    }
}

// Public discovery groups must not attach TokenMiddleware (unlike /order).
if (preg_match(
    "/group\\('\\/delivery'\\s*,\\s*function.*?\\}\\s*,\\s*\\[\\s*\\\$tokenMiddleware/s",
    $webRoutes
)) {
    $fail('delivery group must not use TokenMiddleware');
}
if (preg_match(
    "/group\\('\\/payment'\\s*,\\s*function.*?\\}\\s*,\\s*\\[\\s*\\\$tokenMiddleware/s",
    $webRoutes
)) {
    $fail('payment group must not use TokenMiddleware');
}

fwrite(STDOUT, "OK DeliveryPaymentCatalogRoutesTest\n");
exit(0);
