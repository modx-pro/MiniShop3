<?php

/**
 * Soft-facet Spec helpers for product/filters (#565).
 *
 * Run: php tests/ProductFacetSoftSemanticsTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Product\ProductCatalogFilterException;
use MiniShop3\Services\Product\ProductCatalogFilterParser;
use MiniShop3\Services\Product\ProductFacetService;

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

$spec = ProductCatalogFilterParser::parse([
    'price_min' => '100',
    'price_max' => '5000',
    'vendor_id' => '3,7',
    'options' => '{"color":["black"],"size":["M"]}',
]);

$priceSoft = $spec->withoutPriceBounds();
$assertSame(null, $priceSoft->priceMin, 'price soft clears min');
$assertSame(null, $priceSoft->priceMax, 'price soft clears max');
$assertSame(['color' => ['black'], 'size' => ['M']], $priceSoft->options, 'price soft keeps options');
$assertSame([3, 7], $priceSoft->vendorIds, 'price soft keeps vendors');

$colorSoft = $spec->withoutOptionKey('color');
$assertSame(['size' => ['M']], $colorSoft->options, 'option soft drops only color');
$assertSame(100.0, $colorSoft->priceMin, 'option soft keeps price_min');
$assertSame(['black'], $spec->options['color'], 'original options unchanged');

$sizeSoft = $spec->withoutOptionKey('size');
$assertSame(['color' => ['black']], $sizeSoft->options, 'option soft drops only size');

$missing = $spec->withoutOptionKey('memory');
$assertSame($spec->options, $missing->options, 'unknown option key is no-op');

$vendorSoft = $spec->withoutVendors();
$assertSame([], $vendorSoft->vendorIds, 'vendor soft clears vendor_id');
$assertSame(['color' => ['black'], 'size' => ['M']], $vendorSoft->options, 'vendor soft keeps options');

$assertSame(20, ProductFacetService::MAX_FACET_KEYS, 'max facet keys');
$assertSame(50, ProductFacetService::MAX_VALUES_PER_KEY, 'max values per key');
$assertSame(120, ProductFacetService::CACHE_TTL_SECONDS, 'cache TTL');

$reflection = new ReflectionClass(ProductFacetService::class);
$parseKeys = $reflection->getMethod('parseRequestedKeys');
$parseKeys->setAccessible(true);
$service = $reflection->newInstanceWithoutConstructor();

$assertSame(['color', 'memory'], $parseKeys->invoke($service, 'color,memory,color'), 'keys CSV unique');
$assertThrows(
    'ms3_err_catalog_facet_keys_invalid',
    static fn () => $parseKeys->invoke($service, 'bad-key!'),
    'invalid key char'
);
$assertThrows(
    'ms3_err_catalog_facet_keys_invalid',
    static fn () => $parseKeys->invoke($service, ''),
    'empty keys'
);
$assertThrows(
    'ms3_err_catalog_facet_keys_invalid',
    static fn () => $parseKeys->invoke($service, '   '),
    'whitespace keys'
);

// nested expands only with parents= (parity with product/list parent|category)
$facetSrc = file_get_contents(__DIR__ . '/../src/Services/Product/ProductFacetService.php');
if ($facetSrc === false || !str_contains($facetSrc, 'hasParents() && $filters->nested')) {
    $fail('resolveScopeCategoryIds must gate nested on hasParents()');
}
if (!str_contains($facetSrc, 'innerJoin(') || !str_contains($facetSrc, 'FacetOpt')) {
    $fail('option facets must JOIN FacetOpt (no PHP ID materialization)');
}
if (str_contains($facetSrc, 'collectProductIds')) {
    $fail('collectProductIds must be removed');
}
if (!str_contains($facetSrc, '$cacheable')) {
    $fail('must track cacheable flag for SQL failures');
}
$assertThrows(
    'ms3_err_catalog_facet_keys_limit',
    static function () use ($parseKeys, $service): void {
        $keys = [];
        for ($i = 0; $i < ProductFacetService::MAX_FACET_KEYS + 1; $i++) {
            $keys[] = 'k' . $i;
        }
        $parseKeys->invoke($service, implode(',', $keys));
    },
    'keys cap'
);

fwrite(STDOUT, "OK ProductFacetSoftSemanticsTest\n");
exit(0);
