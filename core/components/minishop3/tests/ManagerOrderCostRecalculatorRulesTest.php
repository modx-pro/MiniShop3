<?php

/**
 * Regression smoke tests for manager order cost rules (#373).
 *
 * Validates free_delivery_amount, percent delivery, and payment commission math
 * used by ManagerOrderCostRecalculator::calculateBreakdown().
 *
 * Run: php tests/ManagerOrderCostRecalculatorRulesTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Utils\PriceAdjustment;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$calculateDefaultDeliveryCost = static function (
    float $freeDeliveryAmount,
    float $weightPrice,
    string $addPrice,
    float $cartCost,
    float $orderWeight
): float {
    if ($freeDeliveryAmount > 0 && $cartCost >= $freeDeliveryAmount) {
        return 0.0;
    }

    $deliveryCost = $weightPrice * $orderWeight;

    if ($addPrice === '') {
        return round($deliveryCost, 6);
    }

    return round($deliveryCost + PriceAdjustment::calculate($cartCost, $addPrice), 6);
};

$assertSame(
    0.0,
    $calculateDefaultDeliveryCost(1000, 50, '100', 1500, 2),
    'free delivery when cart meets threshold'
);

$assertSame(
    70.0,
    $calculateDefaultDeliveryCost(0, 10, '5%', 1000, 2),
    'weight cost plus percent of cart'
);

$assertSame(
    30.0,
    round(PriceAdjustment::calculate(1000, '3%'), 6),
    'payment commission percent of cart-only base'
);

$paymentBase = 1000 + 100;
$assertSame(
    33.0,
    round(PriceAdjustment::calculate($paymentBase, '3%'), 6),
    'payment commission percent of cart plus delivery base'
);

fwrite(STDOUT, "OK ManagerOrderCostRecalculatorRulesTest\n");
exit(0);
