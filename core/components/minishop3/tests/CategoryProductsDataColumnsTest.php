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

$sortSrc = file_get_contents(__DIR__ . '/../src/Services/Category/CategoryProductSort.php');
if ($sortSrc === false) {
    $fail('unable to read CategoryProductSort.php');
}

if (!str_contains($listSrc, 'CategoryProductSort::expression(')) {
    $fail('mapSortField must delegate sort resolution to CategoryProductSort');
}

if (str_contains($listSrc, 'return "msProduct.{$sortBy}"') || str_contains($sortSrc, 'return "msProduct.{$sortBy}"')) {
    $fail('sort must not interpolate caller input into msProduct.{sort}');
}

if (!str_contains($sortSrc, 'return self::builtinExpression($sortBy) ?? $effectiveMenuindexSql;')) {
    $fail('unknown sort must fall back to the effective menuindex expression');
}

if (!str_contains($sortSrc, 'GridColumnRules::matchesSqlIdentifierPattern($sortBy)')
    || !str_contains($sortSrc, 'return "Data.`{$sortBy}`"')
) {
    $fail('extra data sort columns must be qualified as Data.{name} after the identifier check');
}

fwrite(STDOUT, "OK CategoryProductsDataColumnsTest\n");
exit(0);
