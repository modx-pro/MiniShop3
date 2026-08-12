<?php

/**
 * Settings mgr page: Vue entry without Ext tabs (#523 / #521 Phase 1b).
 *
 * Run: php tests/SettingsVueEntryTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$mustContain = static function (string $haystack, string $pattern, string $label) use ($fail): void {
    if (preg_match('#' . $pattern . '#', $haystack) !== 1) {
        $fail("{$label} must contain /{$pattern}/");
    }
};

$mustNotContain = static function (string $haystack, string $pattern, string $label) use ($fail): void {
    if (preg_match('#' . $pattern . '#', $haystack) === 1) {
        $fail("{$label} must not contain /{$pattern}/");
    }
};

$componentRoot = dirname(__DIR__);
$repoRoot = dirname($componentRoot, 3);
$assetsMgr = $repoRoot . '/assets/components/minishop3/js/mgr';

foreach ([
    $assetsMgr . '/settings/settings.js',
    $assetsMgr . '/settings/settings.panel.js',
] as $path) {
    if (is_file($path)) {
        $fail('Ext settings asset must be deleted: ' . str_replace($repoRoot . '/', '', $path));
    }
}

$tpl = $componentRoot . '/templates/default/settings.tpl';
if (!is_file($tpl)) {
    $fail('missing templates/default/settings.tpl');
}
$tplHtml = file_get_contents($tpl);
if ($tplHtml === false || !str_contains($tplHtml, 'id="ms3-vue-settings"')) {
    $fail('settings.tpl must contain mount #ms3-vue-settings');
}

$controller = file_get_contents($componentRoot . '/controllers/mgr/settings.class.php');
if ($controller === false) {
    $fail('cannot read settings controller');
}

foreach ([
    'settings\.js',
    'settings\.panel\.js',
    'minishop3\.js',
    'ms3\.combo\.js',
    'default\.grid\.js',
    'MODx\.add',
    'Ext\.onReady',
    'modx-tabs',
    'deliveries\.min\.js',
] as $pattern) {
    $mustNotContain($controller, $pattern, 'settings controller');
}

foreach ([
    'settings\.min\.js',
    'settings\.tpl',
    'addVueConfig',
    'getTemplateFile',
    'msorder_list',
    '\.min\.css',
] as $pattern) {
    $mustContain($controller, $pattern, 'settings controller');
}

foreach ([
    'primeicons',
    'settings',
    'DynamicField',
    'ActionsColumn',
    'ResourceCategoryTree',
] as $asset) {
    $mustContain($controller, preg_quote($asset, '#'), 'settings controller css asset');
}

foreach ([
    'FileBrowser',
    'DeliveriesGrid',
    'PaymentsGrid',
    'StatusesGrid',
    'VendorsGrid',
    'LinksGrid',
    'OptionsAndGroupsTabs',
] as $merged) {
    // Absorbed into settings.min.css after single settings entry (no separate tab entries).
    if (preg_match('#\'' . preg_quote($merged, '#') . '\'#', $controller) === 1) {
        $fail("settings controller must not list CSS merged into settings.min.css: {$merged}");
    }
}

$entry = $repoRoot . '/vueManager/src/entries/settings.js';
$page = $repoRoot . '/vueManager/src/components/SettingsPage.vue';
$vite = $repoRoot . '/vueManager/vite.config.js';

foreach ([$entry, $page, $vite] as $path) {
    if (!is_file($path)) {
        $fail('missing ' . str_replace($repoRoot . '/', '', $path));
    }
}

$entrySrc = file_get_contents($entry);
$pageSrc = file_get_contents($page);
$viteSrc = file_get_contents($vite);
if ($entrySrc === false || $pageSrc === false || $viteSrc === false) {
    $fail('cannot read settings Vue sources');
}

if (!str_contains($entrySrc, 'SettingsPage')) {
    $fail('settings entry must mount SettingsPage');
}
if (str_contains($entrySrc, 'waitForElement')) {
    $fail('settings entry must not use waitForElement');
}
if (str_contains($pageSrc, 'defineAsyncComponent')) {
    $fail('SettingsPage must statically import tabs (Vite CSS via assetsUrl / settings.min.css)');
}
if (!str_contains($pageSrc, 'window.location.hash') || str_contains($pageSrc, 'replaceState')) {
    $fail('SettingsPage must sync tabs via location.hash (not replaceState)');
}
foreach (['deliveries', 'payments', 'statuses', 'vendors', 'links', 'options'] as $tabId) {
    if (!str_contains($pageSrc, "'" . $tabId . "'") && !str_contains($pageSrc, '"' . $tabId . '"')) {
        $fail("SettingsPage must declare tab {$tabId}");
    }
}
if (!preg_match("/['\"]settings['\"]\\s*:\\s*['\"]src\\/entries\\/settings\\.js['\"]/", $viteSrc)) {
    $fail('vite.config.js must include settings entry');
}
foreach (['deliveries', 'payments', 'statuses', 'vendors', 'links', 'options'] as $orphan) {
    if (preg_match("/['\"]" . preg_quote($orphan, '/') . "['\"]\\s*:\\s*['\"]src\\/entries\\//", $viteSrc) === 1) {
        $fail("vite.config.js must not keep orphan settings tab entry {$orphan}");
    }
    $orphanPath = $repoRoot . '/vueManager/src/entries/' . $orphan . '.js';
    if (is_file($orphanPath)) {
        $fail("orphan settings tab entry must be deleted: vueManager/src/entries/{$orphan}.js");
    }
}

fwrite(STDOUT, "OK SettingsVueEntryTest\n");
exit(0);
