<?php

/**
 * Shared order cost engine (#366) — no MODX/MySQL.
 *
 * Run: php tests/OrderCostEngineTest.php
 */

declare(strict_types=1);

require __DIR__ . '/support/xpdo_stub.php';
require __DIR__ . '/support/xpdo_om_stub.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/stubs/StubMsDelivery.php';
require __DIR__ . '/stubs/StubMsPayment.php';

use MiniShop3\Services\Order\OrderCostEngine;
use MiniShop3\Tests\Stubs\StubMsDelivery;
use MiniShop3\Tests\Stubs\StubMsPayment;
use MiniShop3\Utils\PriceAdjustment;
use MODX\Revolution\modX;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertFloat = static function (float $expected, float $actual, string $case) use ($fail): void {
    if (abs($expected - $actual) > 0.000001) {
        $fail($case . ': expected ' . $expected . ', got ' . $actual);
    }
};

$modx = new modX();

$engineSource = file_get_contents(__DIR__ . '/../src/Services/Order/OrderCostEngine.php');
if ($engineSource === false || !str_contains($engineSource, 'OrderService::paymentCommissionBase')) {
    $fail('OrderCostEngine must delegate paymentCommissionBase to OrderService');
}

$assertFloat(100.0, OrderCostEngine::paymentCommissionBase(100.0), 'payment base cart-only');
$assertFloat(104.5, OrderCostEngine::calculatePaymentTotal(100.0, 4.5), 'payment total');

// #460: payment % base = cart only (1000 → 3% fee = 30, delivery excluded)
$cartOnlyBase = OrderCostEngine::paymentCommissionBase(1000.0);
$assertFloat(1000.0, $cartOnlyBase, '#460 commission base cart-only');
$assertFloat(30.0, PriceAdjustment::calculate($cartOnlyBase, '3%'), '#460 payment surcharge on cart-only');

// Delivery free-threshold edge cases (#366 review).
// free_delivery_amount = 1000: cart at or above threshold → delivery is free.
$freeDelivery = new StubMsDelivery([
    'id' => 1,
    'free_delivery_amount' => 1000.0,
    'weight_price' => 10.0,
    'price' => 0,
]);
$assertFloat(0.0, OrderCostEngine::calculateDefaultDeliveryCost($modx, $freeDelivery, 1000.0, 5.0), 'free delivery at threshold');
$assertFloat(0.0, OrderCostEngine::calculateDefaultDeliveryCost($modx, $freeDelivery, 1500.0, 5.0), 'free delivery above threshold');

// Just below threshold: weight_price × weight + price (10×5 + 50 = 100).
$paidDelivery = new StubMsDelivery([
    'id' => 2,
    'free_delivery_amount' => 1000.0,
    'weight_price' => 10.0,
    'price' => 50.0,
]);
$assertFloat(100.0, OrderCostEngine::calculateDefaultDeliveryCost($modx, $paidDelivery, 999.99, 5.0), 'paid delivery weight + fixed price below threshold');

// Percent delivery price applied to cart (5% of 1000 = 50), no weight component.
$percentDelivery = new StubMsDelivery([
    'id' => 3,
    'free_delivery_amount' => 0,
    'weight_price' => 0.0,
    'price' => '5%',
]);
$assertFloat(50.0, OrderCostEngine::calculateDefaultDeliveryCost($modx, $percentDelivery, 1000.0, 0.0), 'percent delivery on cart');

// free_delivery_amount = 0 disables the free threshold (price-only delivery).
$noFreeThreshold = new StubMsDelivery([
    'id' => 4,
    'free_delivery_amount' => 0,
    'weight_price' => 0.0,
    'price' => 75.0,
]);
$assertFloat(75.0, OrderCostEngine::calculateDefaultDeliveryCost($modx, $noFreeThreshold, 100000.0, 0.0), 'fixed delivery with no free threshold');

// Payment percent surcharge base = cart only (#460): 3% of 1000 = 30.
$percentPayment = new StubMsPayment(['id' => 10, 'price' => '3%']);
$percentBase = OrderCostEngine::paymentCommissionBase(1000.0);
$percentSurcharge = OrderCostEngine::calculatePaymentSurcharge($modx, $percentPayment, $percentBase);
$assertFloat(30.0, $percentSurcharge, 'payment 3% surcharge on cart-only base');
$assertFloat(1030.0, OrderCostEngine::calculatePaymentTotal($percentBase, $percentSurcharge), 'payment total with percent on cart-only');

// Fixed payment surcharge is independent of the base.
$fixedPayment = new StubMsPayment(['id' => 11, 'price' => 30]);
$assertFloat(30.0, OrderCostEngine::calculatePaymentSurcharge($modx, $fixedPayment, 1000.0), 'fixed payment surcharge');

// Empty payment price → zero surcharge regardless of base.
$noPricePayment = new StubMsPayment(['id' => 12, 'price' => 0]);
$assertFloat(0.0, OrderCostEngine::calculatePaymentSurcharge($modx, $noPricePayment, 1000.0), 'zero payment surcharge when price empty');

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

if ($calcSrc !== false && preg_match('/paymentCommissionBase\s*\([^)]*,/', $calcSrc) === 1) {
    $fail('OrderCostCalculator must not pass delivery into paymentCommissionBase');
}

fwrite(STDOUT, "OK OrderCostEngineTest\n");
exit(0);
