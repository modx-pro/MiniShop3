<?php

/**
 * CategoryTreeService: descendant category ids for nested product grids (#418).
 *
 * Run: php tests/CategoryTreeServiceTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/stubs/CategoryProductScopeModxStub.php';
require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Category\CategoryTreeService;
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
$modx->categories = [
    ['id' => 1, 'parent' => 0],
    ['id' => 2, 'parent' => 1],
    ['id' => 3, 'parent' => 2],
];

$tree = new CategoryTreeService($modx);

$assertSame([1], $tree->productParentIds(1, false), 'direct scope');
$assertSame([1, 2, 3], $tree->productParentIds(1, true), 'nested scope includes descendants');

fwrite(STDOUT, "OK: CategoryTreeServiceTest\n");
