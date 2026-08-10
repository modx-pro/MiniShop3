<?php

/**
 * Regression tests for category product scope policy (#444, #455).
 *
 * Run: php tests/CategoryProductScopePolicyTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Category\CategoryProductScopePolicy;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertSame(true, CategoryProductScopePolicy::isParentInScope(10, 10, false, []), 'direct parent match');
$assertSame(false, CategoryProductScopePolicy::isParentInScope(11, 10, false, []), 'direct parent mismatch');
$assertSame(
    true,
    CategoryProductScopePolicy::isParentInScope(12, 10, true, [11, 12]),
    'nested child category'
);
$assertSame(
    true,
    CategoryProductScopePolicy::isParentInScope(10, 10, true, [11, 12]),
    'nested root category'
);
$assertSame(
    false,
    CategoryProductScopePolicy::isParentInScope(99, 10, true, [11, 12]),
    'nested foreign parent'
);
$assertSame(false, CategoryProductScopePolicy::isParentInScope(0, 10, false, []), 'invalid product parent');
$assertSame(false, CategoryProductScopePolicy::isParentInScope(10, 0, false, []), 'invalid category id');

$assertSame(
    [10],
    CategoryProductScopePolicy::allowedParentCategoryIds(10, false, [11, 12]),
    'allowedParentCategoryIds direct'
);
$assertSame(
    [11, 12, 10],
    CategoryProductScopePolicy::allowedParentCategoryIds(10, true, [11, 12]),
    'allowedParentCategoryIds nested'
);

fwrite(STDOUT, "OK CategoryProductScopePolicyTest\n");
exit(0);
