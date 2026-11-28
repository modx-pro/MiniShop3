<?php

/**
 * #872: msOrder→Address relation keys in schema and generated map must agree.
 *
 * Run: php tests/OrderAddressRelationTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$schema = (string) file_get_contents($root . '/core/components/minishop3/schema/minishop3.mysql.schema.xml');
if (!str_contains($schema, '<composite alias="Address" class="MiniShop3\Model\msOrderAddress" local="id" foreign="order_id"')) {
    $fail('Schema must use local=id / foreign=order_id for msOrder.Address (#872)');
}

$map = (string) file_get_contents($root . '/core/components/minishop3/src/Model/mysql/msOrder.php');
if (!preg_match("/'Address'\s*=>\s*\[[\s\S]*?'local'\s*=>\s*'id'[\s\S]*?'foreign'\s*=>\s*'order_id'/", $map)) {
    $fail('Generated msOrder map must declare the same Address keys (#872)');
}

fwrite(STDOUT, "OK OrderAddressRelationTest (#872)\n");
exit(0);
