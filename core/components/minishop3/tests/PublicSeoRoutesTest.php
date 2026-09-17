<?php

/**
 * Smoke: public seo block wiring for product/category get (#567) and list/tree (#703).
 *
 * Run: php tests/PublicSeoRoutesTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$read = static function (string $relative) use ($fail): string {
    $path = __DIR__ . '/../' . $relative;
    $src = file_get_contents($path);
    if ($src === false) {
        $fail("unable to read {$relative}");
    }

    return $src;
};

$productCatalog = $read('src/Services/Product/ProductCatalogService.php');
$categoryCatalog = $read('src/Services/Category/CategoryCatalogService.php');
$productController = $read('src/Controllers/Api/Web/ProductController.php');
$categoryController = $read('src/Controllers/Api/Web/CategoryController.php');
$registry = $read('src/ServiceRegistry.php');
$factories = $read('src/ServiceRegistryFactories.php');
$events = $read('../../../_build/elements/events.php');
$settings = $read('../../../_build/elements/settings.php');
$composer = $read('composer.json');

if (!str_contains($registry, "'ms3_public_seo'")) {
    $fail('ServiceRegistry missing ms3_public_seo');
}
if (!str_contains($factories, "'ms3_public_seo'")) {
    $fail('ServiceRegistryFactories missing ms3_public_seo');
}
if (!str_contains($events, "'msOnGetPublicSeo'")) {
    $fail('events.php missing msOnGetPublicSeo');
}
if (!str_contains($events, 'Public catalog / SEO')) {
    $fail('events.php must document msOnGetPublicSeo under catalog/SEO section');
}
if (!str_contains($settings, "'ms3_public_seo_tv_map'")) {
    $fail('settings.php missing ms3_public_seo_tv_map');
}

if (!str_contains($productCatalog, 'maybeAttachProduct')) {
    $fail('product getById must attach product seo');
}
if (!str_contains($categoryCatalog, 'maybeAttachCategory')) {
    $fail('category getById must attach category seo');
}

if (!str_contains($productCatalog, 'attachSeoToProductList')) {
    $fail('product getList must attach seo via attachSeoToProductList (include_seo)');
}
if (!str_contains($categoryCatalog, 'attachSeoToCategoryList')) {
    $fail('category getList must attach seo via attachSeoToCategoryList (include_seo)');
}

$seoService = $read('src/Services/Seo/PublicSeoService.php');
if (!str_contains($seoService, "resolveBool(\$params, 'include_seo', false)")) {
    $fail('PublicSeoService list/tree must default include_seo to 0');
}
if (!str_contains($seoService, "resolveBool(\$params, 'include_seo', true)")) {
    $fail('PublicSeoService get (maybeAttach) must default include_seo to 1');
}

if (!preg_match('/function resolveByLookup.*?return \$this->getById\(/s', $productCatalog)) {
    $fail('product resolveByLookup must reuse getById so seo attaches');
}
if (!preg_match('/function resolveByLookup.*?return \$this->getById\(/s', $categoryCatalog)) {
    $fail('category resolveByLookup must reuse getById so seo attaches');
}

if (str_contains($productCatalog, 'PublicSeoBuilder')) {
    $fail('product catalog must not import PublicSeoBuilder');
}
if (str_contains($categoryCatalog, 'PublicSeoBuilder')) {
    $fail('category catalog must not import PublicSeoBuilder');
}

if (!str_contains($categoryCatalog, 'attachSeoToCategoryTree')) {
    $fail('category getTree must support include_seo via attachSeoToCategoryTree');
}

if (!str_contains($productCatalog, "'searchable'")) {
    $fail('product RESOURCE_FIELDS must include searchable');
}
if (!str_contains($categoryCatalog, "'searchable'")) {
    $fail('category RESOURCE_FIELDS must include searchable');
}

if (!str_contains($productController, 'include_seo')) {
    $fail('ProductController get must document include_seo');
}
if (!str_contains($categoryController, 'include_seo')) {
    $fail('CategoryController get must document include_seo');
}

foreach (['seosuite', 'stercseo', 'metax'] as $pkg) {
    if (stripos($composer, $pkg) !== false) {
        $fail("composer.json must not depend on {$pkg}");
    }
}

fwrite(STDOUT, "OK PublicSeoRoutesTest\n");
exit(0);
