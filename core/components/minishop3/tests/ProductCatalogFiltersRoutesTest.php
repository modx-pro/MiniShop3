<?php

/**
 * Smoke: product/list filter wiring + lexicon keys (#564).
 *
 * Run: php tests/ProductCatalogFiltersRoutesTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$serviceSrc = file_get_contents(__DIR__ . '/../src/Services/Product/ProductCatalogService.php');
$controllerSrc = file_get_contents(__DIR__ . '/../src/Controllers/Api/Web/ProductController.php');
$applierSrc = file_get_contents(__DIR__ . '/../src/Services/Product/ProductCatalogFilterApplier.php');
$en = file_get_contents(__DIR__ . '/../lexicon/en/default.inc.php');
$ru = file_get_contents(__DIR__ . '/../lexicon/ru/default.inc.php');

if ($serviceSrc === false || $controllerSrc === false || $applierSrc === false || $en === false || $ru === false) {
    $fail('unable to read source files');
}

foreach (
    [
        'ProductCatalogFilterParser::parse',
        'filterApplier()',
        'hasParents()',
        'buildListQuery($params, $filters, false)',
    ] as $needle
) {
    if (!str_contains($serviceSrc, $needle)) {
        $fail("ProductCatalogService missing: {$needle}");
    }
}

if (!str_contains($controllerSrc, 'ProductCatalogFilterException')) {
    $fail('ProductController must catch ProductCatalogFilterException');
}

if (!str_contains($controllerSrc, 'HttpStatus::BAD_REQUEST')) {
    $fail('ProductController must map filter errors to 400');
}

foreach (
    [
        'applyProductCategoryScope',
        'msProductOption',
        'assertKnownOptionKeys',
        'Data.price',
        'Data.stock',
        "groupby('msProduct.id')",
        '$dedupeRows',
    ] as $needle
) {
    if (!str_contains($applierSrc, $needle)) {
        $fail("ProductCatalogFilterApplier missing: {$needle}");
    }
}

$keys = [
    'ms3_err_catalog_parents_invalid',
    'ms3_err_catalog_price_range',
    'ms3_err_catalog_option_unknown',
    'ms3_err_catalog_options_json',
];

foreach ($keys as $key) {
    if (!str_contains($en, $key) || !str_contains($ru, $key)) {
        $fail("lexicon missing {$key} in en/ru");
    }
}

fwrite(STDOUT, "OK ProductCatalogFiltersRoutesTest\n");
exit(0);
