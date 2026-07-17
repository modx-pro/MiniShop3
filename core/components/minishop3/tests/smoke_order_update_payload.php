<?php

/**
 * Live smoke for Manager order GET/PUT payload shape (issue #421).
 *
 * Bootstraps a local MODX install and calls OrdersController directly
 * (no HTTP session required). Optional — needs a real DB with orders.
 *
 * Usage:
 *   MODX_CONFIG_CORE=/path/to/config.core.php \
 *     php tests/smoke_order_update_payload.php [--order-id=N]
 *
 * If MODX_CONFIG_CORE is unset, looks for ../../../../config.core.php
 * relative to this file (core/components/minishop3/tests → site root).
 */

declare(strict_types=1);

$configCore = getenv('MODX_CONFIG_CORE') ?: '';
if ($configCore === '') {
    $candidate = dirname(__DIR__, 4) . '/config.core.php';
    if (is_file($candidate)) {
        $configCore = $candidate;
    }
}

if ($configCore === '' || !is_file($configCore)) {
    fwrite(STDERR, "SKIP: set MODX_CONFIG_CORE to a MODX config.core.php (live DB required)\n");
    exit(0);
}

require $configCore;
require MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require MODX_CORE_PATH . 'vendor/autoload.php';

use MiniShop3\Controllers\Api\Manager\OrdersController;
use MiniShop3\Model\msOrder;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$pass = static function (string $message): void {
    fwrite(STDOUT, "OK: {$message}\n");
};

$orderId = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--order-id=')) {
        $orderId = (int) substr($arg, 11);
    }
}

$modx = new \MODX\Revolution\modX();
$modx->initialize('mgr');

if (!$modx->services->has('ms3')) {
    $fail('ms3 service is not registered');
}

if ($orderId === null || $orderId <= 0) {
    // Prefer an order that still has a web-order secret in DB (stronger leak check).
    $c = $modx->newQuery(msOrder::class);
    $c->where(['token:!=' => '', 'AND:token:IS NOT' => null]);
    $c->select('id');
    $c->sortby('id', 'DESC');
    $c->limit(1);
    $c->prepare();
    $c->stmt->execute();
    $orderId = (int) $c->stmt->fetchColumn();
    if ($orderId <= 0) {
        $c = $modx->newQuery(msOrder::class);
        $c->select('id');
        $c->sortby('id', 'DESC');
        $c->limit(1);
        $c->prepare();
        $c->stmt->execute();
        $orderId = (int) $c->stmt->fetchColumn();
    }
}

if ($orderId <= 0) {
    $fail('No order found to smoke-test');
}

/** @var msOrder|null $before */
$before = $modx->getObject(msOrder::class, $orderId);
if (!$before) {
    $fail("Order #{$orderId} not found");
}

$dbToken = (string) $before->get('token');
$requireDbToken = $dbToken !== '';

$controller = new OrdersController($modx);

$get = $controller->get(['id' => $orderId]);
if (empty($get['success'])) {
    $fail('GET failed: ' . json_encode($get, JSON_UNESCAPED_UNICODE));
}
$getData = $get['data'] ?? null;
if (!is_array($getData)) {
    $fail('GET data is not an array');
}

$hasTokenInGet = array_key_exists('token', $getData);
$pass(sprintf(
    'GET #%d success; token in payload: %s',
    $orderId,
    $hasTokenInGet ? 'YES (leak)' : 'no'
));

$oldComment = (string) $before->get('order_comment');
$marker = '[smoke-421 ' . date('H:i:s') . ']';
$newComment = $oldComment === '' ? $marker : $oldComment;

$put = $controller->update([
    'id' => $orderId,
    'order_comment' => $newComment,
]);

if (empty($put['success'])) {
    $fail('PUT failed: ' . json_encode($put, JSON_UNESCAPED_UNICODE));
}
$putData = $put['data'] ?? null;
if (!is_array($putData)) {
    $fail('PUT data is not an array');
}

$hasTokenInPut = array_key_exists('token', $putData);
$pass(sprintf(
    'PUT #%d success; token in payload: %s',
    $orderId,
    $hasTokenInPut ? 'YES (leak)' : 'no'
));

if ($newComment !== $oldComment) {
    $controller->update(['id' => $orderId, 'order_comment' => $oldComment]);
}

$shapeKeysExpected = [
    'status_name',
    'delivery_name',
    'payment_name',
    'customer',
    'cost_formatted',
    'cart_cost_formatted',
    'delivery_cost_formatted',
];

$missingInPut = [];
foreach ($shapeKeysExpected as $key) {
    if (!array_key_exists($key, $putData)) {
        $missingInPut[] = $key;
    }
}

$putKeys = array_keys($putData);
$getKeys = array_keys($getData);
sort($putKeys);
sort($getKeys);

$onlyInPut = array_values(array_diff($putKeys, $getKeys));
$onlyInGet = array_values(array_diff($getKeys, $putKeys));

fwrite(STDOUT, 'GET keys count: ' . count($getKeys) . "\n");
fwrite(STDOUT, 'PUT keys count: ' . count($putKeys) . "\n");
if ($onlyInPut) {
    fwrite(STDOUT, 'Only in PUT: ' . implode(', ', $onlyInPut) . "\n");
}
if ($onlyInGet) {
    fwrite(STDOUT, 'Only in GET: ' . implode(', ', $onlyInGet) . "\n");
}

$errors = [];
if ($hasTokenInGet) {
    $errors[] = 'GET still exposes token';
}
if ($hasTokenInPut) {
    $errors[] = 'PUT still exposes token';
}
if ($missingInPut) {
    $errors[] = 'PUT missing GET-shape keys: ' . implode(', ', $missingInPut);
}
if ($onlyInPut || $onlyInGet) {
    $errors[] = 'GET/PUT key sets differ';
}

$after = $modx->getObject(msOrder::class, $orderId);
if (!$after) {
    $errors[] = 'Order missing after update';
} elseif ($requireDbToken && (string) $after->get('token') !== $dbToken) {
    $errors[] = 'DB token changed after update';
}

if ($errors) {
    foreach ($errors as $error) {
        fwrite(STDERR, "FAIL: {$error}\n");
    }
    exit(1);
}

$pass('GET and PUT payloads match (no token, shared shape keys)');
exit(0);
