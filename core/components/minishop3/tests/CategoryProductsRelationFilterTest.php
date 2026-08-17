<?php

/**
 * #582: relation column filters use filter_{field} (PHP + Vue grid contract).
 *
 * Run: php tests/CategoryProductsRelationFilterTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$listSrc = file_get_contents(__DIR__ . '/../src/Services/Category/CategoryProductsListService.php');
$vueSrc = file_get_contents(__DIR__ . '/../../../../vueManager/src/components/CategoryProductsGrid.vue');

if ($listSrc === false) {
    $fail('unable to read CategoryProductsListService.php');
}
if ($vueSrc === false) {
    $fail('unable to read CategoryProductsGrid.vue');
}

if (!str_contains($listSrc, 'foreach ($optionSpecs as $spec)')) {
    $fail('optionSpecs filter loop missing (regression)');
}

if (!preg_match(
    '/foreach\s*\(\s*\$relationSpecs\s+as\s+\$spec\s*\)\s*\{[^}]*filter_[^}]*sortExpression\(\)[^}]*:LIKE/s',
    $listSrc
)) {
    $fail('relationSpecs foreach must apply filter_{field} via sortExpression() LIKE');
}

// Manager grid must prefix both option and relation filters for the backend contract.
if (!preg_match(
    "/col\.type\s*===\s*'option'\s*\|\|\s*col\.type\s*===\s*'relation'/",
    $vueSrc
) || !str_contains($vueSrc, 'filter_${key}')) {
    $fail('CategoryProductsGrid must send filter_{key} for option and relation columns');
}

fwrite(STDOUT, "OK CategoryProductsRelationFilterTest\n");
exit(0);
