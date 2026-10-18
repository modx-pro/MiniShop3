<?php

/**
 * Smoke: processors that return a raw toArray() of msCustomer / modUser must strip
 * credential columns regardless of the `combo` property (#908).
 *
 * Behaviour is covered by tests/Unit/Processors/SecretFieldsTest.php; this guard keeps
 * the stripping from being dropped during a refactor of the processors themselves.
 *
 * Run: php tests/ProcessorSecretFieldsSmokeTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/support/xpdo_stub.php';
require __DIR__ . '/support/xpdo_om_stub.php';

use MiniShop3\Model\msCustomer;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

// 1. The customer secret list stays non-empty and keeps both credential columns.
foreach (['password', 'token'] as $expected) {
    if (!in_array($expected, msCustomer::SECRET_FIELDS, true)) {
        $fail("msCustomer::SECRET_FIELDS must list '{$expected}'");
    }
}

$clean = msCustomer::withoutSecrets(['id' => 1, 'email' => 'a@b.c', 'password' => 'h', 'token' => 't']);
if (isset($clean['password']) || isset($clean['token'])) {
    $fail('msCustomer::withoutSecrets() must drop password and token');
}
if (($clean['email'] ?? null) !== 'a@b.c') {
    $fail('msCustomer::withoutSecrets() must keep non-secret fields');
}

// 2. Each processor strips the columns outside of the `combo` branch.
$srcDir = dirname(__DIR__) . '/src/Processors';

/** @var array<string, list<string>> $processors */
$processors = [
    'Customer/GetListCombobox.php' => ['msCustomer::withoutSecrets'],
    'Customer/Get.php' => ['msCustomer::withoutSecrets'],
    'System/User/GetList.php' => ['SECRET_FIELDS', 'unset($array[$field])'],
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

// 3. The modUser list covers what core strips plus the external-auth columns.
$userSource = (string) file_get_contents($srcDir . '/System/User/GetList.php');
foreach (['password', 'cachepwd', 'salt', 'remote_key', 'remote_data'] as $column) {
    if (!preg_match("/'" . $column . "'/", $userSource)) {
        $fail("System/User/GetList.php: '{$column}' must be in SECRET_FIELDS");
    }
}

fwrite(STDOUT, "OK ProcessorSecretFieldsSmokeTest\n");
