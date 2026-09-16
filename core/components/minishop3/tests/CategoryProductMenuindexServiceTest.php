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
$assertSame(
    'CASE WHEN `msProduct`.`parent` = 12 THEN `msProduct`.`menuindex` '
    . 'ELSE COALESCE(`CategoryMember`.`menuindex`, `msProduct`.`menuindex`) END',
    $singleSql,
    'single category expression'
);

$multiSql = CategoryProductMenuindexService::effectiveMenuindexSqlForCategories([4, 8]);
$assertSame(
    'CASE WHEN `msProduct`.`parent` IN (4,8) THEN `msProduct`.`menuindex` '
    . 'ELSE COALESCE(MIN(`CategoryMember`.`menuindex`), `msProduct`.`menuindex`) END',
    $multiSql,
    'multi category expression'
);
$assertSame('`msProduct`.`menuindex`', CategoryProductMenuindexService::effectiveMenuindexSqlForCategories([]), 'no categories');

// msProducts passes the expression as pdoTools sortby. pdoTools\Fetch::addSort() (3.0.x) splits
// the clause on commas and rewrites `alias.field ` in every piece without a backtick; the result
// was invalid SQL (empty catalog). The expression must survive that step unchanged.
$pdoToolsEscapeSortby = static function (string $sortby): string {
    $tmp = explode(',', $sortby);
    array_walk($tmp, static function (&$value) {
        if (strpos($value, '`') === false) {
            $value = preg_replace('#(.*?)\.(.*?)\s#', '`$1`.`$2`', $value);
        }
    });

    return implode(',', $tmp);
};
foreach ([[12], [4, 8], [3, 5, 7, 11]] as $ids) {
    $sql = CategoryProductMenuindexService::effectiveMenuindexSqlForCategories($ids);
    $assertSame($sql, $pdoToolsEscapeSortby($sql), 'pdoTools sortby escaping keeps expression for ' . implode(',', $ids));
}

// msProducts sortby substitution.
$scope = [3, 5, 7];
$expr = CategoryProductMenuindexService::effectiveMenuindexSqlForCategories($scope);
$substitute = static fn (string|array $sortby, array $ids = [3, 5, 7]): string
    => CategoryProductMenuindexService::substituteMenuindexSortby($sortby, $ids);

foreach (['menuindex', '`menuindex`', 'msProduct.menuindex', '`msProduct`.`menuindex`', 'MenuIndex'] as $sortby) {
    $assertSame($expr, $substitute($sortby), 'substitute ' . $sortby);
}
$assertSame($expr . ' DESC, pagetitle', $substitute('menuindex DESC, pagetitle'), 'substitute with direction and second column');
$assertSame(
    $substitute('menuindex DESC, pagetitle'),
    $pdoToolsEscapeSortby($substitute('menuindex DESC, pagetitle')),
    'substituted multi-column sortby survives pdoTools escaping'
);

$json = $substitute('{"menuindex":"ASC","pagetitle":"DESC"}');
$decoded = json_decode($json, true);
$assertSame([$expr => 'ASC', 'pagetitle' => 'DESC'], $decoded, 'substitute inside JSON sortby keeps valid JSON');

// Array sortby (Fenom/PHP) must not cast to "Array" (#740).
$arrayAsc = $substitute(['menuindex' => 'ASC']);
$assertSame(
    json_decode($substitute('{"menuindex":"ASC"}'), true),
    json_decode($arrayAsc, true),
    'array [menuindex => ASC] matches JSON equivalent'
);
$arrayMulti = $substitute(['menuindex' => 'DESC', 'pagetitle' => 'ASC']);
$assertSame(
    [$expr => 'DESC', 'pagetitle' => 'ASC'],
    json_decode($arrayMulti, true),
    'array [menuindex => DESC, pagetitle => ASC] substitutes key only'
);
$assertTrue(
    CategoryProductMenuindexService::sortbyRefersToMenuindex(['menuindex' => 'ASC']),
    'sortbyRefersToMenuindex detects array keys'
);
$assertTrue(
    !CategoryProductMenuindexService::sortbyRefersToMenuindex(['pagetitle' => 'ASC']),
    'sortbyRefersToMenuindex ignores array without menuindex'
);
$assertTrue(
    !CategoryProductMenuindexService::sortbyRefersToMenuindex('Array'),
    'literal Array string is not treated as menuindex sortby'
);

$assertSame('pagetitle', $substitute('pagetitle'), 'no menuindex in sortby');
$assertSame('CategoryMember.menuindex', $substitute('CategoryMember.menuindex'), 'other alias menuindex untouched');
$assertSame('`CategoryMember`.`menuindex`', $substitute('`CategoryMember`.`menuindex`'), 'quoted other alias menuindex untouched');
$assertSame('menuindex', $substitute('menuindex', []), 'no category scope');
$assertSame($expr, $substitute($expr), 'already substituted sortby unchanged');

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
