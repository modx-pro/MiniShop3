<?php

/**
 * AuthManager API token rotation policy for #412 (no MODX).
 *
 * Locks anti-fixation contracts:
 * - cart may transfer only from guest/own previous token
 * - Login / Register / checkout auto-login must rotate via AuthManager session helpers
 * - call sites must not rebind an existing token with set('customer_id')
 *
 * Run: php tests/ApiTokenRotationPolicyTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Customer\AuthManager;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

// Guest token (customer_id 0) — migrate cart, then revoke (fixes token fixation)
if (!AuthManager::canTransferCartFromToken(0, 42)) {
    $fail('guest token must be migratable');
}

// Own previous session token — rotate and allow cart transfer
if (!AuthManager::canTransferCartFromToken(42, 42)) {
    $fail('own token must be migratable');
}

// Missing previous token row — no transfer
if (AuthManager::canTransferCartFromToken(-1, 42)) {
    $fail('missing token must not transfer cart');
}

// Foreign customer token — must NOT migrate cart (still revoked after mint)
if (AuthManager::canTransferCartFromToken(7, 42)) {
    $fail('foreign token must not be migratable');
}

$authSrc = file_get_contents(__DIR__ . '/../src/Services/Customer/AuthManager.php');
if ($authSrc === false) {
    $fail('unable to read AuthManager.php');
}

if (!preg_match(
    '/function\s+establishCustomerSession\s*\([^)]*\)\s*:[^{]*\{[\s\S]*?persistApiToken\s*\(\s*\(int\)\$customer->id\s*,\s*null\s*,/',
    $authSrc
)) {
    $fail('establishCustomerSession must mint via persistApiToken(..., null, ...) — never reuse planted token');
}

if (!str_contains($authSrc, 'session_regenerate_id(true)')) {
    $fail('establishCustomerSession must regenerate PHP session id');
}

$loginSrc = file_get_contents(__DIR__ . '/../src/Processors/Api/Customer/Login.php');
$registerSrc = file_get_contents(__DIR__ . '/../src/Processors/Api/Customer/Register.php');
$customerSrc = file_get_contents(__DIR__ . '/../src/Controllers/Customer/Customer.php');
$emailSrc = file_get_contents(__DIR__ . '/../src/Controllers/Api/Web/CustomerEmailController.php');

foreach (
    [
        'Login' => $loginSrc,
        'Register' => $registerSrc,
        'CustomerEmailController' => $emailSrc,
    ] as $label => $src
) {
    if ($src === false) {
        $fail("unable to read {$label}");
    }
    if (!str_contains($src, 'establishApiSession')) {
        $fail("{$label} must call establishApiSession");
    }
    if (preg_match("/set\\(\\s*'customer_id'\\s*,/", $src)) {
        $fail("{$label} must not rebind token via set('customer_id')");
    }
}

if ($customerSrc === false) {
    $fail('unable to read Customer.php');
}

if (
    !str_contains($customerSrc, 'establishApiSession')
    && !str_contains($customerSrc, 'establishCustomerSession')
) {
    $fail('Customer checkout auto-login must use establishApiSession/establishCustomerSession');
}

if (
    preg_match(
        '/function\s+createFromOrderData[\s\S]*?\$autoLogin[\s\S]*?(?:establish(?:Api|Customer)Session)[\s\S]*?\n\s*\}/m',
        $customerSrc
    ) !== 1
) {
    $fail('createFromOrderData auto-login must call establishApiSession/establishCustomerSession');
}

if (preg_match("/\\\$\w*[Tt]oken\w*->set\(\s*['\"]customer_id['\"]/", $customerSrc)) {
    $fail('Customer controller must not rebind API token via set(customer_id)');
}

fwrite(STDOUT, "OK ApiTokenRotationPolicyTest\n");
exit(0);
