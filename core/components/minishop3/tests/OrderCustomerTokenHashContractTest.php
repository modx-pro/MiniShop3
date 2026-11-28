<?php

/**
 * #1058: order/customer tokens must be stored hashed, without touching the
 * non-bearer integration token.
 *
 * Standalone (no MODX). Run: php tests/OrderCustomerTokenHashContractTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$migration = (string) file_get_contents(
    $root . '/migrations/20261127120000_hash_order_customer_tokens.php'
);
foreach (['ms3_orders', 'ms3_customers'] as $table) {
    if (!str_contains($migration, "'{$table}'")) {
        $fail("migration must hash {$table}");
    }
}
if (!str_contains($migration, "REGEXP '^[0-9a-f]{64}$'")) {
    $fail('migration must hash only 64-hex API tokens');
}
if (!str_contains($migration, "CONCAT('sha256$', SHA2(`token`, 256))")) {
    $fail('migration must write the same sha256$ prefix as StoredTokenHash (#1058)');
}

$files = [
    'src/Services/Order/OrderDraftManager.php',
    'src/Services/Customer/RegisterService.php',
    'src/Services/Customer/CustomerFieldManager.php',
    'src/Services/Customer/CustomerOrderResolver.php',
    'src/Controllers/Customer/Customer.php',
    'elements/plugins/minishop3.php',
];
foreach ($files as $file) {
    $source = (string) file_get_contents($root . '/' . $file);
    if (!str_contains($source, 'StoredTokenHash')) {
        $fail("{$file} must use StoredTokenHash");
    }
    if (preg_match("/->set\(\s*'token'\s*,\s*\\\$token\s*\)/", $source) === 1) {
        $fail("{$file} still stores a plaintext token");
    }
}

$hashUtil = (string) file_get_contents($root . '/src/Utils/StoredTokenHash.php');
if (!str_contains($hashUtil, "private const PREFIX = 'sha256\$';")) {
    $fail('hashed values need a prefix so the migration cannot double-hash them');
}

$fieldManager = (string) file_get_contents($root . '/src/Services/Customer/CustomerFieldManager.php');
$hashPos = strpos($fieldManager, "StoredTokenHash::hash(\$customerData['token'])");
$createPos = strpos($fieldManager, 'newObject(msCustomer::class, $customerData)');
if ($hashPos === false || $createPos === false || $hashPos >= $createPos) {
    $fail('CustomerFieldManager::create() must hash &token before newObject');
}

$integration = (string) file_get_contents($root . '/src/Services/Order/OrderDraftManager.php');
if (!str_contains($integration, "md5(uniqid('ms3_int_', true))")) {
    $fail('integration order token must keep its own identifier');
}

fwrite(STDOUT, "OK OrderCustomerTokenHashContractTest\n");
exit(0);
