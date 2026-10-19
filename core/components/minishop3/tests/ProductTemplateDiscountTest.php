<?php

/**
 * The product page must not rely on a $discount placeholder nobody sets (#814).
 *
 * msProducts fills row['discount'] for catalog cards, but the product page has no
 * such producer: the "Скидка {$discount}%" block rendered empty for every product.
 * The percentage is now computed with Format::discount() — the same formula the
 * catalog uses — so there is exactly one source of truth.
 *
 * Run: php tests/ProductTemplateDiscountTest.php
 */

declare(strict_types=1);

$templatePath = __DIR__ . '/../elements/templates/product.tpl';
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL ProductTemplateDiscountTest: {$message}\n");
    exit(1);
};

if (!is_readable($templatePath)) {
    $fail("Cannot read {$templatePath}");
}

$source = (string)file_get_contents($templatePath);

if (!str_contains($source, "\$ms3Service->format->discount(\$old_price, \$price)")) {
    $fail('the product page must compute the discount via Format::discount($old_price, $price) (#814)');
}

if (!str_contains($source, "{set \$discount = ")) {
    $fail('the template must assign $discount itself — nothing feeds it as a placeholder (#814)');
}

// A stale `{if $discount?}` guard would hide the badge again once $discount is 0-valued.
if (str_contains($source, '{if $discount?}')) {
    $fail('the discount block must be guarded by a value check, not by placeholder existence (#814)');
}

if (!str_contains($source, 'Скидка {$discount}%')) {
    $fail('the discount badge must stay in the template');
}

// The guard must stay inside the old-price branch: no old price — no discount.
$oldPriceBranch = strpos($source, '{if $old_price? && $old_price > 0}');
$discountSet = strpos($source, '{set $discount = ');
$branchEnd = strpos($source, "\n                            {/if}", (int)$oldPriceBranch);
if ($oldPriceBranch === false || $discountSet === false || $branchEnd === false) {
    $fail('cannot locate the price block in product.tpl');
}
if ($discountSet < $oldPriceBranch || $discountSet > $branchEnd) {
    $fail('the discount must be computed inside the old-price branch (#814)');
}

fwrite(STDOUT, "OK ProductTemplateDiscountTest\n");
exit(0);