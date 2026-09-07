<?php

/**
 * CategoryProductMenuindexService SQL helpers (#625).
 *
 * Run: php tests/CategoryProductMenuindexServiceTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/stubs/ProductCategoryMembershipModxStub.php';

use MiniShop3\Services\Category\CategoryProductMenuindexService;
use MiniShop3\Tests\Stubs\ProductCategoryMembershipModxStub;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertTrue = static function (bool $value, string $case) use ($fail): void {
    if (!$value) {
        $fail($case);
    }
};

$assertSame(true, CategoryProductMenuindexService::isNativeInCategory(5, 5), 'native same category');
$assertSame(false, CategoryProductMenuindexService::isNativeInCategory(5, 9), 'not native');
$assertSame(true, CategoryProductMenuindexService::isNativeInCategories(3, [1, 3, 7]), 'native in list');

$singleSql = CategoryProductMenuindexService::effectiveMenuindexSql(12);
$assertTrue(str_contains($singleSql, 'msProduct.parent = 12'), 'single category parent check');
$assertTrue(str_contains($singleSql, 'COALESCE(CategoryMember.menuindex, msProduct.menuindex)'), 'single coalesce');

$multiSql = CategoryProductMenuindexService::effectiveMenuindexSqlForCategories([4, 8]);
$assertTrue(str_contains($multiSql, 'msProduct.parent IN (4,8)'), 'multi parent in');
$assertTrue(str_contains($multiSql, 'MIN(CategoryMember.menuindex)'), 'multi uses min');

$joinSingle = CategoryProductMenuindexService::memberJoinOn(15);
$assertSame(
    '`CategoryMember`.product_id = msProduct.id AND `CategoryMember`.category_id = 15',
    $joinSingle,
    'single member join'
);

$joinMulti = CategoryProductMenuindexService::memberJoinOnCategories([2, 6]);
$assertSame(
    '`CategoryMember`.product_id = msProduct.id AND `CategoryMember`.category_id IN (2,6)',
    $joinMulti,
    'multi member join'
);

$menuindexModx = new ProductCategoryMembershipModxStub();
$menuindexService = new CategoryProductMenuindexService($menuindexModx);

$assertSame(0, $menuindexService->getNextMemberMenuindex(99), 'empty category next is 0');

$menuindexModx->members = [
    ['product_id' => 1, 'category_id' => 10, 'menuindex' => 4],
    ['product_id' => 2, 'category_id' => 10, 'menuindex' => 7],
];
$assertSame(8, $menuindexService->getNextMemberMenuindex(10), 'next after member max');

$menuindexModx->nativeProducts = [
    ['parent' => 11, 'menuindex' => 12],
];
$menuindexModx->members = [];
$assertSame(13, $menuindexService->getNextMemberMenuindex(11), 'next after native max');

$menuindexModx->nativeProducts = [
    ['parent' => 12, 'menuindex' => 3],
];
$menuindexModx->members = [
    ['product_id' => 5, 'category_id' => 12, 'menuindex' => 9],
];
$assertSame(10, $menuindexService->getNextMemberMenuindex(12), 'next after greater of native and member');

fwrite(STDOUT, "OK: CategoryProductMenuindexServiceTest\n");
