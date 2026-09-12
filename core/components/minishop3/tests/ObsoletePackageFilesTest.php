<?php

/**
 * #704: obsolete Extra files are listed and purged only under the component root.
 *
 * Run: php tests/ObsoletePackageFilesTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL ObsoletePackageFilesTest: {$message}\n");
    exit(1);
};

$repoRoot = dirname(__DIR__, 4);
$coreRoot = dirname(__DIR__);
$configPath = $coreRoot . '/config/obsolete_package_files.php';
$resolverPath = $repoRoot . '/_build/resolvers/resolver_10_obsolete_files.php';
$helperPath = $coreRoot . '/src/Utils/ObsoletePackageFiles.php';

if (!is_readable($configPath)) {
    $fail('config/obsolete_package_files.php missing');
}
if (!is_readable($resolverPath)) {
    $fail('resolver_10_obsolete_files.php missing');
}
if (!is_readable($helperPath)) {
    $fail('ObsoletePackageFiles.php missing');
}

$resolverSrc = file_get_contents($resolverPath);
if (!is_string($resolverSrc)) {
    $fail('cannot read resolver_10_obsolete_files.php');
}
if (!str_contains($resolverSrc, 'ACTION_UPGRADE')) {
    $fail('resolver must run on ACTION_UPGRADE');
}
if (!str_contains($resolverSrc, 'ObsoletePackageFiles')) {
    $fail('resolver must use ObsoletePackageFiles');
}

/** @var array{core?: mixed, assets?: mixed} $list */
$list = require $configPath;
if (!isset($list['core']) || !is_array($list['core'])) {
    $fail('obsolete list must have a core array');
}

$core = [];
foreach ($list['core'] as $path) {
    if (!is_string($path) || $path === '') {
        $fail('core obsolete path must be a non-empty string');
    }
    if (str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, "\0")) {
        $fail("unsafe obsolete path: {$path}");
    }
    $core[] = $path;
}

if ($core !== array_values(array_unique($core))) {
    $fail('core obsolete paths must be unique');
}
if (!in_array('src/Processors/Product/Autocomplete.php', $core, true)) {
    $fail('obsolete list must include Product/Autocomplete.php');
}

foreach ($core as $path) {
    $full = $coreRoot . '/' . $path;
    if (is_file($full)) {
        $fail("obsolete path still ships in the Extra: {$path}");
    }
}

fwrite(STDOUT, "OK ObsoletePackageFilesTest\n");
exit(0);
