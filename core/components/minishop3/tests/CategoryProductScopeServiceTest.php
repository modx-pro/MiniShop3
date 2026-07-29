<?php

/**
 * CategoryProductScopeService: IDOR parent scope (#418) + additional categories (#481/#480).
 *
 * Run: php tests/CategoryProductScopeServiceTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/stubs/StubMsProduct.php';
require __DIR__ . '/stubs/CategoryProductScopeModxStub.php';
require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Model\msProduct;
use MiniShop3\Services\Category\CategoryProductScopeService;
use MiniShop3\Tests\Stubs\CategoryProductScopeModxStub;
use MiniShop3\Tests\Stubs\StubMsProduct;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$modx = new CategoryProductScopeModxStub();
$modx->products = [
    ['id' => 10, 'parent' => 1, 'published' => 0],
    ['id' => 20, 'parent' => 2, 'published' => 1],
];
$modx->categories = [
    ['id' => 2, 'parent' => 1],
];

$scope = new CategoryProductScopeService($modx);

$direct = $scope->findInCategory(1, 10, false);
if (!$direct instanceof StubMsProduct) {
    $fail('direct scope should return in-category product');
}

$assertSame(null, $scope->findInCategory(1, 20, false), 'reject child category product when not nested');

$nested = $scope->findInCategory(1, 20, true);
if (!$nested instanceof StubMsProduct) {
    $fail('nested scope should return descendant category product');
}

$assertSame(null, $scope->findInCategory(1, 999, true), 'reject missing product');
$assertSame(null, $scope->findInCategory(0, 10, false), 'reject zero category');
$assertSame(null, $scope->findInCategory(1, 0, false), 'reject zero product');

$directCall = null;
foreach ($modx->getObjectCalls as $call) {
    if (($call['class'] ?? '') !== msProduct::class || !is_array($call['criteria'] ?? null)) {
        continue;
    }
    if (($call['criteria']['id'] ?? null) === 10 && ($call['criteria']['parent'] ?? null) === 1) {
        $directCall = $call['criteria'];
        break;
    }
}
$assertSame(['id' => 10, 'parent' => 1], $directCall, 'direct lookup uses id and parent');

$parsed = CategoryProductScopeService::parseParentSpec('10, 20 ,-30,0,abc');
$assertSame([10, 20], $parsed['include'], 'parse include');
$assertSame([30], $parsed['exclude'], 'parse exclude');

$emptyParsed = CategoryProductScopeService::parseParentSpec('');
$assertSame([], $emptyParsed['include'], 'empty include');
$assertSame([], $emptyParsed['exclude'], 'empty exclude');

$finalized = CategoryProductScopeService::finalizeIncludedCategoryIds([10, 20, 30], [20, 99]);
$assertSame([10, 30], $finalized, 'finalize exclude diff');

$parentOnlySql = CategoryProductScopeService::buildMsProductsWhereSql([5, 12], []);
$assertSame(
    '`msProduct`.`parent` IN (5,12)',
    $parentOnlySql,
    'where parent only'
);

$withMembersSql = CategoryProductScopeService::buildMsProductsWhereSql([5], [101, 102, 101]);
$assertSame(
    '(`msProduct`.`parent` IN (5) OR `msProduct`.`id` IN (101,102))',
    $withMembersSql,
    'where parent or member ids'
);

try {
    CategoryProductScopeService::buildMsProductsWhereSql([], [1]);
    $fail('empty categoryIds must throw');
} catch (\InvalidArgumentException) {
    // expected
}

$regressionSql = CategoryProductScopeService::buildMsProductsWhereSql([42], [999]);
if (!str_contains($regressionSql, 'OR `msProduct`.`id` IN (999)')) {
    $fail('regression: member OR clause missing');
}
if (!str_contains($regressionSql, '`msProduct`.`parent` IN (42)')) {
    $fail('regression: parent IN clause missing');
}

$scopeWhere = CategoryProductScopeService::buildProductCategoryScopeWhere([7], []);
$assertSame(['msProduct.parent:IN' => [7]], $scopeWhere, 'admin scope parent only');

$scopeWhereMembers = CategoryProductScopeService::buildProductCategoryScopeWhere([7, 8], [100, 200]);
$assertSame(
    [
        'msProduct.parent:IN' => [7, 8],
        'OR:msProduct.id:IN' => [100, 200],
    ],
    $scopeWhereMembers,
    'admin scope parent or member ids'
);

fwrite(STDOUT, "OK: CategoryProductScopeServiceTest\n");
