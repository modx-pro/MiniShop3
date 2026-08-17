<?php

/**
 * #575: ProductCatalogService list avoids N+1 Data and useless COUNT DISTINCT.
 *
 * Run: php tests/ProductCatalogPerfTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$src = file_get_contents(__DIR__ . '/../src/Services/Product/ProductCatalogService.php');
$modelSrc = file_get_contents(__DIR__ . '/../src/Model/msProduct.php');
if ($src === false || $modelSrc === false) {
    $fail('unable to read source files');
}

if (str_contains($modelSrc, 'function attachData')) {
    $fail('do not add attachData on msProduct — use addOne($data, \'Data\')');
}

if (!str_contains($src, "addOne(\$attached, 'Data')") && !str_contains($src, 'addOne($attached, "Data")')) {
    $fail('prefetch must attach Data via addOne(..., Data)');
}

if (!str_contains($src, "where(['id:IN'")) {
    $fail('Data prefetch must use id:IN batch query');
}

if (!str_contains($src, "select('COUNT(msProduct.id)')") && !str_contains($src, 'COUNT(msProduct.id)')) {
    $fail('countList must COUNT(msProduct.id) for the 1:1 Data path');
}

if (str_contains($src, 'COUNT(DISTINCT msProduct.id)') && !str_contains($src, "options !== []")) {
    $fail('COUNT(DISTINCT) must be gated to option-filter joins only');
}

if (!preg_match(
    "/getSelectColumns\(\s*msProduct::class\s*,\s*'msProduct'\s*,\s*''\s*,\s*\['content'\]\s*,\s*true\s*\)/",
    $src
)) {
    $fail('include_content=0 must exclude content via getSelectColumns(..., [content], true)');
}

// Prefetch must run on getList path before format (batch, not per-row getOne).
if (!preg_match('/function getList[\s\S]*?id:IN[\s\S]*?formatProduct/m', $src)
    && !preg_match('/function getList[\s\S]*?prefetchAndAttachProductData[\s\S]*?formatProduct/m', $src)
) {
    $fail('getList must batch-load Data before formatProduct');
}

fwrite(STDOUT, "OK ProductCatalogPerfTest\n");
exit(0);
