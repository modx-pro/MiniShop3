<?php

/**
 * Static checks for public customer DTO (without MODX).
 *
 * Run: php tests/CustomerPublicDtoTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Customer\CustomerPublicDto;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$input = [
    'id' => 42,
    'user_id' => 7,
    'first_name' => 'Ada',
    'last_name' => 'Lovelace',
    'email' => 'ada@example.com',
    'phone' => '+10000000000',
    'password' => '$2y$10$notarealhash',
    'token' => 'session-or-api-secret',
    'email_verified_at' => '2026-01-02 03:04:05',
    'is_active' => true,
    'is_blocked' => true,
    'privacy_ip' => '203.0.113.10',
    'failed_login_attempts' => 3,
    'blocked_until' => '2026-01-01 00:00:00',
    'created_at' => '2025-01-01 00:00:00',
    'updated_at' => '2025-06-01 00:00:00',
    'last_login_at' => '2025-12-01 00:00:00',
    'orders_count' => 2,
    'total_spent' => 99.5,
    'last_order_at' => '2025-11-01 00:00:00',
    'privacy_accepted_at' => '2025-01-01 00:00:00',
    'future_secret_column' => 'must-not-leak',
];

$expected = [
    'id' => 42,
    'first_name' => 'Ada',
    'last_name' => 'Lovelace',
    'email' => 'ada@example.com',
    'phone' => '+10000000000',
    'email_verified_at' => '2026-01-02 03:04:05',
    'is_active' => true,
    'created_at' => '2025-01-01 00:00:00',
    'updated_at' => '2025-06-01 00:00:00',
    'last_login_at' => '2025-12-01 00:00:00',
    'orders_count' => 2,
    'total_spent' => 99.5,
    'last_order_at' => '2025-11-01 00:00:00',
    'privacy_accepted_at' => '2025-01-01 00:00:00',
];

$public = CustomerPublicDto::fromArray($input);

if ($public !== $expected) {
    $fail(sprintf(
        "public payload mismatch:\nexpected: %s\nactual:   %s",
        json_encode($expected, JSON_UNESCAPED_SLASHES),
        json_encode($public, JSON_UNESCAPED_SLASHES)
    ));
}

foreach (['password', 'token', 'privacy_ip', 'failed_login_attempts', 'blocked_until', 'is_blocked', 'user_id', 'future_secret_column'] as $hidden) {
    if (array_key_exists($hidden, $public)) {
        $fail("hidden field still present: {$hidden}");
    }
}

$coreEditable = CustomerPublicDto::CORE_PROFILE_EDITABLE_FIELDS;
foreach (['first_name', 'last_name', 'email', 'phone'] as $key) {
    if (!in_array($key, $coreEditable, true)) {
        $fail("CORE_PROFILE_EDITABLE_FIELDS missing {$key}");
    }
}

$withExtra = CustomerPublicDto::fromArray(
    array_merge($input, ['loyalty_tier' => 'gold']),
    ['loyalty_tier']
);
if (($withExtra['loyalty_tier'] ?? null) !== 'gold') {
    $fail('registered extra field must be included in public payload');
}

$controllerSrc = file_get_contents(__DIR__ . '/../src/Controllers/Api/Web/CustomerProfileController.php');
if ($controllerSrc === false) {
    $fail('unable to read CustomerProfileController.php');
}
if (str_contains($controllerSrc, '$customer->toArray()')) {
    $fail('CustomerProfileController still serializes full customer via toArray()');
}
if (!str_contains($controllerSrc, 'CustomerPublicDto::fromCustomer($customer, $this->modx, $this->ms3)')) {
    $fail('CustomerProfileController missing CustomerPublicDto::fromCustomer with modx/ms3');
}

fwrite(STDOUT, "OK CustomerPublicDtoTest\n");
exit(0);
