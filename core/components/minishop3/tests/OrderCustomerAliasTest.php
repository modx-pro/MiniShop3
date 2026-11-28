<?php

/**
 * #874: schema and generated xPDO map must agree on the msOrder→Customer alias.
 *
 * Run: php tests/OrderCustomerAliasTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$schema = (string) file_get_contents($root . '/core/components/minishop3/schema/minishop3.mysql.schema.xml');
if (!str_contains($schema, '<aggregate alias="Customer" class="MiniShop3\Model\msCustomer" local="customer_id"')) {
    $fail('Schema must declare the msOrder Customer aggregate (#874)');
}
if (str_contains($schema, '<aggregate alias="msCustomer" class="MiniShop3\Model\msCustomer"')) {
    $fail('Old msCustomer aggregate alias must be gone (#874)');
}

$map = (string) file_get_contents($root . '/core/components/minishop3/src/Model/mysql/msOrder.php');
if (!preg_match("/'Customer'\s*=>\s*\[[\s\S]*?'class'\s*=>\s*'MiniShop3\\\\\\\\Model\\\\\\\\msCustomer'/", $map)) {
    $fail('Generated msOrder map must declare the Customer aggregate (#874)');
}

fwrite(STDOUT, "OK OrderCustomerAliasTest (#874)\n");
exit(0);
