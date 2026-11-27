<?php

/**
 * #845: ISO currency code is configurable; schema.org markup and payment attempt
 * log no longer assume RUB unconditionally.
 *
 * Standalone (no MODX). Run: php tests/CurrencyCodeSettingTest.php
 */

declare(strict_types=1);

$component = dirname(__DIR__);
$root = dirname($component, 3);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertTrue = static function (bool $condition, string $case) use ($fail): void {
    if (!$condition) {
        $fail($case);
    }
};

$settings = (string) file_get_contents($root . '/_build/elements/settings.php');
$assertTrue(
    str_contains($settings, "'ms3_currency_code' => [\n        'value' => 'RUB',"),
    'settings.php declares ms3_currency_code with the RUB default'
);

foreach (['ru', 'en'] as $lang) {
    $lexicon = (string) file_get_contents($component . "/lexicon/{$lang}/setting.inc.php");
    $assertTrue(
        str_contains($lexicon, "\$_lang['setting_ms3_currency_code']"),
        "{$lang} lexicon has setting_ms3_currency_code"
    );
    $assertTrue(
        str_contains($lexicon, "\$_lang['setting_ms3_currency_code_desc']"),
        "{$lang} lexicon has setting_ms3_currency_code_desc"
    );
}

$chunk = (string) file_get_contents($component . '/elements/chunks/ms3_products_row.tpl');
$assertTrue(str_contains($chunk, "{set \$currency_code = 'ms3_currency_code' | option}"), 'chunk reads the currency code setting');
$assertTrue(str_contains($chunk, 'content="{$currency_code | e}"'), 'priceCurrency uses the setting');
$assertTrue(!str_contains($chunk, 'priceCurrency" content="RUB"'), 'priceCurrency is not hardcoded');

$service = (string) file_get_contents($component . '/src/Services/Payment/PaymentService.php');
$assertTrue(
    str_contains($service, "\$this->modx->getOption('ms3_currency_code', null, 'RUB')"),
    'payment attempt currency falls back to the configured code'
);

$payment = (string) file_get_contents($component . '/src/Controllers/Payment/Payment.php');
$assertTrue(
    str_contains($payment, "'currency' => \$modx->getOption('ms3_currency_code', null, 'RUB')"),
    'provider example uses the currency code setting'
);

fwrite(STDOUT, "OK CurrencyCodeSettingTest\n");
exit(0);
