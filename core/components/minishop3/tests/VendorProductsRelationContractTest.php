<?php

/**
 * #856: msVendor must aggregate Products onto msProductData (where vendor_id lives)
 * so that $vendor->getMany('Products') resolves and does not silently return [].
 *
 * Standalone (no MODX/MySQL). Run: php tests/VendorProductsRelationContractTest.php
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

$schema = (string) file_get_contents($component . '/schema/minishop3.mysql.schema.xml');
$assertTrue(
    str_contains($schema, '<aggregate alias="Products" class="MiniShop3\Model\msProductData" local="id" foreign="vendor_id" cardinality="many" owner="foreign"/>'),
    'schema declares Products aggregate on msVendor pointing to msProductData'
);

$map = (string) file_get_contents($component . '/src/Model/mysql/msVendor.php');
$assertTrue(
    str_contains($map, "'Products' =>"),
    'generated xPDO map declares Products aggregate on msVendor'
);
$productsBlock = substr($map, (int) strpos($map, "'Products' =>"));
$productsBlock = substr($productsBlock, 0, (int) strpos($productsBlock, '],'));
$assertTrue(
    str_contains($productsBlock, "'class' => 'MiniShop3"),
    'Products aggregate class key points to MiniShop3 model'
);
$assertTrue(
    str_contains($productsBlock, 'msProductData'),
    'Products aggregate points to msProductData'
);
$assertTrue(
    str_contains($productsBlock, "'local' => 'id',"),
    'Products aggregate local key is msVendor PK'
);
$assertTrue(
    str_contains($productsBlock, "'foreign' => 'vendor_id',"),
    'Products aggregate foreign key is vendor_id'
);
$assertTrue(
    str_contains($productsBlock, "'cardinality' => 'many',"),
    'Products aggregate cardinality is many'
);

fwrite(STDOUT, "OK VendorProductsRelationContractTest\n");
exit(0);
