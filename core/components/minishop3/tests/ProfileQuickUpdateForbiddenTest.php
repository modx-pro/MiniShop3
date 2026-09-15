<?php

/**
 * Regression #413/#424: customer/add allowlist must block GDPR/system fields.
 *
 * Run: php tests/ProfileQuickUpdateForbiddenTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Customer\CustomerPublicDto;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$controllerSrc = file_get_contents(__DIR__ . '/../src/Controllers/Api/Web/CustomerProfileController.php');
if ($controllerSrc === false || $controllerSrc === '') {
    $fail('unable to read CustomerProfileController.php');
}

if (str_contains($controllerSrc, 'PROFILE_QUICK_UPDATE_FORBIDDEN')) {
    $fail('blacklist PROFILE_QUICK_UPDATE_FORBIDDEN must be removed in favor of allowlist');
}

if (!str_contains($controllerSrc, 'CustomerPublicDto::editableFieldKeys')) {
    $fail('updateField must enforce CustomerPublicDto allowlist via editableFieldKeys');
}

if (preg_match('/foreach \\(array_keys\\(\\$rules\\)/', $controllerSrc)) {
    $fail('update must not require all core profile fields — partial updates only (#424)');
}
if (!preg_match('/array_intersect_key\\(\\$this->getProfileFieldRules\\(\\),\\s*\\$data\\)/', $controllerSrc)) {
    $fail('update must restrict validation to core rules for fields present in $data');
}

foreach (['privacy_accepted_at', 'privacy_ip', 'password', 'token', 'email_verified_at', 'customer_group_id'] as $key) {
    if (!in_array($key, CustomerPublicDto::SYSTEM_NON_EDITABLE_FIELDS, true)) {
        $fail("SYSTEM_NON_EDITABLE_FIELDS must include {$key}");
    }
}

$coreEditable = CustomerPublicDto::CORE_PROFILE_EDITABLE_FIELDS;
foreach (['first_name', 'last_name', 'email', 'phone'] as $key) {
    if (!in_array($key, $coreEditable, true)) {
        $fail("CORE_PROFILE_EDITABLE_FIELDS must include {$key}");
    }
}

$merged = CustomerPublicDto::mergeEditableFieldKeys(['company', 'password', 'privacy_accepted_at']);
if (!in_array('company', $merged, true)) {
    $fail('active extra field key must merge into editable allowlist');
}
foreach (['password', 'privacy_accepted_at'] as $blocked) {
    if (in_array($blocked, $merged, true)) {
        $fail("non-editable key must not merge: {$blocked}");
    }
}

$publicWithExtra = CustomerPublicDto::fromArray(
    ['id' => 1, 'company' => 'ACME', 'password' => 'secret'],
    ['company']
);
if (($publicWithExtra['company'] ?? null) !== 'ACME') {
    $fail('extra public field must appear when registered in msExtraField keys');
}
if (array_key_exists('password', $publicWithExtra)) {
    $fail('password must never appear in public DTO');
}

fwrite(STDOUT, "OK ProfileQuickUpdateForbiddenTest\n");
exit(0);
