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
use MiniShop3\Services\Grid\RelationSqlFragments;

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
    '`rel_msVendor_vendor_id`.`id` = `Data`.`vendor_id`',
    $spec->joinCondition(),
    'vendor join condition'
);
$assertSame(
    '`rel_msVendor_vendor_id`.`address` AS `vendor_address`',
    $spec->selectExpression(),
    'vendor select expression'
);
$assertSame(
    '`rel_msVendor_vendor_id`.`address`',
    $spec->sortExpression(),
    'vendor sort/filter expression'
);

$rankGroup = $group;
$rankGroup['foreignKey'] = 'vendor_id';
$rankSpec = RelationColumnSpec::fromGroupField($rankGroup, [
    'name' => 'vendor_rank',
    'displayField' => 'rank',
]);
$assertTrue($rankSpec instanceof RelationColumnSpec, 'grandfathered reserved displayField is accepted');
$assertSame(
    '`rel_msVendor_vendor_id`.`rank` AS `vendor_rank`',
    $rankSpec->selectExpression(),
    'reserved displayField is quoted'
);

$rankAliasSpec = RelationColumnSpec::fromGroupField($rankGroup, [
    'name' => 'rank',
    'displayField' => 'name',
]);
$assertTrue(
    $rankAliasSpec instanceof RelationColumnSpec,
    'grandfathered reserved fieldName alias is accepted at read time'
);
$assertSame(
    '`rel_msVendor_vendor_id`.`name` AS `rank`',
    $rankAliasSpec->selectExpression(),
    'reserved fieldName alias is quoted'
);

$assertTrue(
    !GridColumnRules::isValidCategoryProductExtraFieldName('rank'),
    'create path still rejects reserved fieldName'
);
$assertTrue(
    GridColumnRules::isReadableCategoryProductExtraFieldName('rank'),
    'read path allows reserved fieldName'
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

$assertSame(
    '`rel_msVendor_vendor_id`.`rank` AS `vendor_rank`',
    RelationSqlFragments::selectAs('rel_msVendor_vendor_id', 'rank', 'vendor_rank'),
    'RelationSqlFragments quotes reserved displayField'
);
$assertSame(
    '`rel_status`.`id` = `msOrder`.`status_id`',
    RelationSqlFragments::joinEqualsId('rel_status', 'msOrder', 'status_id'),
    'RelationSqlFragments order join'
);

$assertSame('modx_ms3_orders', RelationSqlFragments::stripIdentQuotes('`modx_ms3_orders`'), 'stripIdentQuotes');

$customersSql = RelationSqlFragments::customerRelationAggregateSql(
    '`modx_ms3_customers`',
    'modx_ms3_orders',
    'user_id',
    'rank',
    'COUNT',
    [1, 2],
);
$assertTrue(
    is_string($customersSql)
    && str_contains($customersSql, '`modx_ms3_orders`.`rank`')
    && str_contains($customersSql, 'COUNT(`modx_ms3_orders`.`rank`)')
    && str_contains($customersSql, 'LEFT JOIN `modx_ms3_orders` ON `modx_ms3_orders`.`user_id` = `modx_ms3_customers`.`id`')
    && str_contains($customersSql, 'IN (1,2)'),
    'customerRelationAggregateSql quotes reserved displayField and tables'
);

$fkSql = RelationSqlFragments::customerRelationAggregateSql(
    'modx_ms3_customers',
    'modx_ms3_orders',
    'order',
    'cost',
    'SUM',
    [5],
);
$assertTrue(
    is_string($fkSql) && str_contains($fkSql, '`modx_ms3_orders`.`order`'),
    'customerRelationAggregateSql quotes reserved foreignKey'
);

$assertTrue(
    RelationSqlFragments::customerRelationAggregateSql(
        'c',
        'r',
        'fk',
        'col',
        'DROP',
        [1],
    ) === null,
    'customerRelationAggregateSql rejects unknown aggregation'
);

$assertTrue(
    RelationSqlFragments::customerRelationAggregateSql(
        'modx_ms3_customers',
        'modx_ms3_orders',
        'user_id',
        'address; DROP TABLE',
        'COUNT',
        [1],
    ) === null,
    'customerRelationAggregateSql rejects invalid identifier charset'
);

fwrite(STDOUT, "OK RelationColumnSpecTest\n");
exit(0);
