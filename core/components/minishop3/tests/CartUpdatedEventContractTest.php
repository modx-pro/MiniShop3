<?php

/**
 * Regression #802: ms3:cart:updated must always carry detail and must only
 * fire for real cart changes.
 *
 * OrderUI.handleClean() used to dispatch it without detail (TypeError in
 * subscribers written per the docs) even though order/clean resets the order
 * draft and never touches the cart. It now fires ms3:order:cleaned instead,
 * and OrderUI recalculates costs on that dedicated event.
 *
 * Run: php tests/CartUpdatedEventContractTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL CartUpdatedEventContractTest: {$message}\n");
    exit(1);
};

$webDir = dirname(__DIR__, 4) . '/assets/components/minishop3/js/web';
if (!is_dir($webDir)) {
    $fail("Missing {$webDir}");
}

// Every ms3:cart:updated dispatch in the storefront scripts must pass detail.
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($webDir, FilesystemIterator::SKIP_DOTS)
);
$dispatchCount = 0;
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'js' || str_contains($file->getPathname(), '/lib/')) {
        continue;
    }
    $source = (string) file_get_contents($file->getPathname());
    if (!preg_match_all('/new CustomEvent\(\s*[\'"`]ms3:cart:updated[\'"`]\s*,\s*\{(.*?)\}\s*\)/s', $source, $found)) {
        continue;
    }
    foreach ($found[1] as $args) {
        $dispatchCount++;
        if (!preg_match('/\bdetail\s*:/', $args)) {
            $fail($file->getFilename() . ' dispatches ms3:cart:updated without a detail key (#802)');
        }
    }
}
if ($dispatchCount === 0) {
    $fail('no ms3:cart:updated dispatch found — the contract scan is broken');
}

$orderUiPath = $webDir . '/ui/OrderUI.js';
$orderUi = (string) file_get_contents($orderUiPath);
if ($orderUi === '') {
    $fail("Cannot read {$orderUiPath}");
}

// order/clean does not change the cart: OrderUI must not fire the cart event.
if (preg_match('/new CustomEvent\(\s*[\'"`]ms3:cart:updated[\'"`]/', $orderUi)) {
    $fail('OrderUI must not dispatch ms3:cart:updated: order/clean never touches the cart (#802)');
}

// The dedicated event carries detail and drives the cost recalculation.
if (!preg_match('/new CustomEvent\(\s*[\'"`]ms3:order:cleaned[\'"`]\s*,\s*\{[^}]*\bdetail\s*:\s*response/s', $orderUi)) {
    $fail('OrderUI must dispatch ms3:order:cleaned with detail: response (#802)');
}
foreach (['ms3:order:cleaned', 'ms3:cart:updated'] as $event) {
    if (!preg_match('/addEventListener\(\s*[\'"`]' . preg_quote($event, '/') . '[\'"`]\s*,\s*\(\)\s*=>\s*\{[^}]*updateOrderCosts\(\)/s', $orderUi)) {
        $fail("OrderUI listener for {$event} must actually recalculate costs (#802)");
    }
}
if (!str_contains($orderUi, 'orderListenersRegistered')) {
    $fail('Document listeners must be registered once across repeated init() calls (#802)');
}

fwrite(STDOUT, "OK CartUpdatedEventContractTest ({$dispatchCount} cart dispatches with detail, #802)\n");
exit(0);
