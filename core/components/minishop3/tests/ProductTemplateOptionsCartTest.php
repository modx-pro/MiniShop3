<?php

/**
 * #815: product.tpl option buttons must write the selected values into the
 * cart/add form (options[color] / options[size]), not only toggle a CSS class.
 *
 * Standalone (no MODX). Run: php tests/ProductTemplateOptionsCartTest.php
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

$template = (string) file_get_contents($component . '/elements/templates/product.tpl');

$assertTrue(str_contains($template, 'data-option-key="color"'), 'color group declares its option key');
$assertTrue(str_contains($template, 'data-option-key="size"'), 'size group declares its option key');
$assertTrue(str_contains($template, 'data-option-value="{$colorOption | e}"'), 'color buttons carry their value');
$assertTrue(str_contains($template, 'data-option-value="{$sizeOption | e}"'), 'size buttons carry their value');

$addForm = substr($template, (int) strpos($template, 'data-cart-state="add"'));
$addForm = substr($addForm, 0, (int) strpos($addForm, '</form>'));
$assertTrue(str_contains($addForm, 'name="options[color]"'), 'add form has the color option input');
$assertTrue(str_contains($addForm, 'name="options[size]"'), 'add form has the size option input');
$assertTrue(str_contains($addForm, 'name="ms3_action" value="cart/add"'), 'inputs belong to the cart/add form');

$assertTrue(
    str_contains($template, "document.querySelector('input[name=\"options[' + key + ']\"]')"),
    'selection JS locates the option input'
);
$assertTrue(
    str_contains($template, "input.value = btn.dataset.optionValue || ''"),
    'selection JS stores the chosen option value'
);
$assertTrue(
    str_contains($template, "b.classList.toggle('active', b === btn)"),
    'selection JS keeps exactly one active button per group'
);

$collectOptions = (string) file_get_contents(
    dirname($component, 3) . '/assets/components/minishop3/js/web/ms3.js'
);
$assertTrue(
    str_contains($collectOptions, "key.match(/^options\\[([^\\]]+)\\]$/)"),
    'ms3.js collects options[key] FormData entries'
);

fwrite(STDOUT, "OK ProductTemplateOptionsCartTest\n");
exit(0);
