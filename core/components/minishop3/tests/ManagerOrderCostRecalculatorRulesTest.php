<?php

/**
 * Regression smoke tests for persisted order cost rules (#373).
 *
 * Calls {@see OrderPersistedCostRules} — the same pure helpers used by
 * ManagerOrderCostRecalculator::calculateBreakdown().
 *
 * Run: php tests/ManagerOrderCostRecalculatorRulesTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Order\OrderPersistedCostRules;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertSame(
    0.0,
    OrderPersistedCostRules::calculateDefaultDeliveryCost(1000, 50, '100', 1500, 2),
    'free delivery when cart meets threshold'
);

$assertSame(
    70.0,
    OrderPersistedCostRules::calculateDefaultDeliveryCost(0, 10, '5%', 1000, 2),
    'weight cost plus percent of cart'
);

$assertSame(
    30.0,
    OrderPersistedCostRules::calculateDefaultPaymentCommission('3%', 1000),
    'payment commission percent of cart-only base'
);

$assertSame(
    33.0,
    OrderPersistedCostRules::calculateDefaultPaymentCommission('3%', 1100),
    'payment commission percent of cart plus delivery base'
);

fwrite(STDOUT, "OK ManagerOrderCostRecalculatorRulesTest\n");
exit(0);
