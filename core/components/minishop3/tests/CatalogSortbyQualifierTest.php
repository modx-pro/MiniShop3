<?php

/**
 * CatalogSortbyQualifier (#741 / #742 / #755).
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
$dataFields = ['price', 'article', 'old_price', 'weight'];

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
$assertSame(
    'IFNULL(pagetitle, \'\') DESC',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields('IFNULL(pagetitle, \'\') DESC', $fields),
    'parenthesized expressions untouched as a whole when not dropping'
);
$assertSame(
    'Vendor.name ASC, msProduct.pagetitle',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields('Vendor.name ASC, pagetitle', $fields),
    'foreign alias kept, bare resource field qualified'
);
$assertSame(
    '',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields(',', $fields),
    'legacy empty input stays empty without drop mode'
);

$assertSame(
    'msProduct.pagetitle DESC, msProduct.publishedon',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields(
        'pagetitle DESC, publishedon, evil_injection',
        $fields,
        'msProduct',
        true,
    ),
    'snippet drop mode drops unknown simple parts'
);
$assertSame(
    'Data.price DESC, msProduct.pagetitle ASC',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields(
        'price DESC, pagetitle ASC, junk',
        $fields,
        'msProduct',
        true,
        $dataFields,
    ),
    'snippet drop mode qualifies Data fields and keeps resource fields'
);
$assertSame(
    'vendor_name ASC',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields(
        'vendor_name ASC, evil',
        $fields,
        'msProduct',
        true,
        $dataFields,
        'Data',
        ['vendor_name'],
    ),
    'snippet drop mode keeps passthrough names bare'
);
$assertSame(
    'Data.price DESC',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields(
        'Data.price DESC, evil.column ASC',
        $fields,
        'msProduct',
        true,
        $dataFields,
    ),
    'snippet drop mode rejects unknown table prefixes'
);
$assertSame(
    'msProduct.id',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields(
        'CASE WHEN 1=1 THEN id ELSE 0 END, (SELECT 1)',
        $fields,
        'msProduct',
        true,
        $dataFields,
    ),
    'snippet drop mode rejects caller CASE / parenthetical before snippet inject'
);
$assertSame(
    'msProduct.id',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields('evil_only', $fields, 'msProduct', true),
    'snippet drop mode falls back to msProduct.id when all parts dropped'
);

$assertSame(
    'RAND()',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields('RAND()', $fields, 'msProduct', true, $dataFields),
    'snippet drop mode keeps RAND() without arguments'
);
$assertSame(
    'RAND() DESC',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields('RAND() DESC', $fields, 'msProduct', true, $dataFields),
    'snippet drop mode keeps RAND() with direction'
);
$assertSame(
    'FIELD(msProduct.id, 5, 3, 1)',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields(
        'FIELD(id, 5, 3, 1)',
        $fields,
        'msProduct',
        true,
        $dataFields,
    ),
    'snippet drop mode keeps FIELD() and qualifies bare id'
);
$assertSame(
    'IFNULL(msProduct.pagetitle, \'\') DESC',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields(
        'IFNULL(pagetitle, \'\') DESC',
        $fields,
        'msProduct',
        true,
        $dataFields,
    ),
    'snippet drop mode keeps IFNULL() and qualifies bare fields'
);
$assertSame(
    'msProduct.id',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields(
        'RAND(1), SLEEP(1)',
        $fields,
        'msProduct',
        true,
        $dataFields,
    ),
    'snippet drop mode rejects RAND with args and unsafe functions'
);

$dropped = null;
$assertSame(
    'msProduct.pagetitle',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields(
        'pagetitle, evil_injection, (SELECT 1)',
        $fields,
        'msProduct',
        true,
        $dataFields,
        'Data',
        [],
        ['msProduct', 'Data', 'Vendor'],
        $dropped,
    ),
    'snippet drop mode keeps valid parts while collecting drops'
);
$assertSame(
    ['evil_injection', '(SELECT 1)'],
    $dropped,
    'snippet drop mode reports dropped parts'
);

$assertSame(
    ['Custom', 'Vendor'],
    CatalogSortbyQualifier::tableAliasesFromJoins(
        ['Custom' => ['class' => 'X'], 'Vendor' => ['class' => 'Y']],
        ['bad key' => []],
    ),
    'join alias helper collects safe keys only'
);
$assertSame(
    'Custom.score DESC, msProduct.pagetitle',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields(
        'Custom.score DESC, pagetitle, Other.x',
        $fields,
        'msProduct',
        true,
        $dataFields,
        'Data',
        [],
        ['msProduct', 'Data', 'Vendor', 'Custom'],
    ),
    'snippet drop mode allows leftJoin aliases and rejects unknown prefixes'
);

// Declared names win over table columns: an option key or TV may be called `weight`
// just like a msProductData column, and the caller means their own join (#742 review).
$assertSame(
    'weight',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields(
        'weight',
        $fields,
        'msProduct',
        true,
        $dataFields,
        'Data',
        ['weight'],
    ),
    'sortbyOptions key shadows the Data column of the same name'
);
$assertSame(
    'Data.weight',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields(
        'weight',
        $fields,
        'msProduct',
        true,
        $dataFields,
        'Data',
        [],
    ),
    'without a declared key the Data column is still qualified'
);
$assertSame(
    'pagetitle DESC',
    CatalogSortbyQualifier::qualifyUnaliasedResourceFields(
        'pagetitle DESC',
        $fields,
        'msProduct',
        true,
        $dataFields,
        'Data',
        ['pagetitle'],
    ),
    'declared name shadows a resource column too, direction preserved'
);

fwrite(STDOUT, "OK: CatalogSortbyQualifierTest\n");
