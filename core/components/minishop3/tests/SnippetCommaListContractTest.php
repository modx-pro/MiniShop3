<?php

/**
 * #842: leading commas must not break wildcard/field-list parsing in any
 * storefront snippet, and product objects must never be cast to int directly.
 *
 * Run: php tests/SnippetCommaListContractTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$dir = $root . '/core/components/minishop3/elements/snippets/';
$order = (string) file_get_contents($dir . 'ms3_order.php');
$products = (string) file_get_contents($dir . 'ms3_products.php');
foreach (['order' => $order, 'products' => $products] as $name => $source) {
    if (!str_contains($source, 'CommaList::parse')) {
        $fail("{$name} snippet must parse CSV params with CommaList (#842)");
    }
}

foreach (['ms3_product_options.php', 'ms3_options.php', 'ms3_gallery.php'] as $file) {
    $source = (string) file_get_contents($dir . $file);
    if (!str_contains($source, 'SnippetProductResolver::resolveId')) {
        $fail("{$file} must resolve &product without casting an object to int (#842)");
    }
}

fwrite(STDOUT, "OK SnippetCommaListContractTest (#842)\n");
exit(0);
