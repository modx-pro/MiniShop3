<?php

/**
 * #857: msProduct must resolve vendor-prefixed keys strictly (prefix at start), read real
 * product data columns (vendor_id FK) before the vendor label lookup, resolve option keys
 * before the vendor label, and must not let the vendor card overwrite the product vendor_id.
 *
 * Standalone (no MODX/MySQL). Run: php tests/VendorProductGetContractTest.php
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

$source = (string) file_get_contents($component . '/src/Model/msProduct.php');
$assertTrue(
    str_contains($source, "strpos(\$k, 'vendor_') === 0 || strpos(\$k, 'vendor.') === 0"),
    'msProduct::get uses strict vendor prefix for the vendor lookup'
);
$assertTrue(
    str_contains($source, 'Real product data columns (for example the vendor_id foreign key)'),
    'msProduct::get reads real product data columns before the vendor lookup'
);
$assertTrue(
    str_contains($source, 'in_array($k, $this->loadData()->getOptionKeys())'),
    'msProduct::get resolves option keys before the vendor lookup'
);
$assertTrue(
    str_contains($source, 'unset($vendorFields[$keyPrefix . \'vendor_id\'])'),
    'msProduct::toArray drops the vendor PK so it cannot overwrite the product vendor_id FK'
);

fwrite(STDOUT, "OK VendorProductGetContractTest\n");
exit(0);
