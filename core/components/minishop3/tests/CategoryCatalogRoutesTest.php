<?php

/**
 * Smoke: public category routes registered in web.php (#563).
 *
 * Run: php tests/CategoryCatalogRoutesTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$webRoutes = file_get_contents(__DIR__ . '/../config/routes/web.php');
$controllerSrc = file_get_contents(__DIR__ . '/../src/Controllers/Api/Web/CategoryController.php');
$serviceSrc = file_get_contents(__DIR__ . '/../src/Services/Category/CategoryCatalogService.php');

if ($webRoutes === false || $controllerSrc === false || $serviceSrc === false) {
    $fail('unable to read source files');
}

foreach (
    [
        "group('/category'",
        "get('/get/{id}'",
        "get('/list'",
        "get('/tree'",
        'CategoryController',
    ] as $needle
) {
    if (!str_contains($webRoutes, $needle)) {
        $fail("web.php missing: {$needle}");
    }
}

foreach (['function get(', 'function getList(', 'function getTree('] as $method) {
    if (!str_contains($controllerSrc, $method)) {
        $fail("CategoryController missing {$method}");
    }
}

if (!str_contains($controllerSrc, 'ms3_category_catalog')) {
    $fail('CategoryController must resolve ms3_category_catalog');
}

if (!str_contains($serviceSrc, 'class_key') || !str_contains($serviceSrc, 'published')) {
    $fail('CategoryCatalogService must scope published msCategory');
}

if (!str_contains($serviceSrc, 'hidemenu')) {
    $fail('CategoryCatalogService must handle hidemenu / include_hidden');
}

if (!preg_match('/function buildBreadcrumbs.*?visibilityCriteria\s*\(/s', $serviceSrc)) {
    $fail('buildBreadcrumbs must apply visibilityCriteria (hidemenu + context)');
}

if (!preg_match('/function getList.*?findVisibleCategory\s*\(/s', $serviceSrc)) {
    $fail('getList must refuse invisible parent');
}

if (!preg_match('/function getTree.*?findVisibleCategory\s*\(/s', $serviceSrc)) {
    $fail('getTree must refuse invisible parent');
}

if (!preg_match('/function getTree.*?loadTreeWindowRows\s*\(/s', $serviceSrc)) {
    $fail('getTree must load a parent+depth window, not the full context');
}

if (!str_contains($serviceSrc, 'MAX_TREE_NODES') || !str_contains($serviceSrc, 'parent:IN')) {
    $fail('tree loader must cap nodes and filter by parent:IN levels');
}

foreach (
    [
        "'published' => 1",
        "'deleted' => 0",
        'msCategory::class',
    ] as $needle
) {
    if (!str_contains($serviceSrc, $needle)) {
        $fail("visibility criteria missing: {$needle}");
    }
}

fwrite(STDOUT, "OK CategoryCatalogRoutesTest\n");
exit(0);
