<?php

/**
 * #854: msCustomer must declare an Addresses composite (owner side) so that
 * $customer->getMany('Addresses') resolves and $customer->remove() cascades
 * to msCustomerAddress rows — the same way the Tokens composite cascades.
 *
 * Standalone (no MODX/MySQL). Run: php tests/CustomerAddressesRelationContractTest.php
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
    str_contains($schema, '<composite alias="Addresses" class="MiniShop3\Model\msCustomerAddress" local="id" foreign="customer_id" cardinality="many" owner="local"/>'),
    'schema declares Addresses composite on msCustomer'
);
$assertTrue(
    str_contains($schema, '<composite alias="Tokens" class="MiniShop3\Model\msCustomerToken" local="id" foreign="customer_id" cardinality="many" owner="local"/>'),
    'schema keeps Tokens composite on msCustomer'
);

$map = (string) file_get_contents($component . '/src/Model/mysql/msCustomer.php');
$assertTrue(
    str_contains($map, "'Addresses' =>"),
    'generated xPDO map declares Addresses composite'
);
$addressesBlock = substr($map, (int) strpos($map, "'Addresses' =>"));
$addressesBlock = substr($addressesBlock, 0, (int) strpos($addressesBlock, '],'));
$assertTrue(
    str_contains($addressesBlock, "'class' => 'MiniShop3"),
    'Addresses composite class key points to MiniShop3 model'
);
$assertTrue(
    str_contains($addressesBlock, 'msCustomerAddress'),
    'Addresses composite points to msCustomerAddress'
);
$assertTrue(
    str_contains($addressesBlock, "'local' => 'id',"),
    'Addresses composite local key is msCustomer PK'
);
$assertTrue(
    str_contains($addressesBlock, "'foreign' => 'customer_id',"),
    'Addresses composite foreign key is customer_id'
);
$assertTrue(
    str_contains($addressesBlock, "'cardinality' => 'many',"),
    'Addresses composite cardinality is many'
);

fwrite(STDOUT, "OK CustomerAddressesRelationContractTest\n");
exit(0);
