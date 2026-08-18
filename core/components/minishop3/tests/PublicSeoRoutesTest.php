<?php

/**
 * Smoke: public seo block wiring for product/category get (#567).
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

if (!str_contains($productCatalog, 'maybeAttachProduct')) {
    $fail('product getById must attach product seo');
}
if (!str_contains($categoryCatalog, 'maybeAttachCategory')) {
    $fail('category getById must attach category seo');
}

if (substr_count($productCatalog, 'maybeAttachProduct') !== 1) {
    $fail('product catalog must attach seo only once (get)');
}
if (substr_count($categoryCatalog, 'maybeAttachCategory') !== 1) {
    $fail('category catalog must attach seo only once (get)');
}

if (str_contains($productCatalog, 'PublicSeoBuilder')) {
    $fail('product catalog must not import PublicSeoBuilder');
}
if (str_contains($categoryCatalog, 'PublicSeoBuilder')) {
    $fail('category catalog must not import PublicSeoBuilder');
}

if (preg_match('/function getList.*?maybeAttach/s', $productCatalog)) {
    $fail('product getList must not attach seo');
}
if (preg_match('/function getList.*?maybeAttach/s', $categoryCatalog)) {
    $fail('category getList must not attach seo');
}
if (preg_match('/function getTree.*?maybeAttach/s', $categoryCatalog)) {
    $fail('category getTree must not attach seo');
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
