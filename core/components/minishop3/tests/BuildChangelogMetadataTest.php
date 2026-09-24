<?php

/**
 * Smoke: в метаданные пакета уходит блок выпущенной версии, а не [Unreleased] (#296, #783).
 *
 * Run: php tests/BuildChangelogMetadataTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$root = dirname(__DIR__, 4);

$buildSrc = file_get_contents($root . '/_build/build.php');
if ($buildSrc === false) {
    $fail('_build/build.php not readable');
}

if (!str_contains($buildSrc, "strcasecmp(trim(\$match[1]), 'Unreleased')")) {
    $fail('readLatestChangelogEntry() must skip the [Unreleased] heading, or package metadata '
        . 'ships "Unreleased" instead of the version being installed');
}

/**
 * Same extraction the builder performs.
 */
$latest = static function (string $changelog): ?array {
    if (!preg_match_all('/^##\s+\[([^\]]+)\][^\n]*\n.*?(?=^##\s+\[|\z)/ms', $changelog, $matches, PREG_SET_ORDER)) {
        return null;
    }

    foreach ($matches as $match) {
        if (strcasecmp(trim($match[1]), 'Unreleased') === 0) {
            continue;
        }

        return [trim($match[1]), rtrim($match[0])];
    }

    return null;
};

$fixture = <<<'MD'
# Changelog

## [Unreleased]

### Changed
- something not released yet

## [9.9.9-beta1]

### Fixed
- released entry

## [9.9.8-beta1]

### Fixed
- older entry
MD;

$picked = $latest($fixture);
if ($picked === null) {
    $fail('extraction returned nothing for the fixture');
}
if ($picked[0] !== '9.9.9-beta1') {
    $fail('expected the newest released block, got [' . $picked[0] . ']');
}
if (str_contains($picked[1], 'not released yet')) {
    $fail('the [Unreleased] body leaked into the extracted block');
}
if (str_contains($picked[1], 'older entry')) {
    $fail('extraction must stop at the next version heading');
}

// A file without [Unreleased] must still yield its first block.
$noUnreleased = $latest("# Changelog\n\n## [1.0.0-pl]\n\n- first\n");
if ($noUnreleased === null || $noUnreleased[0] !== '1.0.0-pl') {
    $fail('a changelog without [Unreleased] must still resolve its latest block');
}

// The shipped changelog must resolve to a real version.
$changelog = file_get_contents(dirname(__DIR__) . '/docs/changelog.txt');
if ($changelog === false) {
    $fail('docs/changelog.txt not readable');
}

$real = $latest($changelog);
if ($real === null) {
    $fail('docs/changelog.txt has no version block');
}
if (!preg_match('/^\d+\.\d+\.\d+/', $real[0])) {
    $fail('docs/changelog.txt resolves to [' . $real[0] . '], expected a version number');
}

// #296: serialized metadata must fit modx_transport_packages.metadata (TEXT, 65535).
if (strlen($real[1]) > 60000) {
    $fail('the latest changelog block is ' . strlen($real[1]) . ' bytes, too close to the TEXT limit');
}

fwrite(STDOUT, "OK: BuildChangelogMetadataTest (метаданные: [{$real[0]}], " . strlen($real[1]) . " байт)\n");
