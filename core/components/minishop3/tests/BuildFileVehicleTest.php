<?php

/**
 * Smoke: файлы едут отдельными vehicle и не архивируются в .preserved.zip (#783).
 *
 * Run: php tests/BuildFileVehicleTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$buildDir = dirname(__DIR__, 4) . '/_build';
$buildSrc = file_get_contents($buildDir . '/build.php');
if ($buildSrc === false) {
    $fail('_build/build.php not readable');
}

// File resolvers on the category vehicle inherit PRESERVE_PREEXISTING, which is
// exactly what zipped the installed core/components/minishop3 on every upgrade.
if (preg_match("/resolve\(\s*'file'/", $buildSrc)) {
    $fail("build.php must not ship files through resolve('file'): such a resolver inherits "
        . 'PRESERVE_PREEXISTING from the category vehicle and re-archives the whole component');
}

foreach (['xPDOFileVehicle::class', 'xPDOTransport::PREEXISTING_MODE', 'xPDOTransport::REMOVE_PREEXISTING'] as $needle) {
    if (!str_contains($buildSrc, $needle)) {
        $fail("build.php must reference {$needle} to keep files out of the preserved archive");
    }
}

foreach (['use xPDO\\Transport\\xPDOFileVehicle;', 'use xPDO\\Transport\\xPDOTransport;'] as $import) {
    if (!str_contains($buildSrc, $import)) {
        $fail('build.php is missing import: ' . $import);
    }
}

// Both filesets must travel this way, or the one left behind keeps archiving.
foreach (["MODX_CORE_PATH . 'components/';", "MODX_ASSETS_PATH . 'components/';"] as $target) {
    if (substr_count($buildSrc, $target) !== 1) {
        $fail('expected exactly one packaged fileset for target ' . $target);
    }
}

// PHP resolvers hang off the category vehicle and run after it, so the files have
// to be registered earlier — resolver_02 needs vendor/autoload.php on disk.
$filesPos = strpos($buildSrc, '$this->packageFiles();');
$vehiclePos = strpos($buildSrc, '$this->builder->createVehicle($this->category');
if ($filesPos === false) {
    $fail('build.php must call packageFiles()');
}
if ($vehiclePos === false || $filesPos > $vehiclePos) {
    $fail('packageFiles() must run before the category vehicle is created');
}

// The category vehicle keeps the default mode: changing it there would also alter
// how its objects behave on uninstall. Match the whole array — a lazy [^\]]* stops
// at the first nested [] (RELATED_OBJECT_ATTRIBUTES) and never reaches the key.
if (preg_match('/\$this->category_attributes\s*=\s*\[(.*?)\n\s*\];/s', $buildSrc, $attrs) !== 1) {
    $fail('could not locate the $this->category_attributes array in build.php');
}
if (preg_match('/preexisting_mode/i', $attrs[1])) {
    $fail('the category vehicle must keep the default preexisting_mode');
}

fwrite(STDOUT, "OK: BuildFileVehicleTest\n");
