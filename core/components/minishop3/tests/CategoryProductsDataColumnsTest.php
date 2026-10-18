<?php

/**
 * #914: category products grid reads custom msProductData columns via ProductDataColumnPolicy.
 *
 * Run: php tests/CategoryProductsDataColumnsTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$listSrc = file_get_contents(__DIR__ . '/../src/Services/Category/CategoryProductsListService.php');
$policySrc = file_get_contents(__DIR__ . '/../src/Services/Grid/ProductDataColumnPolicy.php');

if ($listSrc === false) {
    $fail('unable to read CategoryProductsListService.php');
}
if ($policySrc === false) {
    $fail('unable to read ProductDataColumnPolicy.php');
}

if (!str_contains($listSrc, 'ProductDataColumnPolicy::extraReadableColumns')) {
    $fail('getPage must resolve extra data columns via ProductDataColumnPolicy');
}

if (!preg_match(
    '/foreach\s*\(\s*array_keys\s*\(\s*\$dataColumns\s*\)\s+as\s+\$dataColumnName\s*\)\s*\{[^}]*Data\.`\{\$dataColumnName\}`/s',
    $listSrc
)) {
    $fail('SELECT must add Data.{name} for each extra data column');
}

if (!str_contains($listSrc, '$dataColumns[$sortBy]')
    || !preg_match('/isset\s*\(\s*\$dataColumns\[\$sortBy\]\s*\)[\s\S]*?return\s+"Data\.`\{\$sortBy\}`"/', $listSrc)
) {
    $fail('mapSortField must sort extra data columns on Data.{name}');
}

fwrite(STDOUT, "OK CategoryProductsDataColumnsTest\n");
exit(0);
