<?php

/**
 * #571 customer auth contract: me payload shape, Bearer bind order, refresh rotation.
 *
 * Standalone (no MODX). Run: php tests/CustomerSessionContractTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Customer\CustomerPublicDto;
use MiniShop3\Services\TokenService;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertTrue = static function (bool $actual, string $case) use ($fail): void {
    if (!$actual) {
        $fail($case . ': expected true');
    }
};

$assertFalse = static function (bool $actual, string $case) use ($fail): void {
    if ($actual) {
        $fail($case . ': expected false');
    }
};

// --- resolveTokenFromRequest priority: Bearer > MS3TOKEN > REQUEST > session ---
$_SERVER = [];
$_REQUEST = [];
$_SESSION = [];

$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer bearer-token-value';
$_SERVER['HTTP_MS3TOKEN'] = 'legacy-header';
$_REQUEST['ms3_token'] = 'request-token';
$_SESSION['ms3']['customer_token'] = 'session-token';
$assertSame('bearer-token-value', TokenService::resolveTokenFromRequest(), 'Bearer wins');

unset($_SERVER['HTTP_AUTHORIZATION']);
$assertSame('legacy-header', TokenService::resolveTokenFromRequest(), 'MS3TOKEN next');

unset($_SERVER['HTTP_MS3TOKEN']);
$assertSame('request-token', TokenService::resolveTokenFromRequest(), 'REQUEST next');

unset($_REQUEST['ms3_token'], $_REQUEST['token']);
$assertSame('session-token', TokenService::resolveTokenFromRequest(), 'session last among globals');

unset($_SESSION['ms3']['customer_token']);
$assertSame('', TokenService::resolveTokenFromRequest(), 'empty when nothing set');

// --- CustomerPublicDto must not expose secrets (me allowlist) ---
$leaky = [
    'id' => 1,
    'email' => 'a@b.c',
    'password' => 'hash',
    'token' => 'internal',
    'user_id' => 9,
    'first_name' => 'Ann',
];
$public = CustomerPublicDto::fromArray($leaky);
$assertSame(1, $public['id'] ?? null, 'id kept');
$assertSame('a@b.c', $public['email'] ?? null, 'email kept');
$assertSame('Ann', $public['first_name'] ?? null, 'first_name kept');
$assertFalse(array_key_exists('password', $public), 'password stripped');
$assertFalse(array_key_exists('token', $public), 'token stripped');
$assertFalse(array_key_exists('user_id', $public), 'user_id stripped');

// --- me / refresh source contracts ---
$sessionService = file_get_contents(__DIR__ . '/../src/Services/Customer/CustomerSessionService.php');
if ($sessionService === false) {
    $fail('cannot read CustomerSessionService');
}
$assertTrue(str_contains($sessionService, "'authenticated'"), 'me payload has authenticated');
$assertTrue(str_contains($sessionService, 'CustomerPublicDto::fromCustomer'), 'me uses public DTO');
$assertFalse(str_contains($sessionService, 'rotateApiToken'), 'session service is me-only (no refresh wrapper)');

$tokenServiceSrc = file_get_contents(__DIR__ . '/../src/Services/TokenService.php');
if ($tokenServiceSrc === false) {
    $fail('cannot read TokenService');
}
$assertTrue(str_contains($tokenServiceSrc, 'resolveTokenFromRequest()'), 'bind uses shared resolve');
$assertTrue(
    str_contains($tokenServiceSrc, 'getBindableTokenString')
    && str_contains($tokenServiceSrc, 'resolveTokenFromRequest'),
    'getBindableTokenString file includes resolveTokenFromRequest for Bearer bind'
);

$middlewareSrc = file_get_contents(__DIR__ . '/../src/Middleware/TokenMiddleware.php');
if ($middlewareSrc === false) {
    $fail('cannot read TokenMiddleware');
}
$assertTrue(
    str_contains($middlewareSrc, 'TokenService::resolveTokenFromRequest()'),
    'TokenMiddleware uses shared resolve'
);
$assertFalse(
    str_contains($middlewareSrc, 'private function resolveToken'),
    'TokenMiddleware must not keep a private resolveToken duplicate'
);

fwrite(STDOUT, "OK CustomerSessionContractTest\n");
exit(0);
