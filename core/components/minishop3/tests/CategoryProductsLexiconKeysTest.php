<?php

/**
 * Category products API messages must exist in ru/en default lexicon (#446).
 *
 * Run: php tests/CategoryProductsLexiconKeysTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$keys = [
    'ms3_err_category_id_required',
    'ms3_err_category_nf',
    'ms3_err_category_products_list_service',
    'ms3_err_items_required',
    'ms3_err_method_required',
    'ms3_err_unknown_method',
    'ms3_err_access_denied_permission',
    'ms3_err_product_ids_required',
    'ms3_err_product_ids_invalid',
    'ms3_err_category_products_no_updates',
    'ms3_err_product_id_required',
    'ms3_err_product_nf',
    'ms3_err_product_update_failed',
    'ms3_category_products_reordered',
    'ms3_category_product_published',
    'ms3_category_product_unpublished',
    'ms3_category_products_updated',
];

foreach (['en', 'ru'] as $lang) {
    $path = __DIR__ . '/../lexicon/' . $lang . '/default.inc.php';
    if (!is_readable($path)) {
        $fail("Missing lexicon file: {$path}");
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        $fail("Cannot read {$path}");
    }

    foreach ($keys as $key) {
        if (!str_contains($contents, "'{$key}'")) {
            $fail("Missing {$key} in {$lang}/default.inc.php");
        }
    }
}

fwrite(STDOUT, 'OK CategoryProductsLexiconKeysTest (' . count($keys) . " keys × 2 langs)\n");
exit(0);
