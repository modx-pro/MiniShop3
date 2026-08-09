<?php

/**
 * Guards OrderView save/recalculate in-flight lock (issue #379).
 *
 * Run: php tests/OrderSaveRecalcRaceTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$repoRoot = dirname(__DIR__, 4);
$orderView = $repoRoot . '/vueManager/src/components/OrderView.vue';
$actionsBar = $repoRoot . '/vueManager/src/components/order/OrderFormActionsBar.vue';

foreach ([$orderView => 'OrderView.vue', $actionsBar => 'OrderFormActionsBar.vue'] as $path => $label) {
    if (!is_readable($path)) {
        $fail("cannot read {$label}");
    }
}

$orderViewSource = file_get_contents($orderView);
$actionsBarSource = file_get_contents($actionsBar);

if ($orderViewSource === false || $actionsBarSource === false) {
    $fail('cannot read vue sources');
}

if (!preg_match('/async function saveOrder\(\)[\s\S]*?if \(recalculatingCost\.value \|\| saving\.value\)/', $orderViewSource)) {
    $fail('saveOrder must bail out when recalculatingCost or saving is active');
}

if (!preg_match('/async function recalculateOrderCost[\s\S]*?if \(saving\.value \|\| recalculatingCost\.value\)/', $orderViewSource)) {
    $fail('recalculateOrderCost must bail out when saving or recalculatingCost is active');
}

if (!str_contains($actionsBarSource, 'recalculatingCost')) {
    $fail('OrderFormActionsBar must accept recalculatingCost prop');
}

if (!str_contains($actionsBarSource, ':disabled="recalculatingCost"')) {
    $fail('Save button must be disabled while recalculatingCost');
}

fwrite(STDOUT, "OK OrderSaveRecalcRaceTest\n");
exit(0);
