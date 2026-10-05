<?php

/**
 * Customer token TTL must have exactly one setting, and its code default must match
 * the value shipped in the transport package (#797).
 *
 * `ms3_customer_api_token_ttl` was never read after the httpOnly cookie transition,
 * yet it sat in settings.php next to `ms3_customer_token_ttl` with the same value and
 * area, so admins edited the wrong one. The code fallback (604800) also drifted from
 * the shipped setting (86400), leaving two numbers for one meaning.
 *
 * Run: php tests/CustomerTokenTtlSettingTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$root = dirname(__DIR__, 4);
$settingsPath = $root . '/_build/elements/settings.php';
if (!is_readable($settingsPath)) {
    $fail("Missing {$settingsPath}");
}

$settingsSource = (string)file_get_contents($settingsPath);

if (str_contains($settingsSource, "'ms3_customer_api_token_ttl'")) {
    $fail('settings.php must not ship the unread ms3_customer_api_token_ttl (#797)');
}

if (!preg_match("/'ms3_customer_token_ttl' => \[\s*'value' => (\d+)/", $settingsSource, $m)) {
    $fail('settings.php must ship ms3_customer_token_ttl with a numeric value');
}

$shippedTtl = (int)$m[1];

// Every shipped setting keeps a localized title + description in both languages.
preg_match_all("/^\s{4}'([a-z0-9_]+)' => \[/m", $settingsSource, $settingKeys);
$shippedKeys = $settingKeys[1] ?: [];

if ($shippedKeys === []) {
    $fail('settings.php must define settings');
}

foreach (['en', 'ru'] as $lang) {
    $lexiconPath = "{$root}/core/components/minishop3/lexicon/{$lang}/setting.inc.php";
    if (!is_readable($lexiconPath)) {
        $fail("Missing {$lexiconPath}");
    }
    $lexicon = (string)file_get_contents($lexiconPath);

    foreach ($shippedKeys as $key) {
        if (!str_contains($lexicon, "\$_lang['setting_{$key}']")) {
            $fail("{$lang}/setting.inc.php misses setting_{$key} (#797)");
        }
        if (!str_contains($lexicon, "\$_lang['setting_{$key}_desc']")) {
            $fail("{$lang}/setting.inc.php misses setting_{$key}_desc (#797)");
        }
    }

    if (str_contains($lexicon, 'setting_ms3_customer_api_token_ttl')) {
        $fail("{$lang}/setting.inc.php keeps the removed setting_ms3_customer_api_token_ttl lexicon (#797)");
    }
}

// Code fallbacks must not drift away from the shipped value.
$codeRoots = [
    "{$root}/core/components/minishop3/src",
];
$readSites = [];

foreach ($codeRoots as $codeRoot) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($codeRoot));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $source = (string)file_get_contents($file->getPathname());
        if (preg_match_all("/getOption\(\s*'ms3_customer_token_ttl'\s*,\s*null\s*,\s*(\d+)\s*\)/", $source, $found)) {
            foreach ($found[1] as $default) {
                $readSites[$file->getPathname()][] = (int)$default;
            }
        }
    }
}

if ($readSites === []) {
    $fail('No code reads ms3_customer_token_ttl — the setting must stay the single source of truth');
}

foreach ($readSites as $path => $defaults) {
    foreach ($defaults as $default) {
        if ($default !== $shippedTtl) {
            $fail(
                basename($path) . " defaults ms3_customer_token_ttl to {$default},"
                . " settings.php ships {$shippedTtl} (#797)"
            );
        }
    }
}

// Installed sites must lose the dangling setting row on upgrade.
$migrationFound = false;
foreach (glob("{$root}/core/components/minishop3/migrations/[0-9]*remove*api_token_ttl*.php") ?: [] as $migration) {
    $source = (string)file_get_contents($migration);
    if (str_contains($source, "'ms3_customer_api_token_ttl'")
        && str_contains($source, 'DELETE FROM')
    ) {
        $migrationFound = true;
        break;
    }
}

if (!$migrationFound) {
    $fail('No migration deletes the ms3_customer_api_token_ttl setting row (#797)');
}

fwrite(
    STDOUT,
    'OK CustomerTokenTtlSettingTest (' . count($shippedKeys) . " settings × 2 langs,"
    . count($readSites) . " read sites @ {$shippedTtl}s + cleanup migration)\n"
);
exit(0);