<?php

/**
 * CatalogSortbyQualifier (#741 / #742 review).
 *
 * Run: php tests/CatalogSortbyQualifierTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Catalog\CatalogSortbyQualifier;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$fields = ['id', 'pagetitle', 'publishedon', 'menuindex', 'parent'];

$assertSame(
    'msProduct.pagetitle DESC, msProduct.publishedon',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields('pagetitle DESC, publishedon', $fields),
    'multi-column bare fields'
);
$assertSame(
    'msProduct.pagetitle',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields('pagetitle', $fields),
    'single bare field'
);
$assertSame(
    'msProduct.pagetitle DESC, Data.price ASC',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields('pagetitle DESC, Data.price ASC', $fields),
    'leaves non-resource / already-aliased pieces'
);
$assertSame(
    '{"pagetitle":"DESC","publishedon":"ASC"}',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields('{"pagetitle":"DESC","publishedon":"ASC"}', $fields),
    'JSON untouched'
);
$case = 'CASE WHEN msProduct.parent = 1 THEN msProduct.menuindex ELSE 0 END DESC, pagetitle';
$assertSame(
    'CASE WHEN msProduct.parent = 1 THEN msProduct.menuindex ELSE 0 END DESC, msProduct.pagetitle',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields($case, $fields),
    'CASE piece left alone; trailing bare field still qualified'
);
$assertSame(
    'IFNULL(pagetitle, \'\') DESC',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields('IFNULL(pagetitle, \'\') DESC', $fields),
    'parenthesized expressions untouched as a whole'
);
$assertSame(
    'Vendor.name ASC, msProduct.pagetitle',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields('Vendor.name ASC, pagetitle', $fields),
    'foreign alias kept, bare resource field qualified'
);

fwrite(STDOUT, "OK: CatalogSortbyQualifierTest\n");
