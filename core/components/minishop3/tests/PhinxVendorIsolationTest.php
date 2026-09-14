<?php

/**
 * Smoke: MiniShop3 Phinx vendor bootstrap prefers this component's vendor (#719).
 *
 * Run: php tests/PhinxVendorIsolationTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$componentPath = dirname(__DIR__);
require_once $componentPath . '/phinx_vendor.php';

$resolver = dirname(__DIR__, 4) . '/_build/resolvers/resolver_02_migrations.php';
$resolverSrc = file_get_contents($resolver);
if ($resolverSrc === false) {
    $fail('resolver_02_migrations.php not readable');
}

if (!str_contains($resolverSrc, 'ms3PhinxBootstrapVendor')) {
    $fail('resolver_02_migrations.php must call ms3PhinxBootstrapVendor');
}

if (preg_match("/if\\s*\\(\\s*!class_exists\\(\\s*'Phinx\\\\\\\\Config\\\\\\\\Config'\\s*\\)\\s*\\)/", $resolverSrc)) {
    $fail('resolver must not skip MS3 autoload via class_exists(Phinx\\Config\\Config)');
}

$phinxPhp = file_get_contents($componentPath . '/phinx.php');
if ($phinxPhp === false) {
    $fail('phinx.php not readable');
}

if (!str_contains($phinxPhp, 'ms3PhinxBootstrapVendor')) {
    $fail('phinx.php must call ms3PhinxBootstrapVendor after MODX boot');
}

$vendorHelper = file_get_contents($componentPath . '/phinx_vendor.php');
if ($vendorHelper === false) {
    $fail('phinx_vendor.php not readable');
}

$configCheckPos = strpos($vendorHelper, 'class_exists(\\Phinx\\Config\\Config::class, false)');
$resolvePos = strpos($vendorHelper, 'ms3PhinxResolveClassLoader($componentPath)');
if ($configCheckPos === false || $resolvePos === false || $configCheckPos > $resolvePos) {
    $fail('foreign Config check must run before ms3PhinxResolveClassLoader (Composer prepend)');
}

$ciPhp = file_get_contents($componentPath . '/scripts/ci-php.sh');
if ($ciPhp === false || !str_contains($ciPhp, 'phinx_vendor.php')) {
    $fail('ci-php.sh must php -l phinx_vendor.php');
}

$autoload = $componentPath . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDOUT, "OK: PhinxVendorIsolationTest (skipped live prepend; vendor missing)\n");
    exit(0);
}

$logs = [];
$ok = ms3PhinxBootstrapVendor(
    $componentPath,
    static function (string $message) use (&$logs): void {
        $logs[] = $message;
    }
);

if ($logs !== []) {
    $fail('unexpected Phinx ownership log: ' . implode(' | ', $logs));
}

if ($ok !== true) {
    $fail('ms3PhinxBootstrapVendor should succeed for MiniShop3 vendor');
}

$expected = ms3PhinxExpectedPhinxRoot($componentPath);
$environment = ms3PhinxFirstAutoloadFile('Phinx\\Migration\\Manager\\Environment');
if (!is_string($environment) || !ms3PhinxPathIsUnder($environment, $expected)) {
    $fail('Environment must resolve under MiniShop3 vendor, got: ' . ($environment ?? 'null'));
}

fwrite(STDOUT, "OK: PhinxVendorIsolationTest\n");
