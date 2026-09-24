<?php

/**
 * Smoke: upgrading over a live vendor must not fatal on a renamed autoloader (#779).
 *
 * bootstrap.php require_once's vendor/composer/autoload_real.php on every MODX
 * request. The transport then unpacks a new vendor over it, so `require
 * vendor/autoload.php` in the migrations resolver re-reads the new file while its
 * require_once on the unchanged autoload_real.php path is a no-op — and the call to
 * ComposerAutoloaderInit* hits a class nobody declared.
 *
 * Run: php tests/PhinxVendorAutoloadUpgradeTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$componentPath = dirname(__DIR__);
require __DIR__ . '/../vendor/autoload.php';
require_once $componentPath . '/phinx_vendor.php';

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

$tmpRoot = sys_get_temp_dir() . '/ms3-autoload-upgrade-' . bin2hex(random_bytes(6));

/**
 * Write a vendor tree shaped exactly like Composer's generated files.
 */
$writeVendor = static function (string $vendor, string $initClass) use ($fail): void {
    if (!is_dir($vendor . '/composer') && !mkdir($vendor . '/composer', 0777, true)) {
        $fail('could not create ' . $vendor . '/composer');
    }

    file_put_contents(
        $vendor . '/autoload.php',
        "<?php\n\nrequire_once __DIR__ . '/composer/autoload_real.php';\n\n"
        . "return {$initClass}::getLoader();\n"
    );

    file_put_contents(
        $vendor . '/composer/autoload_real.php',
        "<?php\n\nclass {$initClass}\n{\n    private static \$loader;\n\n"
        . "    public static function getLoader()\n    {\n"
        . "        if (null !== self::\$loader) {\n            return self::\$loader;\n        }\n\n"
        . "        return self::\$loader = new \\Composer\\Autoload\\ClassLoader(\\dirname(__DIR__));\n"
        . "    }\n}\n"
    );
};

try {
    // The trap itself: a plain require over a replaced vendor, with no guard.
    $trap = $tmpRoot . '/trap/vendor';
    $writeVendor($trap, 'ComposerAutoloaderInitTrapOld');
    require_once $trap . '/autoload.php';
    $writeVendor($trap, 'ComposerAutoloaderInitTrapNew');

    $trapped = null;
    try {
        require $trap . '/autoload.php';
    } catch (\Error $e) {
        $trapped = $e->getMessage();
    }

    if ($trapped === null) {
        $fail('expected the unguarded require to hit the #779 Error; the scenario no longer reproduces');
    }
    if (!str_contains($trapped, 'ComposerAutoloaderInitTrapNew')) {
        $fail('unexpected Error from the unguarded require: ' . $trapped);
    }

    // Same sequence, guarded by ms3PhinxEnsureAutoloaderInit().
    $guarded = $tmpRoot . '/guarded/vendor';
    $writeVendor($guarded, 'ComposerAutoloaderInitGuardedOld');
    require_once $guarded . '/autoload.php';
    $writeVendor($guarded, 'ComposerAutoloaderInitGuardedNew');

    if (ms3PhinxAutoloaderInitClass($guarded . '/autoload.php') !== 'ComposerAutoloaderInitGuardedNew') {
        $fail('ms3PhinxAutoloaderInitClass must read the init class out of the new autoload.php');
    }

    ms3PhinxEnsureAutoloaderInit($guarded);

    if (!class_exists('ComposerAutoloaderInitGuardedNew', false)) {
        $fail('ms3PhinxEnsureAutoloaderInit must declare the replaced vendor init class');
    }

    $loader = require $guarded . '/autoload.php';
    if (!$loader instanceof \Composer\Autoload\ClassLoader) {
        $fail('guarded require must return the new ClassLoader');
    }

    // A vendor that was never loaded stays untouched: no stray require, no error.
    $fresh = $tmpRoot . '/fresh/vendor';
    $writeVendor($fresh, 'ComposerAutoloaderInitFresh');
    ms3PhinxEnsureAutoloaderInit($fresh);
    if (!class_exists('ComposerAutoloaderInitFresh', false)) {
        $fail('ms3PhinxEnsureAutoloaderInit must also work on a first load');
    }

    // Missing vendor must not warn or throw.
    ms3PhinxEnsureAutoloaderInit($tmpRoot . '/absent/vendor');
} finally {
    if (is_dir($tmpRoot)) {
        $removeTree($tmpRoot);
    }
}

// The permanent half of the fix: a stable class name across releases.
$composerJson = json_decode((string)file_get_contents($componentPath . '/composer.json'), true);
if (($composerJson['config']['autoloader-suffix'] ?? null) !== 'MiniShop3') {
    $fail('composer.json must pin config.autoloader-suffix to MiniShop3, otherwise the class name '
        . 'moves with the package set and every upgrade re-enters #779');
}

$realAutoload = (string)file_get_contents($componentPath . '/vendor/autoload.php');
if (!str_contains($realAutoload, 'ComposerAutoloaderInitMiniShop3::getLoader()')) {
    $fail('vendor/autoload.php is stale: run "composer dump-autoload" so the suffix takes effect');
}

fwrite(STDOUT, "OK: PhinxVendorAutoloadUpgradeTest\n");
