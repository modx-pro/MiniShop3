<?php

/**
 * Utilities mgr page: Vue entry without Ext tabs (#524 / #521 Phase 1c).
 *
 * Run: php tests/UtilitiesVueEntryTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertRegex = static function (
    string $haystack,
    string $pattern,
    string $label,
    bool $mustMatch
) use ($fail): void {
    $matched = preg_match('#' . $pattern . '#', $haystack) === 1;
    if ($mustMatch !== $matched) {
        $fail($label . ($mustMatch ? ' must contain' : ' must not contain') . " /{$pattern}/");
    }
};

$componentRoot = dirname(__DIR__);
$repoRoot = dirname($componentRoot, 3);
$assetsMgr = $repoRoot . '/assets/components/minishop3/js/mgr';

foreach ([
    $assetsMgr . '/utilities/utilities.js',
    $assetsMgr . '/utilities/utilities.panel.js',
    $assetsMgr . '/utilities/import/panel.js',
] as $path) {
    if (is_file($path)) {
        $fail('Ext utilities asset must be deleted: ' . str_replace($repoRoot . '/', '', $path));
    }
}

$tpl = $componentRoot . '/templates/default/utilities.tpl';
$tplHtml = is_file($tpl) ? file_get_contents($tpl) : false;
if ($tplHtml === false || !str_contains($tplHtml, 'id="ms3-vue-utilities"')) {
    $fail('utilities.tpl must contain mount #ms3-vue-utilities');
}

$controller = file_get_contents($componentRoot . '/controllers/mgr/utilities.class.php');
if ($controller === false) {
    $fail('cannot read utilities controller');
}

foreach ([
    'utilities\.js',
    'utilities\.panel\.js',
    'import/panel\.js',
    'minishop3\.js',
    'ms3\.combo\.js',
    'default\.grid\.js',
    'MODx\.add',
    'Ext\.onReady',
    'modx-tabs',
    'fields-management\.min\.js',
    'utilities-gallery\.min\.js',
] as $pattern) {
    $assertRegex($controller, $pattern, 'utilities controller', false);
}

foreach ([
    'utilities\.min\.js',
    'utilities\.tpl',
    'addVueConfig',
    'getTemplateFile',
    'mssetting_list',
    'utility_gallery_source_id',
    '\.min\.css',
    'primeicons',
    'utilities',
] as $pattern) {
    $assertRegex($controller, $pattern, 'utilities controller', true);
}

$entry = $repoRoot . '/vueManager/src/entries/utilities.js';
$page = $repoRoot . '/vueManager/src/components/UtilitiesPage.vue';
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
    $fail('cannot read utilities Vue sources');
}

if (!str_contains($entrySrc, 'UtilitiesPage')) {
    $fail('utilities entry must mount UtilitiesPage');
}
if (str_contains($entrySrc, 'waitForElement')) {
    $fail('utilities entry must not use waitForElement');
}
if (str_contains($pageSrc, 'defineAsyncComponent')) {
    $fail('UtilitiesPage must statically import tabs');
}
if (!str_contains($pageSrc, 'localStorage') || !str_contains($pageSrc, 'ms3-utilities-active-tab')) {
    $fail('UtilitiesPage must persist active tab in localStorage');
}

foreach (
    [
        'gallery',
        'import',
        'fields-management',
        'extra-fields',
        'grid-fields-config',
        'model-fields',
    ] as $tabId
) {
    if (!str_contains($pageSrc, "'" . $tabId . "'") && !str_contains($pageSrc, '"' . $tabId . '"')) {
        $fail("UtilitiesPage must declare tab {$tabId}");
    }
}

if (!preg_match("/['\"]utilities['\"]\\s*:\\s*['\"]src\\/entries\\/utilities\\.js['\"]/", $viteSrc)) {
    $fail('vite.config.js must include utilities entry');
}

foreach (
    [
        'fields-management',
        'extra-fields',
        'grid-fields-config',
        'model-fields',
        'import',
        'utilities-gallery',
    ] as $orphan
) {
    if (preg_match("/['\"]" . preg_quote($orphan, '/') . "['\"]\\s*:\\s*['\"]src\\/entries\\//", $viteSrc) === 1) {
        $fail("vite.config.js must not keep orphan utilities tab entry {$orphan}");
    }
    if (is_file($repoRoot . '/vueManager/src/entries/' . $orphan . '.js')) {
        $fail("orphan utilities tab entry must be deleted: vueManager/src/entries/{$orphan}.js");
    }
}

fwrite(STDOUT, "OK UtilitiesVueEntryTest\n");
exit(0);
