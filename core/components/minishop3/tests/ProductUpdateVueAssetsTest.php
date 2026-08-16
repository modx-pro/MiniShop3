<?php

/**
 * Контракт #503: product/update не подключает отсутствующий vue-dist/main.min.css.
 *
 * Запуск: php tests/ProductUpdateVueAssetsTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$base = dirname(__DIR__);
$controller = file_get_contents($base . '/controllers/product/update.class.php');
if ($controller === false) {
    $fail('cannot read controllers/product/update.class.php');
}

if (preg_match('/addCss\([^;]*main\.min\.css/', $controller) === 1) {
    $fail('product/update must not call addCss(...main.min.css) (#503)');
}

if (preg_match('/addVueModule\([^;]*main\.min\.js/', $controller) === 1) {
    $fail('product/update must not call addVueModule(...main.min.js) (unused; product-tabs covers UI)');
}

if (preg_match('/addVueModule\([^;]*product-tabs\.min\.js/', $controller) !== 1) {
    $fail('product/update must still register product-tabs.min.js');
}

if (preg_match('/addCss\([^;]*primeicons\.min\.css/', $controller) !== 1) {
    $fail('product/update must still register primeicons.min.css');
}

if (preg_match('/addCss\([^;]*ResourceCategoryTree\.min\.css/', $controller) !== 1) {
    $fail('product/update must load ResourceCategoryTree.min.css (shared Vite chunk, #555)');
}

fwrite(STDOUT, "OK ProductUpdateVueAssetsTest\n");
exit(0);
