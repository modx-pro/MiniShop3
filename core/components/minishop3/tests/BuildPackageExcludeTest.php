<?php

/**
 * Smoke: transport build stages core without the test harness (#781).
 *
 * Run: php tests/BuildPackageExcludeTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$buildDir = dirname(__DIR__, 4) . '/_build';
require_once $buildDir . '/package_exclude.php';

$buildSrc = file_get_contents($buildDir . '/build.php');
if ($buildSrc === false) {
    $fail('_build/build.php not readable');
}

if (!str_contains($buildSrc, 'stageCorePackageTree()')) {
    $fail('build.php must stage the core tree via stageCorePackageTree()');
}
if (!str_contains($buildSrc, 'package_exclude.php')) {
    $fail('build.php must load package_exclude.php');
}
if (!str_contains($buildSrc, 'ms3BuildCoreExcludeItems()')) {
    $fail('build.php must apply ms3BuildCoreExcludeItems()');
}

$expected = [
    'tests',
    'scripts',
    '.phpunit.cache',
    'phpunit.xml',
    'phpunit.xml.dist',
    'phpunit.modx.xml',
    'composer.json',
    'composer.lock',
    '.gitignore',
];

$items = ms3BuildCoreExcludeItems();
sort($items);
$expectedSorted = $expected;
sort($expectedSorted);
if ($items !== $expectedSorted) {
    $fail('ms3BuildCoreExcludeItems() must match the frozen harness list from #781');
}

fwrite(STDOUT, "OK: BuildPackageExcludeTest\n");
