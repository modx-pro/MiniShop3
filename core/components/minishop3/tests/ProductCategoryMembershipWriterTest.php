<?php

/**
 * ProductCategoryMembershipWriter preserves member menuindex on re-save (#625).
 *
 * Run: php tests/ProductCategoryMembershipWriterTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/support/xpdo_stub.php';
require __DIR__ . '/support/xpdo_om_stub.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/stubs/ProductCategoryMembershipModxStub.php';

use MiniShop3\Model\msProductData;
use MiniShop3\Services\Product\ProductCategoryMembershipWriter;
use MiniShop3\Tests\Stubs\ProductCategoryMembershipModxStub;

final class WriterTestProductData extends msProductData
{
    /** @var array<string, mixed> */
    public array $_fields = [];

    public function get($key, $format = null, $formatString = '')
    {
        if ($key === 'id') {
            return 100;
        }

        return parent::get($key, $format, $formatString);
    }
}

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$modx = new ProductCategoryMembershipModxStub();
$modx->members = [
    ['product_id' => 100, 'category_id' => 2, 'menuindex' => 7],
    ['product_id' => 100, 'category_id' => 3, 'menuindex' => 4],
];
$modx->nativeProducts = [
    ['parent' => 5, 'menuindex' => 2],
];

$writer = new ProductCategoryMembershipWriter($modx);
$xpdo = new class extends \xPDO\xPDO {
    public object $services;

    public function __construct()
    {
        $this->services = new class {
            public function has(string $key): bool
            {
                return false;
            }

            public function get(string $key): null
            {
                return null;
            }
        };
    }
};

$setExplicitCategories = static function (WriterTestProductData $productData, array $categories): void {
    $productData->_fields = [
        'id' => 100,
        'categories' => $categories,
    ];
};

$productData = new WriterTestProductData($xpdo);
$setExplicitCategories($productData, [2, 3, 5]);

$writer->saveCategories($productData);

$byCategory = [];
foreach ($modx->members as $row) {
    $byCategory[(int) $row['category_id']] = (int) $row['menuindex'];
}

$assertSame(7, $byCategory[2] ?? null, 'kept menuindex for category 2');
$assertSame(4, $byCategory[3] ?? null, 'kept menuindex for category 3');
$assertSame(3, $byCategory[5] ?? null, 'new member gets next menuindex after native product in category 5');

$productData2 = new WriterTestProductData($xpdo);
$setExplicitCategories($productData2, [2]);

$writer->saveCategories($productData2);

$remainingCategories = array_map(
    static fn(array $row): int => (int) $row['category_id'],
    $modx->members
);
sort($remainingCategories);
$assertSame([2], $remainingCategories, 'removed unlisted categories');
$assertSame(7, $modx->members[0]['menuindex'], 'menuindex still preserved after shrink');

fwrite(STDOUT, "OK: ProductCategoryMembershipWriterTest\n");
