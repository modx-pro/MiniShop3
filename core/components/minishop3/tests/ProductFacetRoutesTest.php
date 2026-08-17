<?php

/**
 * Smoke: product/filters wiring + public route + lexicons (#565).
 *
 * Run: php tests/ProductFacetRoutesTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$routes = file_get_contents(__DIR__ . '/../config/routes/web.php');
$controller = file_get_contents(__DIR__ . '/../src/Controllers/Api/Web/ProductController.php');
$facet = file_get_contents(__DIR__ . '/../src/Services/Product/ProductFacetService.php');
$catalog = file_get_contents(__DIR__ . '/../src/Services/Product/ProductCatalogService.php');
$middleware = file_get_contents(__DIR__ . '/../src/Middleware/TokenMiddleware.php');
$registry = file_get_contents(__DIR__ . '/../src/ServiceRegistry.php');
$factories = file_get_contents(__DIR__ . '/../src/ServiceRegistryFactories.php');
$en = file_get_contents(__DIR__ . '/../lexicon/en/default.inc.php');
$ru = file_get_contents(__DIR__ . '/../lexicon/ru/default.inc.php');

foreach (
    [
        'routes' => $routes,
        'controller' => $controller,
        'facet' => $facet,
        'catalog' => $catalog,
        'middleware' => $middleware,
        'registry' => $registry,
        'factories' => $factories,
        'en' => $en,
        'ru' => $ru,
    ] as $label => $src
) {
    if ($src === false || $src === '') {
        $fail("unable to read {$label}");
    }
}

if (!str_contains($routes, "get('/filters'")) {
    $fail('web.php missing GET /product/filters');
}
if (!str_contains($routes, '->filters($params)')) {
    $fail('web.php must call ProductController::filters');
}
if (!str_contains($routes, "get('/list'")) {
    $fail('product/list route must remain');
}

if (!str_contains($controller, 'function filters(')) {
    $fail('ProductController missing filters()');
}
if (!str_contains($controller, 'ms3_product_facets')) {
    $fail('ProductController must resolve ms3_product_facets');
}
if (!str_contains($controller, 'function getList(')) {
    $fail('ProductController::getList must remain');
}

foreach (
    [
        'withoutPriceBounds()',
        'withoutOptionKey(',
        'withoutVendors()',
        'buildScopedListQuery(',
        'assertKnownOptionKeys(',
        'FacetOpt',
        '$cacheable',
        'CACHE_TTL_SECONDS',
        'MAX_FACET_KEYS',
        'MAX_VALUES_PER_KEY',
    ] as $needle
) {
    if (!str_contains($facet, $needle)) {
        $fail("ProductFacetService missing: {$needle}");
    }
}

if (str_contains($facet, 'collectProductIds') || str_contains($facet, 'queryFailed')) {
    $fail('ProductFacetService must not materialize product IDs or use queryFailed flag');
}

$migration = file_get_contents(
    __DIR__ . '/../migrations/20260817130105_add_product_options_composite_indexes.php'
);
$metaMap = file_get_contents(__DIR__ . '/../src/Model/mysql/msProductOption.php');
if ($migration === false || $metaMap === false) {
    $fail('unable to read product_options index migration/metaMap');
}
foreach (['key_product_id', 'product_id_key'] as $indexName) {
    if (!str_contains($migration, $indexName) || !str_contains($metaMap, "'{$indexName}'")) {
        $fail("composite index {$indexName} missing in migration or msProductOption metaMap");
    }
}

if (!str_contains($catalog, 'function buildScopedListQuery(')) {
    $fail('ProductCatalogService must expose buildScopedListQuery');
}

if (!preg_match('/private array \$publicRoutes\s*=\s*\[(.*?)\];/s', $middleware, $match)) {
    $fail('publicRoutes array not found');
}
if (!str_contains($match[1], "'/api/v1/product/filters'")) {
    $fail('TokenMiddleware publicRoutes missing /api/v1/product/filters');
}

if (!str_contains($registry, "'ms3_product_facets'")) {
    $fail('ServiceRegistry missing ms3_product_facets');
}
if (!str_contains($factories, "'ms3_product_facets'")) {
    $fail('ServiceRegistryFactories missing ms3_product_facets');
}

foreach (
    [
        'ms3_err_catalog_facet_keys_invalid',
        'ms3_err_catalog_facet_keys_limit',
    ] as $key
) {
    if (!str_contains($en, $key) || !str_contains($ru, $key)) {
        $fail("lexicon missing {$key} in en/ru");
    }
}

fwrite(STDOUT, "OK ProductFacetRoutesTest\n");
exit(0);
