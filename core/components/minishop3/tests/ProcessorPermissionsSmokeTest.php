<?php

/**
 * Smoke: gallery processors declare $permission (#660).
 *
 * Empty $permission makes ModelProcessor::checkPermissions() return true for any
 * authenticated manager. Sort/Multiple must require msproductfile_save.
 *
 * Denial uses the same formula as MODX ModelProcessor::checkPermissions() with
 * ModxStub::hasPermission — property values come from the Gallery sources under test.
 *
 * Run: php tests/ProcessorPermissionsSmokeTest.php
 */

declare(strict_types=1);

require __DIR__ . '/stubs/ModxStub.php';

use MODX\Revolution\modX;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $label) use ($fail): void {
    if ($actual !== $expected) {
        $fail(sprintf(
            "%s:\nexpected: %s\nactual:   %s",
            $label,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
};

$assertTrue = static function (bool $actual, string $label) use ($assertSame): void {
    $assertSame(true, $actual, $label);
};

$assertFalse = static function (bool $actual, string $label) use ($assertSame): void {
    $assertSame(false, $actual, $label);
};

$galleryRoot = dirname(__DIR__) . '/src/Processors/Gallery';
if (!is_dir($galleryRoot)) {
    $fail("Gallery processors directory not found: {$galleryRoot}");
}

$permissionPattern = '/public\s+\$permission\s*=\s*[\'"]([^\'"]*)[\'"]/';
$declared = [];

foreach (glob($galleryRoot . '/*.php') ?: [] as $absolute) {
    $relative = 'Gallery/' . basename($absolute);
    $source = file_get_contents($absolute);
    if ($source === false) {
        $fail("Cannot read {$relative}");
    }
    if (!preg_match('/\bclass\s+\w+/', $source)) {
        continue;
    }
    if (!preg_match($permissionPattern, $source, $matches) || $matches[1] === '') {
        $fail("{$relative}: missing non-empty public \$permission");
    }
    $declared[basename($absolute)] = $matches[1];
}

$assertSame('msproductfile_save', $declared['Sort.php'] ?? null, 'Gallery/Sort.php $permission');
$assertSame('msproductfile_save', $declared['Multiple.php'] ?? null, 'Gallery/Multiple.php $permission');

/**
 * Mirrors MODX\Revolution\Processors\ModelProcessor::checkPermissions()
 * (see .phpstan-deps/.../ModelProcessor.php). Wired to ModxStub so AC #2 is
 * exercised with the permission strings declared on Sort/Multiple.
 */
$checkPermissions = static function (object $modx, string $permission): bool {
    return $permission !== '' ? $modx->hasPermission($permission) : true;
};

$modxDenied = new modX();
$modxDenied->setPermissions([]);
$assertFalse(
    $checkPermissions($modxDenied, $declared['Sort.php']),
    'Sort: manager without msproductfile_save is denied'
);
$assertFalse(
    $checkPermissions($modxDenied, $declared['Multiple.php']),
    'Multiple: manager without msproductfile_save is denied'
);

$modxAllowed = new modX();
$modxAllowed->setPermissions(['msproductfile_save']);
$assertTrue(
    $checkPermissions($modxAllowed, $declared['Sort.php']),
    'Sort: manager with msproductfile_save is allowed'
);
$assertTrue(
    $checkPermissions($modxAllowed, $declared['Multiple.php']),
    'Multiple: manager with msproductfile_save is allowed'
);

$lexiconKeys = ['ms3_gallery_err_ns', 'ms3_gallery_err_no_product'];
foreach (['en', 'ru'] as $lang) {
    $lexiconPath = dirname(__DIR__) . "/lexicon/{$lang}/default.inc.php";
    $lexicon = file_get_contents($lexiconPath);
    if ($lexicon === false) {
        $fail("Cannot read lexicon {$lang}/default.inc.php");
    }
    foreach ($lexiconKeys as $key) {
        if (!str_contains($lexicon, "\$_lang['{$key}']")) {
            $fail("Missing lexicon key {$key} in {$lang}/default.inc.php");
        }
    }
}

fwrite(STDOUT, 'OK ProcessorPermissionsSmokeTest (' . count($declared) . " Gallery processors)\n");
exit(0);
