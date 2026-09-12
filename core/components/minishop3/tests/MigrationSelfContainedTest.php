<?php

/**
 * Migrations must not import MiniShop3 application code (transport install runs Phinx
 * before guaranteed autoload of the new component classes).
 *
 * Run: php tests/MigrationSelfContainedTest.php
 */

declare(strict_types=1);

$migrationsDir = dirname(__DIR__) . '/migrations';
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL MigrationSelfContainedTest: {$message}\n");
    exit(1);
};

$files = glob($migrationsDir . '/[0-9]*.php') ?: [];
if ($files === []) {
    $fail('no migration files found');
}

foreach ($files as $file) {
    $source = (string) file_get_contents($file);
    $basename = basename($file);

    if (preg_match('/^use MiniShop3\\\\/m', $source)) {
        $fail("{$basename} imports MiniShop3 namespace — keep migrations self-contained");
    }
}

// Helpers colocated under migrations/ (e.g. _modx.php) must also stay free of use MiniShop3\.
foreach (glob($migrationsDir . '/_*.php') ?: [] as $helper) {
    $source = (string) file_get_contents($helper);
    $basename = basename($helper);
    if (preg_match('/^use MiniShop3\\\\/m', $source)) {
        $fail("{$basename} imports MiniShop3 namespace — keep migration helpers self-contained");
    }
}

fwrite(STDOUT, "OK MigrationSelfContainedTest (" . count($files) . " migrations)\n");
exit(0);
