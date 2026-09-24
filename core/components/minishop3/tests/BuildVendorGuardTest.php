<?php

/**
 * Smoke: the transport build refuses a vendor/ that still holds require-dev packages (#779).
 *
 * Run: php tests/BuildVendorGuardTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$buildDir = dirname(__DIR__, 4) . '/_build';
require_once $buildDir . '/vendor_guard.php';

$buildSrc = file_get_contents($buildDir . '/build.php');
if ($buildSrc === false) {
    $fail('_build/build.php not readable');
}

$guardPos = strpos($buildSrc, '$this->assertProductionVendor();');
if ($guardPos === false) {
    $fail('build.php must call assertProductionVendor()');
}

// Ahead of initialize(), or a rejected build still leaves a half-made package behind.
foreach (['$this->initialize();', '$this->builder->createVehicle('] as $marker) {
    $pos = strpos($buildSrc, $marker);
    if ($pos === false || $guardPos > $pos) {
        $fail('the vendor guard must run before ' . $marker);
    }
}

$removeTree = static function (string $dir) use (&$removeTree): void {
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry;
        is_dir($path) ? $removeTree($path) : @unlink($path);
    }
    @rmdir($dir);
};

$tmpRoot = sys_get_temp_dir() . '/ms3-vendor-guard-' . bin2hex(random_bytes(6));

$makeCore = static function (string $name, ?array $installed) use ($tmpRoot, $fail): string {
    $core = $tmpRoot . '/' . $name . '/';
    if (!mkdir($core . 'vendor/composer', 0777, true)) {
        $fail('could not create ' . $core);
    }
    if ($installed !== null) {
        file_put_contents($core . 'vendor/composer/installed.json', json_encode($installed));
    }

    return $core;
};

try {
    $devInstall = $makeCore('dev', [
        'packages' => [],
        'dev' => true,
        'dev-package-names' => ['phpunit/phpunit', 'phpstan/phpstan'],
    ]);
    $problem = ms3BuildVendorProblem($devInstall);
    if ($problem === null || !str_contains($problem, 'composer install --no-dev')) {
        $fail('a dev install must be rejected with the --no-dev instruction, got: ' . var_export($problem, true));
    }

    $prodInstall = $makeCore('prod', [
        'packages' => [],
        'dev' => false,
        'dev-package-names' => [],
    ]);
    if (ms3BuildVendorProblem($prodInstall) !== null) {
        $fail('a --no-dev install must pass the guard');
    }

    // Composer omits both keys in some layouts; that is still a clean tree.
    $bareInstall = $makeCore('bare', ['packages' => []]);
    if (ms3BuildVendorProblem($bareInstall) !== null) {
        $fail('installed.json without dev markers must pass the guard');
    }

    $missing = $makeCore('missing', null);
    if (ms3BuildVendorProblem($missing) === null) {
        $fail('a vendor without installed.json must be rejected');
    }
} finally {
    if (is_dir($tmpRoot)) {
        $removeTree($tmpRoot);
    }
}

fwrite(STDOUT, "OK: BuildVendorGuardTest\n");
