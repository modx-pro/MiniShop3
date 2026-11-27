<?php

/**
 * #813: product card availability (badge and schema.org itemprop) follows stock,
 * not product weight. The weight block itself keeps using weight.
 *
 * Standalone (no MODX). Run: php tests/ProductCardAvailabilityContractTest.php
 */

declare(strict_types=1);

$component = dirname(__DIR__);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertTrue = static function (bool $condition, string $case) use ($fail): void {
    if (!$condition) {
        $fail($case);
    }
};

$chunk = (string) file_get_contents($component . '/elements/chunks/ms3_products_row.tpl');

$badge = substr($chunk, (int) strpos($chunk, '{* Статус наличия'));
$badge = substr($badge, 0, (int) strpos($badge, '{* Скидка и бейджи *}'));
$assertTrue(str_contains($badge, '{if $stock > 0}'), 'availability badge uses stock');
$assertTrue(!str_contains($badge, '$weight'), 'availability badge ignores weight');
$assertTrue(str_contains($badge, 'В наличии'), 'in-stock badge kept');
$assertTrue(str_contains($badge, 'Под заказ'), 'out-of-stock badge kept');

$availability = substr($chunk, (int) strpos($chunk, 'itemprop="offers"'));
$availability = substr($availability, 0, (int) strpos($availability, '{* Производитель и артикул'));
$assertTrue(str_contains($availability, 'http://schema.org/InStock'), 'InStock markup kept');
$assertTrue(str_contains($availability, 'http://schema.org/OutOfStock'), 'OutOfStock markup added');
$assertTrue(
    str_contains($availability, '{if $stock > 0}') && str_contains($availability, '{else}'),
    'availability itemprop is conditional on stock'
);

$weightBlock = substr($chunk, (int) strpos($chunk, '{* Дополнительная информация *}'));
$assertTrue(str_contains($weightBlock, '{if $weight > 0?}'), 'weight display still uses weight');

fwrite(STDOUT, "OK ProductCardAvailabilityContractTest\n");
exit(0);
