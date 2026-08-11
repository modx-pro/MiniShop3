<?php

/**
 * Guards payments-active dropdown payload (issue #346, mirror of #416).
 *
 * GET /api/mgr/payments-active must not leak integration secrets
 * (properties, class, validation_rules).
 *
 * Run: php tests/PaymentsActiveDropdownFieldsTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Controllers\Api\Manager\PaymentsController;
use MiniShop3\Services\Settings\SettingsComboListService;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$fields = PaymentsController::ACTIVE_DROPDOWN_FIELDS;
if (!is_array($fields) || $fields === []) {
    $fail('ACTIVE_DROPDOWN_FIELDS must be a non-empty array');
}

$required = ['id', 'name', 'price', 'active', 'position'];
foreach ($required as $field) {
    if (!in_array($field, $fields, true)) {
        $fail("ACTIVE_DROPDOWN_FIELDS must contain \"{$field}\"");
    }
}

$forbidden = ['properties', 'class', 'validation_rules'];
foreach ($forbidden as $field) {
    if (in_array($field, $fields, true)) {
        $fail("ACTIVE_DROPDOWN_FIELDS must not contain secret/internal field \"{$field}\"");
    }
}

$raw = [
    'id' => 3,
    'name' => 'Card',
    'price' => '0',
    'active' => 1,
    'position' => 1,
    'properties' => ['secret' => 'x'],
    'class' => 'MiniShop3\\Controllers\\Payment\\Payment',
    'validation_rules' => '{}',
    'description' => 'should not leak',
];

$projected = PaymentsController::projectActiveDropdownFields($raw);

foreach ($forbidden as $field) {
    if (array_key_exists($field, $projected)) {
        $fail("projected payload must not contain \"{$field}\"");
    }
}

if (array_key_exists('description', $projected)) {
    $fail('projected payload must not contain non-whitelist field "description"');
}

foreach ($required as $field) {
    if (!array_key_exists($field, $projected)) {
        $fail("projected payload must keep \"{$field}\"");
    }
}

$controllerSource = file_get_contents(__DIR__ . '/../src/Controllers/Api/Manager/PaymentsController.php');
if ($controllerSource === false) {
    $fail('cannot read PaymentsController.php');
}
if (!str_contains($controllerSource, 'foreach (self::ACTIVE_DROPDOWN_FIELDS as $field)')) {
    $fail('formatActiveDropdownItem must iterate ACTIVE_DROPDOWN_FIELDS');
}

// Vendor pin semantics shared by processor + REST.
if (SettingsComboListService::resolvePinnedIncludeId(5, null) !== 5) {
    $fail('resolvePinnedIncludeId must pin when limit is null');
}
if (SettingsComboListService::resolvePinnedIncludeId(5, 0) !== 5) {
    $fail('resolvePinnedIncludeId must pin when limit is 0');
}
if (SettingsComboListService::resolvePinnedIncludeId(5, 20) !== 0) {
    $fail('resolvePinnedIncludeId must not pin when browsing with limit>0');
}
if (SettingsComboListService::resolvePinnedIncludeId(0, null) !== 0) {
    $fail('resolvePinnedIncludeId must ignore non-positive id');
}

fwrite(STDOUT, "OK PaymentsActiveDropdownFieldsTest\n");
exit(0);
