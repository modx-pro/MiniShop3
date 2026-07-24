<?php

/**
 * CategoryProductScopeService: direct and nested parent scope (#418).
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

fwrite(STDOUT, "OK: CategoryProductScopeServiceTest\n");
