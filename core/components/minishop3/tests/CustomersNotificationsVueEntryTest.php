<?php

/**
 * Customers/Notifications mgr pages: Vue entry without Ext wrappers (#525 / #521 Phase 1d).
 *
 * Run: php tests/CustomersNotificationsVueEntryTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$componentRoot = dirname(__DIR__);
$repoRoot = dirname($componentRoot, 3);
$assetsMgr = $repoRoot . '/assets/components/minishop3/js/mgr';

$pages = [
    'customers' => 'ms3-customers-vue-wrapper',
    'notifications' => 'ms3-notifications-vue-wrapper',
];

foreach ($pages as $name => $mountId) {
    $wrapper = "{$assetsMgr}/{$name}/{$name}.wrapper.js";
    if (is_file($wrapper)) {
        $fail('Ext wrapper must be deleted: ' . str_replace($repoRoot . '/', '', $wrapper));
    }

    $tpl = "{$componentRoot}/templates/default/{$name}.tpl";
    $tplRelative = str_replace($repoRoot . '/', '', $tpl);
    if (!is_file($tpl)) {
        $fail("missing template {$tplRelative}");
    }
    $html = file_get_contents($tpl);
    if ($html === false || !str_contains($html, 'id="' . $mountId . '"')) {
        $fail("{$tplRelative} must contain mount #{$mountId}");
    }

    $controller = "{$componentRoot}/controllers/mgr/{$name}.class.php";
    $contents = file_get_contents($controller);
    if ($contents === false) {
        $fail("cannot read {$name} controller");
    }

    $forbidden = [
        "{$name}\\.wrapper\\.js",
        'minishop3\\.js',
        'MODx\\.add',
        'Ext\\.onReady',
        'bootstrap\\.buttons\\.css',
    ];
    foreach ($forbidden as $pattern) {
        if (preg_match('#' . $pattern . '#', $contents) === 1) {
            $fail("{$name} controller must not contain /{$pattern}/");
        }
    }

    foreach (["{$name}\\.min\\.js", "{$name}\\.tpl", 'addVueConfig'] as $pattern) {
        if (preg_match('#' . $pattern . '#', $contents) !== 1) {
            $fail("{$name} controller must contain /{$pattern}/");
        }
    }

    if (!str_contains($contents, 'function getTemplateFile')) {
        $fail("{$name} controller must define getTemplateFile()");
    }

    $entry = "{$repoRoot}/vueManager/src/entries/{$name}.js";
    $entryRelative = str_replace($repoRoot . '/', '', $entry);
    $entryContents = file_get_contents($entry);
    if ($entryContents === false) {
        $fail("cannot read {$entryRelative}");
    }
    if (str_contains($entryContents, 'waitForElement')) {
        $fail("{$entryRelative} must not use waitForElement after tpl mount (#525)");
    }
}

// Base controller must ship ms3.config via addVueConfig() and inject the manager auth
// token synchronously — guards the HTTP_MODAUTH cold-start race fix (#544).
$base = "{$componentRoot}/controllers/manager.class.php";
$baseContents = file_get_contents($base);
if ($baseContents === false) {
    $fail('cannot read base controllers/manager.class.php');
}
foreach (['function addVueConfig', 'var ms3', 'getUserToken'] as $pattern) {
    if (!str_contains($baseContents, $pattern)) {
        $fail("base manager controller must contain {$pattern} (#544 token injection)");
    }
}

fwrite(STDOUT, "OK CustomersNotificationsVueEntryTest\n");
exit(0);
