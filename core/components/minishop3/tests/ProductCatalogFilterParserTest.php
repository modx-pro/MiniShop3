<?php

/**
 * Static checks for public product/list filter parser (#564).
 *
 * Run: php tests/ProductCatalogFilterParserTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Product\ProductCatalogFilterException;
use MiniShop3\Services\Product\ProductCatalogFilterParser;
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

$assertThrows = static function (string $lexiconKey, callable $fn, string $case) use ($fail): void {
    try {
        $fn();
        $fail($case . ': expected ProductCatalogFilterException');
    } catch (ProductCatalogFilterException $e) {
        if ($e->getLexiconKey() !== $lexiconKey) {
            $fail($case . ': expected lexicon ' . $lexiconKey . ', got ' . $e->getLexiconKey());
        }
    }
};

// Empty → no filters (regress path)
$empty = ProductCatalogFilterParser::parse([]);
$assertSame([], $empty->parentIds, 'empty parents');
$assertSame(false, $empty->nested, 'empty nested');
$assertSame(null, $empty->priceMin, 'empty price_min');
$assertSame(false, $empty->hasParents(), 'empty hasParents');
$assertSame(false, $empty->hasDataFilters(), 'empty hasDataFilters');

// parents CSV + nested
$spec = ProductCatalogFilterParser::parse([
    'parents' => '12,15,12',
    'nested' => '1',
    'price_min' => '100',
    'price_max' => '5000',
    'in_stock' => '1',
    'stock_min' => '2',
    'vendor_id' => '3,7',
    'new' => '1',
    'popular' => '0',
    'favorite' => 'yes',
    'options' => '{"color":["red","blue"],"size":"M"}',
]);
$assertSame([12, 15], $spec->parentIds, 'parents unique');
$assertSame(true, $spec->nested, 'nested');
$assertSame(100.0, $spec->priceMin, 'price_min');
$assertSame(5000.0, $spec->priceMax, 'price_max');
$assertSame(true, $spec->inStock, 'in_stock');
$assertSame(2, $spec->stockMin, 'stock_min');
$assertSame([3, 7], $spec->vendorIds, 'vendor_id');
$assertSame(true, $spec->flagNew, 'new');
$assertSame(false, $spec->flagPopular, 'popular=0 ignored');
$assertSame(true, $spec->flagFavorite, 'favorite');
$assertSame(['color' => ['red', 'blue'], 'size' => ['M']], $spec->options, 'options JSON');

// bracket-style options array
$bracket = ProductCatalogFilterParser::parse([
    'options' => [
        'color' => 'red,blue',
        'size' => ['M', 'L'],
    ],
]);
$assertSame(['color' => ['red', 'blue'], 'size' => ['M', 'L']], $bracket->options, 'options bracket');

// empty options object ignored
$assertSame([], ProductCatalogFilterParser::parse(['options' => '{}'])->options, 'empty options');

// Rejects
$assertThrows('ms3_err_catalog_price_range', static fn () => ProductCatalogFilterParser::parse([
    'price_min' => 100,
    'price_max' => 50,
]), 'price range');

$assertThrows('ms3_err_catalog_price_invalid', static fn () => ProductCatalogFilterParser::parse([
    'price_min' => 'abc',
]), 'price invalid');

$assertThrows('ms3_err_catalog_vendor_invalid', static fn () => ProductCatalogFilterParser::parse([
    'vendor_id' => '3,x',
]), 'vendor invalid');

$assertThrows('ms3_err_catalog_parents_invalid', static fn () => ProductCatalogFilterParser::parse([
    'parents' => '12,foo',
]), 'parents invalid');

$assertThrows('ms3_err_catalog_options_json', static fn () => ProductCatalogFilterParser::parse([
    'options' => 'not-json',
]), 'options json');

$assertThrows('ms3_err_catalog_option_key_invalid', static fn () => ProductCatalogFilterParser::parse([
    'options' => ['bad-key!' => ['x']],
]), 'option key');

$assertThrows('ms3_err_catalog_options_limit', static fn () => ProductCatalogFilterParser::parse([
    'options' => array_fill_keys(
        array_map(static fn (int $i): string => 'k' . $i, range(1, ProductCatalogFilterParser::MAX_OPTION_KEYS + 1)),
        ['v']
    ),
]), 'option keys limit');

$assertThrows('ms3_err_catalog_parents_limit', static fn () => ProductCatalogFilterParser::parse([
    'parents' => implode(',', range(1, ProductCatalogFilterParser::MAX_PARENT_IDS + 1)),
]), 'parents limit');

$assertThrows('ms3_err_catalog_parents_invalid', static fn () => ProductCatalogFilterParser::parse([
    'parents' => '-12,-5',
]), 'parents only negatives');

$assertThrows('ms3_err_catalog_stock_invalid', static fn () => ProductCatalogFilterParser::parse([
    'stock_min' => -1,
]), 'stock_min negative');

// Scope where helper (members OR parent) — regress semantics used by applier
$where = \MiniShop3\Services\Category\CategoryProductScopeService::buildProductCategoryScopeWhere([12, 15], [99]);
$assertSame([12, 15], $where['msProduct.parent:IN'], 'scope parents');
$assertSame([99], $where['OR:msProduct.id:IN'], 'scope members');

$whereOnly = \MiniShop3\Services\Category\CategoryProductScopeService::buildProductCategoryScopeWhere([12], []);
$assertSame(['msProduct.parent:IN' => [12]], $whereOnly, 'scope without members');

// Empty parents / vendor params stay no-op (BC)
$assertSame([], ProductCatalogFilterParser::parse(['parents' => ''])->parentIds, 'empty parents string');
$assertSame([], ProductCatalogFilterParser::parse(['parents' => '0'])->parentIds, 'parents=0');

// Existing catalog helpers still green
$assertSame(20, ProductCatalogService::resolveLimit([]), 'limit regress');

fwrite(STDOUT, "OK ProductCatalogFilterParserTest\n");
exit(0);
