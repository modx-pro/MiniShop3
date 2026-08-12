<?php

/**
 * Orders/Order mgr pages: Vue entry without Ext wrappers (#526).
 *
 * Run: php tests/OrdersVueEntryTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$componentRoot = dirname(__DIR__);
$repoRoot = dirname($componentRoot, 3);
$assetsMgr = $repoRoot . '/assets/components/minishop3/js/mgr';

foreach ([
    $assetsMgr . '/orders/orders.wrapper.js',
    $assetsMgr . '/orders/order.wrapper.js',
] as $path) {
    if (is_file($path)) {
        $fail('Ext wrapper must be deleted: ' . str_replace($repoRoot . '/', '', $path));
    }
}

foreach ([
    $componentRoot . '/templates/default/orders.tpl' => 'ms3-orders-vue-wrapper',
    $componentRoot . '/templates/default/order.tpl' => 'ms3-order-vue-wrapper',
] as $tpl => $mountId) {
    $relative = str_replace($repoRoot . '/', '', $tpl);
    if (!is_file($tpl)) {
        $fail("missing template {$relative}");
    }
    $html = file_get_contents($tpl);
    if ($html === false || !str_contains($html, 'id="' . $mountId . '"')) {
        $fail("{$relative} must contain mount #{$mountId}");
    }
}

$controllers = [
    'orders' => [
        'file' => $componentRoot . '/controllers/mgr/orders.class.php',
        'forbidden' => [
            'orders\.wrapper\.js',
            'minishop3\.js',
            'MODx\.add',
            'Ext\.onReady',
            'bootstrap\.buttons\.css',
        ],
        'required' => [
            'orders\.min\.js',
            'orders\.tpl',
            'order_show_drafts',
            'addVueConfig',
        ],
    ],
    'order' => [
        'file' => $componentRoot . '/controllers/mgr/order.class.php',
        'forbidden' => [
            'order\.wrapper\.js',
            'minishop3\.js',
            'MODx\.add',
            'Ext\.onReady',
            'bootstrap\.buttons\.css',
        ],
        'required' => [
            'order\.min\.js',
            'order\.tpl',
            'order_id',
            'addVueConfig',
        ],
    ],
];

foreach ($controllers as $name => $spec) {
    $contents = file_get_contents($spec['file']);
    if ($contents === false) {
        $fail("cannot read {$name} controller");
    }

    foreach ($spec['forbidden'] as $pattern) {
        if (preg_match('#' . $pattern . '#', $contents) === 1) {
            $fail("{$name} controller must not contain /{$pattern}/");
        }
    }

    foreach ($spec['required'] as $pattern) {
        if (preg_match('#' . $pattern . '#', $contents) !== 1) {
            $fail("{$name} controller must contain /{$pattern}/");
        }
    }

    if (!str_contains($contents, 'function getTemplateFile')) {
        $fail("{$name} controller must define getTemplateFile()");
    }
}

foreach ([
    $repoRoot . '/vueManager/src/entries/orders.js',
    $repoRoot . '/vueManager/src/entries/order.js',
] as $path) {
    $relative = str_replace($repoRoot . '/', '', $path);
    $contents = file_get_contents($path);
    if ($contents === false) {
        $fail("cannot read {$relative}");
    }
    if (str_contains($contents, 'waitForElement')) {
        $fail("{$relative} must not use waitForElement after tpl mount (#526)");
    }
}

fwrite(STDOUT, "OK OrdersVueEntryTest\n");
exit(0);
