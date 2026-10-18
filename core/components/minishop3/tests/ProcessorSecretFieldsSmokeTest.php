<?php

/**
 * Smoke: manager-side customer/user processors must narrow their payload to an
 * allowlist, so credential columns never reach a response (#908).
 *
 * Behaviour is covered by tests/Unit/Processors/SecretFieldsTest.php; this guard keeps
 * the narrowing from being dropped during a refactor of the processors themselves.
 *
 * Run: php tests/ProcessorSecretFieldsSmokeTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/support/xpdo_stub.php';
require __DIR__ . '/support/xpdo_om_stub.php';

use MiniShop3\Services\Customer\CustomerManagerFields;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

// 1. The allowlist is fail-closed: credentials are absent and unknown columns do not pass.
foreach (['password', 'token'] as $secret) {
    if (in_array($secret, CustomerManagerFields::FIELDS, true)) {
        $fail("CustomerManagerFields::FIELDS must not list '{$secret}'");
    }
}

$picked = CustomerManagerFields::pick([
    'id' => 1,
    'email' => 'a@b.c',
    'password' => 'hash',
    'token' => 'tok',
    'some_new_column' => 'x',
]);
if ($picked !== ['id' => 1, 'email' => 'a@b.c']) {
    $fail('CustomerManagerFields::pick() must keep only allowlisted fields');
}

$excluded = CustomerManagerFields::excludedColumns(['id' => [], 'password' => [], 'token' => []]);
if (!in_array('password', $excluded, true) || !in_array('token', $excluded, true)) {
    $fail('CustomerManagerFields::excludedColumns() must exclude credential columns from SELECT');
}
if (in_array('id', $excluded, true)) {
    $fail('CustomerManagerFields::excludedColumns() must not exclude allowlisted columns');
}

// 2. Every manager-side processor that returns a customer row goes through the allowlist.
$srcDir = dirname(__DIR__) . '/src/Processors';

/** @var array<string, list<string>> $processors */
$processors = [
    'Customer/Get.php' => ['CustomerManagerFields::pick'],
    'Customer/GetList.php' => ['CustomerManagerFields::pick', 'CustomerManagerFields::excludedColumns'],
    'Customer/GetListCombobox.php' => ['CustomerManagerFields::pick', 'CustomerManagerFields::excludedColumns'],
    'Customer/Update.php' => ['CustomerManagerFields::pick', 'function cleanup'],
    'System/User/GetList.php' => ['ALLOWED_FIELDS', 'array_intersect_key'],
];

foreach ($processors as $relative => $needles) {
    $path = $srcDir . '/' . $relative;
    $source = is_readable($path) ? file_get_contents($path) : false;
    if ($source === false) {
        $fail('cannot read ' . $relative);
    }

    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) {
            $fail("{$relative}: expected '{$needle}' so credential columns never reach the response");
        }
    }
}

// 3. The modUser allowlist carries no credential column.
$userSource = (string) file_get_contents($srcDir . '/System/User/GetList.php');
if (!preg_match('/ALLOWED_FIELDS\s*=\s*\[(.*?)\];/s', $userSource, $m)) {
    $fail('System/User/GetList.php: ALLOWED_FIELDS list not found');
}
foreach (['password', 'cachepwd', 'salt', 'remote_key', 'remote_data'] as $column) {
    if (str_contains($m[1], "'" . $column . "'")) {
        $fail("System/User/GetList.php: '{$column}' must stay out of ALLOWED_FIELDS");
    }
}

fwrite(STDOUT, "OK ProcessorSecretFieldsSmokeTest\n");
