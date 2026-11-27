<?php

/**
 * #818: demo templates must not pass the unread formatPrices parameter to msProducts.
 * Price display goes through the *_formatted placeholders prepared by the snippet
 * (withCurrency controls the currency symbol); raw prices stay in machine-readable meta.
 *
 * Standalone (no MODX). Run: php tests/TemplatePriceFormatContractTest.php
 */

declare(strict_types=1);

$component = dirname(__Dir__);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertTrue = static function (bool $condition, string $case) use ($fail): void {
    if (!$condition) {
        $fail($case);
    }
};

$elements = $component . '/elements';

$catalog = (string) file_get_contents($elements . '/templates/catalog.tpl');
$product = (string) file_get_contents($elements . '/templates/product.tpl');

$assertTrue(!str_contains($catalog, 'formatPrices'), 'catalog.tpl does not pass formatPrices to msProducts');
$assertTrue(!str_contains($product, 'formatPrices'), 'product.tpl does not pass formatPrices to related msProducts');
$assertTrue(str_contains($catalog, "'withCurrency' => 1"), 'catalog.tpl still enables currency formatting');

$snippet = (string) file_get_contents($elements . '/snippets/ms3_products.php');
$assertTrue(
    str_contains($snippet, "\$withCurrency = !empty(\$scriptProperties['withCurrency']);"),
    'msProducts reads withCurrency'
);
$assertTrue(
    str_contains($snippet, "\$row['price_formatted'] = \$ms3->format->price(\$rawPrice, \$withCurrency);"),
    'msProducts prepares price_formatted'
);
$assertTrue(
    str_contains($snippet, "\$row['old_price_formatted']"),
    'msProducts prepares old_price_formatted'
);

$chunk = (string) file_get_contents($elements . '/chunks/ms3_products_row.tpl');
$assertTrue(str_contains($chunk, '{$price_formatted}'), 'row chunk displays the formatted price');
$assertTrue(str_contains($chunk, '{$old_price_formatted}'), 'row chunk displays the formatted old price');
$assertTrue(
    str_contains($chunk, 'itemprop="price" content="{$price}"'),
    'schema.org meta keeps the raw numeric price'
);

fwrite(STDOUT, "OK TemplatePriceFormatContractTest\n");
exit(0);
