<?php

/**
 * #816: storefront saved-address form and list must use the msCustomerAddress
 * allowlist (comment, optional name/building), matching the Web API.
 *
 * Standalone (no MODX). Run: php tests/CustomerAddressFormContractTest.php
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

$form = (string) file_get_contents($component . '/elements/chunks/ms3_customer_address_form.tpl');
$assertTrue(str_contains($form, 'name="comment"'), 'form posts the API comment field');
$assertTrue(str_contains($form, '{$address.comment}'), 'form renders the saved comment');
$assertTrue(!str_contains($form, 'name="text_address"'), 'form does not post the order-only text_address');
$assertTrue(!str_contains($form, '{$address.text_address}'), 'form does not read text_address');

// The building input is optional in the API: no HTML required flag.
$buildingBlock = substr($form, (int) strpos($form, 'for="building"'));
$buildingBlock = substr($buildingBlock, 0, (int) strpos($buildingBlock, '{* Подъезд *}'));
$assertTrue(!str_contains($buildingBlock, 'required'), 'building is not required in the form');
$assertTrue(!str_contains($buildingBlock, 'text-danger'), 'building has no required marker');

$cityBlock = substr($form, (int) strpos($form, 'for="city"'));
$cityBlock = substr($cityBlock, 0, (int) strpos($cityBlock, '{* Улица *}'));
$assertTrue(str_contains($cityBlock, 'required'), 'city stays required');

$streetBlock = substr($form, (int) strpos($form, 'for="street"'));
$streetBlock = substr($streetBlock, 0, (int) strpos($streetBlock, '{* Дом *}'));
$assertTrue(str_contains($streetBlock, 'required'), 'street stays required');

$row = (string) file_get_contents($component . '/elements/chunks/ms3_customer_address_row.tpl');
$assertTrue(str_contains($row, '{$comment}'), 'address row prints the comment');
$assertTrue(!str_contains($row, '{$text_address}'), 'address row does not use text_address');

$controller = (string) file_get_contents(
    $component . '/src/Controllers/Api/Web/CustomerAddressController.php'
);
$assertTrue(
    str_contains($controller, "foreach (['city', 'street'] as \$field)"),
    'API requires only city and street'
);
$assertTrue(
    str_contains($controller, '$this->missingRequiredFields(array_merge($address->toArray(), $input))'),
    'update validates the effective record, not just partial input'
);

fwrite(STDOUT, "OK CustomerAddressFormContractTest\n");
exit(0);
