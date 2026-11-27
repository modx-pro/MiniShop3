<?php

/**
 * #931: no API or storefront response may carry exception details.
 *
 * Run: php tests/ApiDebugLeakTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$root = dirname(__DIR__, 4);
$api = file_get_contents($root . '/assets/components/minishop3/api.php');
if ($api === false) {
    $fail('Unable to read api.php');
}
if (!str_contains($api, 'ApiDebugPolicy::class')) {
    $fail('api.php must gate debug output through ApiDebugPolicy (#931)');
}
if (!str_contains($api, "class_exists(\$policyClass, false)")) {
    $fail('api.php must load the policy defensively before the autoloader exists (#931)');
}
if (str_contains($api, "getOption('debug'")) {
    $fail('api.php must not expose details based on the global MODX debug setting (#931)');
}
if (!str_contains($api, "'message' => 'Internal server error'")) {
    $fail('api.php must keep the generic error message (#931)');
}

$policy = file_get_contents($root . '/core/components/minishop3/src/Utils/ApiDebugPolicy.php');
if ($policy === false || !str_contains($policy, 'hasSessionContext') || !str_contains($policy, 'contextTokens')) {
    $fail('ApiDebugPolicy must verify the manager identity, not just a session flag (#931)');
}

$trait = file_get_contents($root . '/core/components/minishop3/src/Processors/Api/ProcessesManagerConnectorRouteTrait.php');
if ($trait === false || !str_contains($trait, "failure('Internal server error'")) {
    $fail('Manager connector must always return a generic failure message (#931)');
}

$files = [
    'src/Controllers/Api/ConfigController.php',
    'src/Controllers/Api/ProductDataController.php',
    'src/Controllers/Api/ReferencesController.php',
    'src/Controllers/Api/Manager/ExtraFieldsController.php',
    'src/Processors/Api/ProcessesManagerConnectorRouteTrait.php',
    'src/Processors/Utilities/Import/Preview.php',
    'src/Services/GridConfigService.php',
    'src/Services/ExtraFieldsService.php',
    'src/Services/Order/ManagerOrderMutationService.php',
    'elements/snippets/ms3_products.php',
    'config/routes/manager.php',
];
foreach ($files as $relative) {
    $path = $root . '/core/components/minishop3/' . $relative;
    $source = file_get_contents($path);
    if ($source === false) {
        $fail("Unable to read {$relative}");
    }
    if (preg_match("/getMessage\(\)/", $source) === 1
        && preg_match("/(message|failure)[^;]{0,120}getMessage\(\)/", $source) === 1) {
        $fail("{$relative} must not return exception messages to clients (#931)");
    }
    if ($relative !== 'elements/snippets/ms3_products.php' && !str_contains($source, 'getMessage()')) {
        $fail("{$relative} must still log exception details (#931)");
    }
}

foreach (['ru', 'en'] as $language) {
    $lexiconPath = $root . "/core/components/minishop3/lexicon/{$language}/setting.inc.php";
    $lexicon = file_get_contents($lexiconPath);
    if ($lexicon === false || !str_contains($lexicon, 'setting_ms3_api_debug_desc')) {
        $fail("{$language} lexicon missing ms3_api_debug description (#931)");
    }
    $managerWord = $language === 'ru' ? 'менеджеру' : 'manager';
    if (!str_contains($lexicon, $managerWord)) {
        $fail("{$language} ms3_api_debug description must state that output is manager-only (#931)");
    }
}

fwrite(STDOUT, "OK ApiDebugLeakTest (#931)\n");
exit(0);
