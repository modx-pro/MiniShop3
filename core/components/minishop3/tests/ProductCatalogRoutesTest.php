<?php

/**
 * Smoke: public product resolve route registered in web.php (#579).
 *
 * Run: php tests/ProductCatalogRoutesTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$webRoutes = file_get_contents(__DIR__ . '/../config/routes/web.php');
$controllerSrc = file_get_contents(__DIR__ . '/../src/Controllers/Api/Web/ProductController.php');
$serviceSrc = file_get_contents(__DIR__ . '/../src/Services/Product/ProductCatalogService.php');

if ($webRoutes === false || $controllerSrc === false || $serviceSrc === false) {
    $fail('unable to read source files');
}

foreach (
    [
        "group('/product'",
        "get('/get',",
        "get('/get/{id}'",
        'ProductController',
        '->resolve($params)',
    ] as $needle
) {
    if (!str_contains($webRoutes, $needle)) {
        $fail("web.php missing: {$needle}");
    }
}

foreach (['function get(', 'function resolve('] as $method) {
    if (!str_contains($controllerSrc, $method)) {
        $fail("ProductController missing {$method}");
    }
}

if (!str_contains($controllerSrc, 'CatalogResolve::parseLookup')) {
    $fail('ProductController must parse catalog lookup via CatalogResolve');
}

if (!str_contains($controllerSrc, 'CatalogContextException')) {
    $fail('ProductController resolve must map CatalogContextException');
}

if (!str_contains($controllerSrc, 'CatalogResolve::lookupErrorLexiconKey')) {
    $fail('ProductController must map catalog lookup parse errors via CatalogResolve');
}

if (!str_contains($serviceSrc, 'resolveByLookup')) {
    $fail('ProductCatalogService must implement resolveByLookup');
}

if (!str_contains($serviceSrc, "'hidemenu' => 0")) {
    $fail('ProductCatalogService publicCriteria must enforce hidemenu=0');
}

$resolveSrc = file_get_contents(__DIR__ . '/../src/Services/Catalog/CatalogResolve.php');
if ($resolveSrc === false) {
    $fail('unable to read CatalogResolve.php');
}
if (!str_contains($resolveSrc, "->select('id')")) {
    $fail('CatalogResolve::findUniqueId must select id without FQCN SQL alias');
}
if (preg_match('/getSelectColumns\s*\(\s*\$class\s*,\s*\$class\b/', $resolveSrc)) {
    $fail('CatalogResolve must not pass FQCN as getSelectColumns table alias');
}
if (!str_contains($resolveSrc, 'CatalogQuery::resolveContext')) {
    $fail('CatalogResolve must validate context via CatalogQuery (#705)');
}
if (preg_match('/private static function sanitizeContext\b/', $resolveSrc)) {
    $fail('CatalogResolve must not keep a private sanitizeContext duplicate (#705)');
}

fwrite(STDOUT, "OK ProductCatalogRoutesTest\n");
exit(0);
