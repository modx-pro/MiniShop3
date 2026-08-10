<?php

/**
 * Guards auth call sites bind a rotated API session (#411 / #412).
 *
 * - email verify / Login / Register → AuthManager::establishApiSession
 * - no in-place token rebind via set('customer_id')
 * - checkout auto-login → CustomerOrderResolver + establishCustomerSession / establishApiSession
 *
 * Run: php tests/EmailVerifyApiSessionTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Customer\AuthManager;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$reflection = new ReflectionClass(AuthManager::class);
if (!$reflection->hasMethod('establishApiSession')) {
    $fail('AuthManager must expose establishApiSession()');
}

$verifySrc = file_get_contents(__DIR__ . '/../src/Controllers/Api/Web/CustomerEmailController.php');
if ($verifySrc === false) {
    $fail('unable to read CustomerEmailController.php');
}

if (str_contains($verifySrc, '$customer->get(\'token\')')) {
    $fail('CustomerEmailController must not store legacy msCustomer.token in session');
}

if (!str_contains($verifySrc, 'establishApiSession')) {
    $fail('CustomerEmailController must bind API session via AuthManager::establishApiSession()');
}

$loginSrc = file_get_contents(__DIR__ . '/../src/Processors/Api/Customer/Login.php');
if ($loginSrc === false) {
    $fail('unable to read Login.php');
}
if (!str_contains($loginSrc, 'establishApiSession')) {
    $fail('Login processor must reuse AuthManager::establishApiSession()');
}

$registerSrc = file_get_contents(__DIR__ . '/../src/Processors/Api/Customer/Register.php');
if ($registerSrc === false) {
    $fail('unable to read Register.php');
}
if (!str_contains($registerSrc, 'establishApiSession')) {
    $fail('Register processor must reuse AuthManager::establishApiSession()');
}

foreach (
    [
        'Login' => $loginSrc,
        'Register' => $registerSrc,
        'CustomerEmailController' => $verifySrc,
    ] as $label => $src
) {
    if (preg_match('/\$\w*[Tt]oken\w*->set\(\s*[\'"]customer_id[\'"]/', $src)) {
        $fail("{$label} must not rebind API token via set(customer_id)");
    }
}

$customerSrc = file_get_contents(__DIR__ . '/../src/Controllers/Customer/Customer.php');
if ($customerSrc === false) {
    $fail('unable to read Customer.php');
}
if (preg_match('/\$\w*[Tt]oken\w*->set\(\s*[\'"]customer_id[\'"]/', $customerSrc)) {
    $fail('Customer controller must not rebind API token via set(customer_id)');
}

// Checkout auto-login lives on CustomerOrderResolver (Customer facade delegates).
$orderResolverSrc = file_get_contents(__DIR__ . '/../src/Services/Customer/CustomerOrderResolver.php');
if ($orderResolverSrc === false) {
    $fail('unable to read CustomerOrderResolver.php');
}
if (
    preg_match(
        '/function\s+createFromOrderData[\s\S]*?\$autoLogin[\s\S]*?establish(?:Api|Customer)Session/',
        $orderResolverSrc
    ) !== 1
) {
    $fail('createFromOrderData auto-login must call establishApiSession/establishCustomerSession');
}
if (preg_match('/\$\w*[Tt]oken\w*->set\(\s*[\'"]customer_id[\'"]/', $orderResolverSrc)) {
    $fail('CustomerOrderResolver must not rebind API token via set(customer_id)');
}

$authSrc = file_get_contents(__DIR__ . '/../src/Services/Customer/AuthManager.php') ?: '';
if (!str_contains($authSrc, 'session_status()')) {
    $fail('AuthManager::establishApiSession() must ensure PHP session is active');
}

fwrite(STDOUT, "OK EmailVerifyApiSessionTest\n");
exit(0);
