<?php

/**
 * Guards email verify auto-login uses API token bind (issue #411).
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

if (!str_contains(file_get_contents(__DIR__ . '/../src/Services/Customer/AuthManager.php') ?: '', 'session_status()')) {
    $fail('AuthManager::establishApiSession() must ensure PHP session is active');
}

fwrite(STDOUT, "OK EmailVerifyApiSessionTest\n");
exit(0);
