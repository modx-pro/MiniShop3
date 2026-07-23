<?php

/**
 * AuthManager API token rotation policy (no MODX).
 *
 * Запуск: php tests/ApiTokenRotationPolicyTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Customer\AuthManager;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

// Guest token (customer_id 0) — migrate + revoke (fixes token fixation)
if (!AuthManager::canMigratePreviousApiToken(0, 42)) {
    $fail('guest token must be migratable');
}

// Own previous session token — rotate
if (!AuthManager::canMigratePreviousApiToken(42, 42)) {
    $fail('own token must be migratable');
}

// Orphan draft (no API token row) — allow draft migrate by string
if (!AuthManager::canMigratePreviousApiToken(null, 42)) {
    $fail('null owner (orphan) must allow migrate');
}

// Foreign customer token — must NOT revoke/steal
if (AuthManager::canMigratePreviousApiToken(7, 42)) {
    $fail('foreign token must not be migratable');
}

// Regression guard: must rotate via establishApiSession, never rebind guest token
$loginSrc = file_get_contents(__DIR__ . '/../src/Processors/Api/Customer/Login.php');
$registerSrc = file_get_contents(__DIR__ . '/../src/Processors/Api/Customer/Register.php');
$customerSrc = file_get_contents(__DIR__ . '/../src/Controllers/Customer/Customer.php');
foreach (['Login' => $loginSrc, 'Register' => $registerSrc] as $label => $src) {
    if (!str_contains($src, 'establishApiSession')) {
        $fail("{$label} must call establishApiSession");
    }
    if (preg_match("/set\\(\\s*'customer_id'\\s*,/", $src)) {
        $fail("{$label} must not rebind token via set('customer_id')");
    }
}
if (!str_contains($customerSrc, 'establishApiSession')) {
    $fail('Customer auto-login must use establishApiSession');
}
if (preg_match('/function autoLoginCustomer.*?\{.*?set\(\s*\'customer_id\'/s', $customerSrc)) {
    $fail('autoLoginCustomer must not rebind token via set(customer_id)');
}

fwrite(STDOUT, "OK ApiTokenRotationPolicyTest\n");
exit(0);
