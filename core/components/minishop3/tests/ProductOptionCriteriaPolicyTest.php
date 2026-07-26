<?php

/**
 * Regression tests for msProductOption criteria whitelist (issue #336).
 *
 * Run: php tests/ProductOptionCriteriaPolicyTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Option\ProductOptionCriteriaPolicy;

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
    ['product_id' => 42, 'key' => 'color'],
    ProductOptionCriteriaPolicy::normalize(['product_id' => 42, 'key' => 'color']),
    'valid criteria'
);

$assertSame(
    ['product_id' => 7, 'key' => 'size'],
    ProductOptionCriteriaPolicy::normalize([
        'product_id' => '7',
        'key' => 'size',
        'value' => 'L',
        'value:!=' => '',
    ]),
    'strips extra keys from criteria'
);

$assertSame(null, ProductOptionCriteriaPolicy::normalize(null), 'null criteria');
$assertSame(null, ProductOptionCriteriaPolicy::normalize('product_id=1'), 'non-array criteria');
$assertSame(null, ProductOptionCriteriaPolicy::normalize(['key' => 'color']), 'missing product_id');
$assertSame(null, ProductOptionCriteriaPolicy::normalize(['product_id' => 1]), 'missing key');
$assertSame(null, ProductOptionCriteriaPolicy::normalize(['product_id' => 0, 'key' => 'color']), 'zero product_id');
$assertSame(null, ProductOptionCriteriaPolicy::normalize(['product_id' => -3, 'key' => 'color']), 'negative product_id');
$assertSame(null, ProductOptionCriteriaPolicy::normalize(['product_id' => 'abc', 'key' => 'color']), 'non-int product_id');
$assertSame(null, ProductOptionCriteriaPolicy::normalize(['product_id' => 1, 'key' => '']), 'empty key');

fwrite(STDOUT, "OK ProductOptionCriteriaPolicyTest\n");
exit(0);
