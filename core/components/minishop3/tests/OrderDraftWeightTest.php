<?php

/**
 * Regression: draft weight must multiply unit weight by count (#375).
 *
 * Run: php tests/OrderDraftWeightTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Order\OrderService;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $label) use ($fail): void {
    if ($actual !== $expected) {
        $fail(sprintf(
            "%s:\nexpected: %s\nactual:   %s",
            $label,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
};

$product = static function (float $weight, int $count, float $cost = 0.0): object {
    return new class ($weight, $count, $cost) {
        public function __construct(
            private float $weight,
            private int $count,
            private float $cost,
        ) {
        }

        public function get(string $field): float|int
        {
            return match ($field) {
                'weight' => $this->weight,
                'count' => $this->count,
                'cost' => $this->cost,
                default => 0,
            };
        }
    };
};

$empty = OrderService::aggregateProductsTotals([]);
$assertSame(0.0, $empty['weight'], 'empty weight');
$assertSame(0.0, $empty['cart_cost'], 'empty cart_cost');

$single = OrderService::aggregateProductsTotals([
    $product(1.0, 3, 30.0),
]);
$assertSame(3.0, $single['weight'], 'weight=1 count=3 → total weight 3');
$assertSame(30.0, $single['cart_cost'], 'line cost is not multiplied again');

$multi = OrderService::aggregateProductsTotals([
    $product(1.5, 2, 10.0),
    $product(0.5, 4, 20.0),
]);
$assertSame(5.0, $multi['weight'], '1.5*2 + 0.5*4');
$assertSame(30.0, $multi['cart_cost'], '10 + 20');

// Guard: unit weight alone must not be treated as total (the #375 bug)
if ($single['weight'] === 1.0) {
    $fail('weight must include count; got unit weight only');
}

fwrite(STDOUT, "OK OrderDraftWeightTest\n");
exit(0);
