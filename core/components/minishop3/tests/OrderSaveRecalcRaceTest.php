<?php

/**
 * Guards order save/recalculate in-flight lock (issue #379).
 *
 * After #339 the guards live in domain composables, not OrderView.vue.
 *
 * Run: php tests/OrderSaveRecalcRaceTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$repoRoot = dirname(__DIR__, 4);
$saveComposable = $repoRoot . '/vueManager/src/composables/useOrderSave.js';
$recalcComposable = $repoRoot . '/vueManager/src/composables/useOrderCostRecalc.js';
$actionsBar = $repoRoot . '/vueManager/src/components/order/OrderFormActionsBar.vue';

foreach (
    [
        $saveComposable => 'useOrderSave.js',
        $recalcComposable => 'useOrderCostRecalc.js',
        $actionsBar => 'OrderFormActionsBar.vue',
    ] as $path => $label
) {
    if (!is_readable($path)) {
        $fail("cannot read {$label}");
    }
}

$saveSource = file_get_contents($saveComposable);
$recalcSource = file_get_contents($recalcComposable);
$actionsBarSource = file_get_contents($actionsBar);

if ($saveSource === false || $recalcSource === false || $actionsBarSource === false) {
    $fail('cannot read vue sources');
}

if (!preg_match('/async function saveOrder\(\)[\s\S]*?if \(recalculatingCost\.value \|\| saving\.value\)/', $saveSource)) {
    $fail('saveOrder must bail out when recalculatingCost or saving is active');
}

if (!preg_match('/async function recalculateOrderCost[\s\S]*?if \(saving\.value \|\| recalculatingCost\.value\)/', $recalcSource)) {
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
