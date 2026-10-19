<?php

/**
 * Snippet properties declared in _build/elements/snippets.php must match what the
 * snippets really read, and every declared property must have a localized
 * description in properties.inc.php (#799).
 *
 * MODX shows declared properties in the snippet properties grid and pulls the
 * description from `ms3_prop_<snake_case>` (see resolver_08_snippet_properties.php).
 * A property that works but is not declared is invisible in the manager; a declared
 * property without a lexicon key shows the raw key as its description.
 *
 * Run: php tests/SnippetPropertiesDeclaredTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL SnippetPropertiesDeclaredTest: {$message}\n");
    exit(1);
};

$snippetsPath = $root . '/_build/elements/snippets.php';
if (!is_readable($snippetsPath)) {
    $fail("Missing {$snippetsPath}");
}

$snippets = require $snippetsPath;
if (!is_array($snippets)) {
    $fail('snippets.php must return an array');
}

/** camelCase -> snake_case, same conversion as resolver_08_snippet_properties.php. */
$toSnake = static function (string $name): string {
    if ($name === 'includeTVs') {
        return 'include_tvs';
    }

    return strtolower((string)preg_replace('/([A-Z])/', '_$1', $name));
};

$lexicons = [];
foreach (['en', 'ru'] as $lang) {
    $path = "{$root}/core/components/minishop3/lexicon/{$lang}/properties.inc.php";
    if (!is_readable($path)) {
        $fail("Missing {$path}");
    }
    $lexicons[$lang] = (string)file_get_contents($path);
}

/**
 * Parameters that are read from $scriptProperties but must stay undeclared:
 * - programmatic/security values passed by other code, not by a template author;
 * - legacy aliases of a declared property;
 * - path overrides for advanced setups.
 */
$allowlist = [
    'msCart' => ['customer_token'],
    'msGallery' => ['extensionsDir'],
    'msProducts' => ['parent', 'category', 'includeVendor'],
    // &id must stay undeclared: ms3_get_order.php falls back to the ?msorder= query
    // parameter via `??`, so a declared '' default would shadow it.
    'msGetOrder' => ['id'],
];

$declaredCount = 0;

foreach ($snippets as $snippetName => $snippet) {
    $file = $snippet['file'] ?? '';
    $properties = $snippet['properties'] ?? [];
    if ($file === '' || !is_array($properties)) {
        $fail("Snippet {$snippetName} must declare file and properties");
    }

    foreach (array_keys($properties) as $propertyName) {
        $declaredCount++;
        $key = 'ms3_prop_' . $toSnake((string)$propertyName);
        foreach (array_keys($lexicons) as $lang) {
            if (!str_contains($lexicons[$lang], "\$_lang['{$key}']")) {
                $fail("{$snippetName}.{$propertyName} needs {$key} in {$lang}/properties.inc.php");
            }
        }
    }

    $snippetPath = "{$root}/core/components/minishop3/elements/snippets/{$file}.php";
    if (!is_readable($snippetPath)) {
        $fail("Snippet file not found: {$snippetPath}");
    }
    $source = (string)file_get_contents($snippetPath);

    $read = [];
    if (preg_match_all("/\\\$scriptProperties\[\s*'([^']+)'/", $source, $found)) {
        $read = array_merge($read, $found[1]);
    }
    if (preg_match_all("/getOption\(\s*'([^']+)'\s*,\s*\\\$scriptProperties/", $source, $found)) {
        $read = array_merge($read, $found[1]);
    }

    $skip = array_merge($allowlist[$snippetName] ?? [], ['ms3_' . $snippetName]);
    foreach (array_unique($read) as $name) {
        // Global settings (ms3_*) are read through $scriptProperties on purpose.
        if (str_starts_with($name, 'ms3_') || in_array($name, $skip, true)) {
            continue;
        }
        if (!array_key_exists($name, $properties)) {
            $fail(
                "{$file} reads &{$name} but {$snippetName} does not declare it"
                . ' — add it to snippets.php or the allowlist (#799)'
            );
        }
    }
}

fwrite(
    STDOUT,
    'OK SnippetPropertiesDeclaredTest (' . count($snippets) . " snippets,"
    . " {$declaredCount} declared properties × 2 langs)\n"
);
exit(0);