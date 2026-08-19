<?php

/**
 * Guards payment lifecycle wiring for issue #590.
 *
 * Run: php tests/PaymentLifecycleWiringTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL PaymentLifecycleWiringTest: {$message}\n");
    exit(1);
};

$srcRoot = dirname(__DIR__) . '/src';
$routes = file_get_contents(dirname(__DIR__) . '/config/routes/web.php');
$payment = file_get_contents($srcRoot . '/Controllers/Payment/Payment.php');
$ordersPage = file_get_contents($srcRoot . '/Services/Customer/OrdersPageService.php');
$snippet = file_get_contents(dirname(__DIR__) . '/elements/snippets/ms3_get_order.php');
$registry = file_get_contents($srcRoot . '/ServiceRegistry.php');
$factories = file_get_contents($srcRoot . '/ServiceRegistryFactories.php');

if (
    $routes === false
    || $payment === false
    || $ordersPage === false
    || $snippet === false
    || $registry === false
    || $factories === false
) {
    $fail('cannot read source files');
}

if (!str_contains($routes, "post('/webhook/{payment_method_id}'")) {
    $fail('web.php must register POST /payment/webhook/{payment_method_id}');
}
if (!str_contains($routes, 'PaymentWebhookController')) {
    $fail('web.php must dispatch PaymentWebhookController');
}
if (!str_contains($registry, "'ms3_payment_lifecycle'")) {
    $fail('ServiceRegistry must register ms3_payment_lifecycle');
}
if (!str_contains($factories, "'ms3_payment_lifecycle'")) {
    $fail('ServiceRegistryFactories must wire ms3_payment_lifecycle');
}
if (str_contains($payment, "set('status_id'")) {
    $fail('Payment docblock must not write status_id directly');
}
if (!str_contains($payment, 'PaymentLifecycleService')) {
    $fail('Payment examples must use PaymentLifecycleService');
}
if (str_contains($ordersPage, '$payment->toArray()')) {
    $fail('OrdersPageService must not expose payment toArray()');
}
if (str_contains($snippet, '$payment->toArray()')) {
    $fail('ms3_get_order must not expose payment toArray()');
}
$paymentService = file_get_contents($srcRoot . '/Services/Payment/PaymentService.php');
$notification = file_get_contents($srcRoot . '/Notifications/Notification.php');
if ($paymentService === false || $notification === false) {
    $fail('cannot read payment service / notification');
}
if (!str_contains($paymentService, 'recordAttemptFromSend')) {
    $fail('PaymentService must record an attempt after a successful send()');
}
if (str_contains($notification, '$payment->toArray()')) {
    $fail('Notification must not expose payment toArray()');
}

fwrite(STDOUT, "OK PaymentLifecycleWiringTest\n");
exit(0);
