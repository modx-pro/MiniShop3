<?php

/**
 * Guards the Manager order payload secret denylist (issue #421).
 *
 * `formatOrder()` strips every key in HIDDEN_ORDER_FIELDS from all order responses
 * (get / list / create / finalize / recalculate-cost / update). This static check
 * fails if the web-order secret `token` is dropped from that denylist.
 *
 * Run: php tests/OrderPayloadSecretFieldsTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Controllers\Api\Manager\OrdersController;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$reflection = new ReflectionClass(OrdersController::class);
$hiddenFields = $reflection->getConstant('HIDDEN_ORDER_FIELDS');

if (!is_array($hiddenFields)) {
    $fail('HIDDEN_ORDER_FIELDS must be an array');
}

if (!in_array('token', $hiddenFields, true)) {
    $fail('HIDDEN_ORDER_FIELDS must contain "token" (web-order secret must never leak)');
}

fwrite(STDOUT, "OK OrderPayloadSecretFieldsTest\n");
exit(0);
