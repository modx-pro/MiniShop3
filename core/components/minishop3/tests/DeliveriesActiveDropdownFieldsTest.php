<?php

/**
 * Guards deliveries-active dropdown payload (issue #416).
 *
 * GET /api/mgr/deliveries-active must not leak integration secrets
 * (properties, class, validation_rules).
 *
 * Run: php tests/DeliveriesActiveDropdownFieldsTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Controllers\Api\Manager\DeliveriesController;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$fields = DeliveriesController::ACTIVE_DROPDOWN_FIELDS;
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
    'id' => 7,
    'name' => 'Courier',
    'price' => '300',
    'active' => 1,
    'position' => 2,
    'properties' => ['api_key' => 'secret'],
    'class' => 'MiniShop3\\Controllers\\Delivery\\Delivery',
    'validation_rules' => '{"phone":"required"}',
    'description' => 'should not leak',
];

$projected = DeliveriesController::projectActiveDropdownFields($raw);

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

if ($projected['id'] !== 7 || $projected['name'] !== 'Courier' || $projected['position'] !== 2) {
    $fail('projected field values must not be altered');
}

// Guard: production formatter must loop the same whitelist (source scan).
$controllerSource = file_get_contents(__DIR__ . '/../src/Controllers/Api/Manager/DeliveriesController.php');
if ($controllerSource === false) {
    $fail('cannot read DeliveriesController.php');
}
if (!str_contains($controllerSource, 'foreach (self::ACTIVE_DROPDOWN_FIELDS as $field)')) {
    $fail('formatActiveDropdownItem must iterate ACTIVE_DROPDOWN_FIELDS (not ad-hoc toArray)');
}
if (preg_match('/function formatActiveDropdownItem.*?toArray\(/s', $controllerSource)) {
    $fail('formatActiveDropdownItem must not call toArray()');
}

fwrite(STDOUT, "OK DeliveriesActiveDropdownFieldsTest\n");
exit(0);
