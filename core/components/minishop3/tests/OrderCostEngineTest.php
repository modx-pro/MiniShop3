<?php

/**
 * Shared order cost engine (#366) — no MODX/MySQL.
 *
 * Run: php tests/OrderCostEngineTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Order\OrderCostEngine;
use MiniShop3\Utils\PriceAdjustment;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertFloat = static function (float $expected, float $actual, string $case) use ($fail): void {
    if (abs($expected - $actual) > 0.000001) {
        $fail($case . ': expected ' . $expected . ', got ' . $actual);
    }
};

$assertFloat(150.0, OrderCostEngine::paymentCommissionBase(100.0, 50.0), 'payment base cart+delivery');
$assertFloat(154.5, OrderCostEngine::calculatePaymentTotal(150.0, 4.5), 'payment total');

// #372: payment % base = cart + delivery (1000 + 500 → 3% fee = 45, not 30 on cart-only)
$issue372Base = OrderCostEngine::paymentCommissionBase(1000.0, 500.0);
$assertFloat(1500.0, $issue372Base, '#372 commission base');
$assertFloat(45.0, PriceAdjustment::calculate($issue372Base, '3%'), '#372 payment surcharge on cart+delivery');

$paths = [
    'ManagerOrderCostRecalculator.php' => [
        'OrderCostEngine::paymentCommissionBase',
        'OrderCostEngine::composeBreakdown',
        'OrderCostEngine::calculateDefaultDeliveryCost',
        'OrderCostEngine::calculatePaymentSurcharge',
    ],
    'OrderCostCalculator.php' => [
        'OrderCostEngine::paymentCommissionBase',
    ],
    'Delivery.php' => [
        'OrderCostEngine::calculateDefaultDeliveryCost',
    ],
    'Payment.php' => [
        'OrderCostEngine::calculatePaymentSurcharge',
        'OrderCostEngine::calculatePaymentTotal',
    ],
];

foreach ($paths as $file => $needles) {
    $src = file_get_contents(__DIR__ . '/../src/' . ($file === 'Delivery.php' || $file === 'Payment.php'
        ? 'Controllers/' . ($file === 'Delivery.php' ? 'Delivery/Delivery.php' : 'Payment/Payment.php')
        : 'Services/Order/' . $file));
    if ($src === false) {
        $fail("unable to read {$file}");
    }
    foreach ($needles as $needle) {
        if (!str_contains($src, $needle)) {
            $fail("{$file} must reference {$needle}");
        }
    }
}

$registry = file_get_contents(__DIR__ . '/../src/ServiceRegistry.php');
if ($registry === false || !str_contains($registry, 'ms3_manager_order_cost_recalculator')) {
    $fail('ServiceRegistry must register ms3_manager_order_cost_recalculator');
}

$ordersCtrl = file_get_contents(__DIR__ . '/../src/Controllers/Api/Manager/OrdersController.php');
if ($ordersCtrl === false || !str_contains($ordersCtrl, "services->get('ms3_manager_order_cost_recalculator')")) {
    $fail('OrdersController must resolve manager recalculator from DI');
}

$calcSrc = file_get_contents(__DIR__ . '/../src/Services/Order/OrderCostCalculator.php');
if ($calcSrc === false || !preg_match('/getPaymentCost\([^)]+\$cartCost,\s*\$deliveryCost/', $calcSrc)) {
    $fail('getTotalCost must pass precomputed cart/delivery into getPaymentCost (single pipeline pass)');
}

fwrite(STDOUT, "OK OrderCostEngineTest\n");
exit(0);
