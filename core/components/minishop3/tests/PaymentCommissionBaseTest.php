<?php

/**
 * Guards payment commission base (issue #372): MS2-compatible cart-only base.
 *
 * Run: php tests/PaymentCommissionBaseTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Order\OrderService;
use MiniShop3\Utils\PriceAdjustment;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$cartCost = 1000.0;
$deliveryCost = 500.0;
$percent = '3%';

$base = OrderService::paymentCommissionBase($cartCost);
if ($base !== 1000.0) {
    $fail('paymentCommissionBase must round cart cost only');
}

$fee = PriceAdjustment::calculate($base, $percent);
if (abs($fee - 30.0) > 0.000001) {
    $fail('3% of cart 1000 must be 30, got ' . $fee);
}

$wrongFee = PriceAdjustment::calculate($cartCost + $deliveryCost, $percent);
if (abs($wrongFee - 45.0) > 0.000001) {
    $fail('sanity: 3% of cart+delivery 1500 must be 45');
}

if (abs($fee - $wrongFee) < 0.000001) {
    $fail('cart-only and cart+delivery bases must differ for this repro');
}

$engineSource = file_get_contents(__DIR__ . '/../src/Services/Order/OrderCostEngine.php');
if ($engineSource === false || !str_contains($engineSource, 'OrderService::paymentCommissionBase')) {
    $fail('OrderCostEngine must delegate paymentCommissionBase to OrderService');
}

$recalculatorSource = file_get_contents(__DIR__ . '/../src/Services/Order/ManagerOrderCostRecalculator.php');
if ($recalculatorSource === false) {
    $fail('cannot read ManagerOrderCostRecalculator.php');
}

if (!str_contains($recalculatorSource, 'OrderCostEngine::paymentCommissionBase($cartCost)')) {
    $fail('ManagerOrderCostRecalculator must use OrderCostEngine::paymentCommissionBase($cartCost)');
}

if (preg_match('/paymentBase\s*=\s*round\s*\(\s*\$cartCost\s*\+\s*\$deliveryCost/s', $recalculatorSource) === 1) {
    $fail('ManagerOrderCostRecalculator must not use cart+delivery as payment base');
}

$calculatorSource = file_get_contents(__DIR__ . '/../src/Services/Order/OrderCostCalculator.php');
if ($calculatorSource === false) {
    $fail('cannot read OrderCostCalculator.php');
}

if (!str_contains($calculatorSource, 'OrderCostEngine::paymentCommissionBase')) {
    $fail('OrderCostCalculator must use OrderCostEngine::paymentCommissionBase');
}

if (preg_match('/paymentCommissionBase\s*\([^)]*,/', $calculatorSource) === 1) {
    $fail('OrderCostCalculator must not pass delivery into paymentCommissionBase');
}

fwrite(STDOUT, "OK PaymentCommissionBaseTest\n");
exit(0);
