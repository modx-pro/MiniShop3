<?php

/**
 * Static checks for public catalog query helpers (without MODX).
 *
 * Run: php tests/ProductCatalogServiceTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Product\ProductCatalogService;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertSame(20, ProductCatalogService::resolveLimit([]), 'default limit');
$assertSame(20, ProductCatalogService::resolveLimit(['limit' => 0]), 'invalid limit falls back');
$assertSame(50, ProductCatalogService::resolveLimit(['limit' => 50]), 'custom limit');
$assertSame(100, ProductCatalogService::resolveLimit(['limit' => 500]), 'limit capped at 100');

$assertSame(0, ProductCatalogService::resolveOffset([], 20), 'default offset');
$assertSame(40, ProductCatalogService::resolveOffset(['page' => 3], 20), 'page to offset');
$assertSame(0, ProductCatalogService::resolveOffset(['page' => 0], 20), 'page < 1 → first page');
$assertSame(15, ProductCatalogService::resolveOffset(['offset' => 15], 20), 'explicit offset');
$assertSame(0, ProductCatalogService::resolveOffset(['offset' => -5], 20), 'negative offset clamped');
$assertSame(30, ProductCatalogService::resolveOffset(['offset' => 30, 'page' => 9], 20), 'offset wins over page');

[$field, $dir] = ProductCatalogService::resolveSort([]);
$assertSame('msProduct.menuindex', $field, 'default sort field');
$assertSame('ASC', $dir, 'default sort dir');

[$field, $dir] = ProductCatalogService::resolveSort(['sort' => 'price', 'dir' => 'desc']);
$assertSame('Data.price', $field, 'price sort field');
$assertSame('DESC', $dir, 'desc dir');

[$field, $dir] = ProductCatalogService::resolveSort(['sort' => 'unknown', 'sortdir' => 'DESC']);
$assertSame('msProduct.menuindex', $field, 'unknown sort → menuindex');
$assertSame('DESC', $dir, 'sortdir alias');

[$field, $dir] = ProductCatalogService::resolveSort(['sort' => 'id', 'dir' => 'sideways']);
$assertSame('msProduct.id', $field, 'id sort');
$assertSame('ASC', $dir, 'invalid dir → ASC');

$assertSame(true, ProductCatalogService::toBool(true), 'bool true');
$assertSame(false, ProductCatalogService::toBool(false), 'bool false');
$assertSame(true, ProductCatalogService::toBool('1'), 'string 1');
$assertSame(true, ProductCatalogService::toBool('yes'), 'string yes');
$assertSame(false, ProductCatalogService::toBool('0'), 'string 0');
$assertSame(false, ProductCatalogService::toBool('no'), 'string no');

$assertSame(
    ['color' => ['Red'], 'size' => ['M']],
    ProductCatalogService::stripOptionMetadata([
        'color' => ['Red'],
        'color.caption' => 'Color',
        'size' => ['M'],
        'size.description' => 'Size chart',
        0 => 'orphan',
    ]),
    'strip dotted option metadata'
);

$assertSame(
    [
        'id' => 1,
        'pagetitle' => 'Tea',
        'price' => 10.5,
        'content' => '<p>x</p>',
        'options' => ['color' => ['Red']],
    ],
    ProductCatalogService::whitelistPublicPayload([
        'id' => 1,
        'pagetitle' => 'Tea',
        'price' => 10.5,
        'content' => '<p>x</p>',
        'options' => ['color' => ['Red'], 'color.caption' => 'Color'],
        'internal_secret' => 'leak',
        'template' => 5,
    ], true, true),
    'whitelist drops unknown keys, keeps content/options'
);

$assertSame(
    [
        'id' => 2,
        'pagetitle' => 'Coffee',
    ],
    ProductCatalogService::whitelistPublicPayload([
        'id' => 2,
        'pagetitle' => 'Coffee',
        'content' => '<p>hidden</p>',
        'options' => ['size' => ['L']],
        'tv_private' => 'x',
    ], false, false),
    'whitelist omits content/options when flags off'
);

fwrite(STDOUT, "OK ProductCatalogServiceTest\n");
exit(0);
