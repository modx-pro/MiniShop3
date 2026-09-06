<?php

/**
 * Smoke: product gallery include_images + /product/{id}/images (#566).
 *
 * Run: php tests/ProductCatalogImagesRoutesTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$catalog = file_get_contents(__DIR__ . '/../src/Services/Product/ProductCatalogService.php');
$controller = file_get_contents(__DIR__ . '/../src/Controllers/Api/Web/ProductController.php');
$service = file_get_contents(__DIR__ . '/../src/Services/Product/ProductGalleryPublicService.php');
$serializer = file_get_contents(__DIR__ . '/../src/Services/Product/ProductGalleryPublicSerializer.php');
$webRoutes = file_get_contents(__DIR__ . '/../config/routes/web.php');

foreach (
    [
        'catalog' => $catalog,
        'controller' => $controller,
        'service' => $service,
        'serializer' => $serializer,
        'webRoutes' => $webRoutes,
    ] as $label => $src
) {
    if ($src === false || $src === '') {
        $fail("unable to read {$label}");
    }
}

if (!str_contains($catalog, 'include_images')) {
    $fail('ProductCatalogService must honor include_images');
}
if (!str_contains($catalog, 'ProductGalleryPublicSerializer::whitelistItems')) {
    $fail('images must pass serializer allowlist after plugins');
}
if (!str_contains($catalog, 'resolvePreviewFileId')) {
    $fail('is_preview must use ProductImageService::resolvePreviewFileId');
}
if (!str_contains($catalog, "ms3_product_gallery_public")) {
    $fail('gallery service must come from ServiceRegistry');
}
if (!preg_match('/function getById\([\s\S]*?loadImagesForProduct/', $catalog)) {
    $fail('gallery load must run inside getById after product is found');
}
if (!preg_match('/function getList\([\s\S]*?loadImagesForProducts/', $catalog)) {
    $fail('product/list must batch-load gallery when include_images is on');
}
if (!str_contains($catalog, 'function getPublicImages')) {
    $fail('catalog must expose getPublicImages for /product/{id}/images');
}

if (!str_contains($controller, 'function getImages')) {
    $fail('ProductController must expose getImages');
}
if (!str_contains($webRoutes, "'/{id}/images'") && !str_contains($webRoutes, '"/{id}/images"')) {
    $fail('web.php must register GET /product/{id}/images');
}

$registry = file_get_contents(__DIR__ . '/../src/ServiceRegistry.php');
$factories = file_get_contents(__DIR__ . '/../src/ServiceRegistryFactories.php');
if ($registry === false || $factories === false
    || !str_contains($registry, "'ms3_product_gallery_public'")
    || !str_contains($factories, "'ms3_product_gallery_public'")) {
    $fail('ms3_product_gallery_public must be registered');
}

if (!str_contains($service, "'parent_id' => 0") || !str_contains($service, "'active' => 1")) {
    $fail('gallery query must restrict parent_id=0 and active=1');
}
if (!str_contains($service, 'parent_id:IN')) {
    $fail('thumbs must batch-load via parent_id IN');
}
if (!str_contains($service, 'ms3_product_thumbnail_size')) {
    $fail('thumb must prefer ms3_product_thumbnail_size');
}

if (!str_contains($serializer, 'no DB `alt`')) {
    $fail('serializer must document name → alt mapping');
}
if (!str_contains($serializer, "'thumbs'")) {
    $fail('serializer must expose multi-size thumbs map');
}

fwrite(STDOUT, "OK ProductCatalogImagesRoutesTest\n");
exit(0);
