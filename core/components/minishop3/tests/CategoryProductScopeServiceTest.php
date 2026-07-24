<?php

/**
 * CategoryProductScopeService: parent boundary for category product mutations (#418).
 *
 * Run: php tests/CategoryProductScopeServiceTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/stubs/StubMsProduct.php';
require __DIR__ . '/stubs/CategoryProductScopeModxStub.php';
require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Category\CategoryProductScopeService;
use MiniShop3\Tests\Stubs\CategoryProductScopeModxStub;

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

$scope = new CategoryProductScopeService($modx);

$assertSame(null, $scope->findInCategory(1, 20), 'reject product from other category');
$assertSame(null, $scope->findInCategory(1, 999), 'reject missing product');
$assertSame(null, $scope->findInCategory(0, 10), 'reject zero category');
$assertSame(null, $scope->findInCategory(1, 0), 'reject zero product');

fwrite(STDOUT, "OK: CategoryProductScopeServiceTest\n");
