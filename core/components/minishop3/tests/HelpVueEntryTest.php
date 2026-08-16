<?php

/**
 * Help mgr page: Vue entry must declare `var ms3` via addVueConfig (#553).
 *
 * Run: php tests/HelpVueEntryTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$componentRoot = dirname(__DIR__);
$repoRoot = dirname($componentRoot, 3);

$tpl = $componentRoot . '/templates/default/help.tpl';
$tplHtml = is_file($tpl) ? file_get_contents($tpl) : false;
if ($tplHtml === false || !str_contains($tplHtml, 'id="ms3-vue-help"')) {
    $fail('help.tpl must contain mount #ms3-vue-help');
}

$controllerPath = $componentRoot . '/controllers/mgr/help.class.php';
$controller = file_get_contents($controllerPath);
if ($controller === false) {
    $fail('cannot read help controller');
}

foreach (['help\.min\.js', 'help\.tpl', 'addVueConfig', 'getTemplateFile'] as $pattern) {
    if (preg_match('#' . $pattern . '#', $controller) !== 1) {
        $fail("help controller must contain /{$pattern}/");
    }
}

if (preg_match('#ms3\.config\s*=#', $controller) === 1) {
    $fail('help controller must not assign ms3.config without declaring ms3 (#553)');
}

$configPos = strpos($controller, 'addVueConfig');
$modulePos = strpos($controller, 'addVueModule');
if ($configPos === false || $modulePos === false || $configPos > $modulePos) {
    $fail('help controller must call addVueConfig() before addVueModule()');
}

$entry = $repoRoot . '/vueManager/src/entries/help.js';
$entrySrc = is_file($entry) ? file_get_contents($entry) : false;
if ($entrySrc === false || !str_contains($entrySrc, '#ms3-vue-help')) {
    $fail('help entry must mount #ms3-vue-help');
}

fwrite(STDOUT, "OK HelpVueEntryTest\n");
exit(0);
