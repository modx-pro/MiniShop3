<?php

/**
 * Static checks for relation column specs (without MODX).
 *
 * Run: php tests/RelationColumnSpecTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Grid\GridColumnRules;
use MiniShop3\Services\Grid\ProductDataForeignKeys;
use MiniShop3\Services\Grid\RelationColumnSpec;

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

$assertNull = static function ($actual, string $case) use ($fail): void {
    if ($actual !== null) {
        $fail($case . ': expected null, got ' . var_export($actual, true));
    }
};

$assertSame('Data', RelationColumnSpec::resolveLocalAlias('vendor_id'), 'vendor_id uses Data alias');
$assertSame('msProduct', RelationColumnSpec::resolveLocalAlias('parent'), 'parent uses msProduct alias');
$assertTrue(ProductDataForeignKeys::contains('vendor_id'), 'vendor_id is product data FK');

$group = [
    'modelClass' => 'MiniShop3\\Model\\msVendor',
    'foreignKey' => 'vendor_id',
    'alias' => 'rel_msVendor_vendor_id',
    'fields' => [],
];

$spec = RelationColumnSpec::fromGroupField($group, [
    'name' => 'vendor_address',
    'displayField' => 'address',
]);
$assertTrue($spec instanceof RelationColumnSpec, 'valid relation spec is created');
$assertSame(
    '`rel_msVendor_vendor_id`.id = Data.vendor_id',
    $spec->joinCondition(),
    'vendor join condition'
);
$assertSame(
    '`rel_msVendor_vendor_id`.address AS `vendor_address`',
    $spec->selectExpression(),
    'vendor select expression'
);
$assertSame(
    '`rel_msVendor_vendor_id`.address',
    $spec->sortExpression(),
    'vendor sort/filter expression'
);

$assertNull(
    RelationColumnSpec::fromGroupField($group, [
        'name' => 'article',
        'displayField' => 'address',
    ]),
    'reserved field name is rejected'
);

$assertNull(
    RelationColumnSpec::fromGroupField($group, [
        'name' => 'vendor_address',
        'displayField' => 'address; DROP TABLE',
    ]),
    'unsafe displayField is rejected'
);

$assertTrue(
    GridColumnRules::isValidCategoryProductExtraFieldName('vendor_address'),
    'vendor_address is allowed extra field'
);
$assertTrue(
    !GridColumnRules::isValidCategoryProductExtraFieldName('vendor_id'),
    'vendor_id is reserved'
);

fwrite(STDOUT, "OK RelationColumnSpecTest\n");
exit(0);
