<?php

/**
 * Structural guards for draft EmptyOrder events and Order::set error aggregation (#343).
 *
 * Run: php tests/OrderDraftEventsTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$srcRoot = dirname(__DIR__) . '/src';
$orderPath = $srcRoot . '/Controllers/Order/Order.php';
$draftPath = $srcRoot . '/Services/Order/OrderDraftManager.php';
$calculatorPath = $srcRoot . '/Services/Order/OrderCostCalculator.php';

foreach (
    [
        $orderPath => 'Order.php',
        $draftPath => 'OrderDraftManager.php',
        $calculatorPath => 'OrderCostCalculator.php',
    ] as $path => $label
) {
    if (!is_readable($path)) {
        $fail("cannot read {$label}");
    }
}

$orderSource = file_get_contents($orderPath);
$draftSource = file_get_contents($draftPath);
$calculatorSource = file_get_contents($calculatorPath);

if ($orderSource === false || $draftSource === false || $calculatorSource === false) {
    $fail('cannot read sources');
}

// Order::set aggregates per-field add() failures into ms3_order_err_validation + data.errors
if (!preg_match(
    '/function set\(array \$order\): array[\s\S]*?\$errors\s*=\s*\[[\s\S]*?\$this->add\(\$key,\s*\$value\)/',
    $orderSource
)) {
    $fail('Order::set must collect per-field add() failures into $errors');
}

if (!preg_match(
    '/function set\(array \$order\): array[\s\S]*?ms3_order_err_validation[\s\S]*?[\'"]errors[\'"]\s*=>\s*\$errors/',
    $orderSource
)) {
    $fail('Order::set must return ms3_order_err_validation with data.errors');
}

// Order::clean propagates plugin refusal from draftManager->clean()
if (!preg_match(
    '/function clean\(\): array[\s\S]*?\$result\s*=\s*\$this->draftManager->clean\(\$this->draft\)[\s\S]*?\$result\s*!==\s*true/',
    $orderSource
)) {
    $fail('Order::clean must surface draftManager->clean() plugin refusal');
}

// OrderDraftManager::clean fires EmptyOrder hooks and returns bool|string
if (!str_contains($draftSource, "function clean(msOrder \$draft): bool|string")) {
    $fail('OrderDraftManager::clean must return bool|string');
}

if (!str_contains($draftSource, "invokeEvent('msOnBeforeEmptyOrder'")) {
    $fail('OrderDraftManager::clean must invoke msOnBeforeEmptyOrder');
}

if (!str_contains($draftSource, "invokeEvent('msOnEmptyOrder'")) {
    $fail('OrderDraftManager::clean must invoke msOnEmptyOrder');
}

// GetOrderCost already lives on beta in OrderCostCalculator (not this PR's delta) —
// keep a wiring guard so #343 AC for cost hooks stays green after EmptyOrder lands.
if (!str_contains($calculatorSource, "invokeEvent('msOnBeforeGetOrderCost'")) {
    $fail('OrderCostCalculator::getTotalCost must invoke msOnBeforeGetOrderCost');
}

if (!str_contains($calculatorSource, "invokeEvent('msOnGetOrderCost'")) {
    $fail('OrderCostCalculator::getTotalCost must invoke msOnGetOrderCost');
}

if (!preg_match(
    '/msOnGetOrderCost[\s\S]*?\$after\[\'data\'\]\[\'cost\'\][\s\S]*?\$after\[\'data\'\]\[\'cart_cost\'\][\s\S]*?\$after\[\'data\'\]\[\'delivery_cost\'\][\s\S]*?\$after\[\'data\'\]\[\'payment_cost\'\]/',
    $calculatorSource
)) {
    $fail('msOnGetOrderCost must apply returnedValues for cost/cart/delivery/payment');
}

fwrite(STDOUT, "OK OrderDraftEventsTest\n");
exit(0);
