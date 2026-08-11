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

$files = glob($migrationsDir . '/*.php') ?: [];
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

fwrite(STDOUT, "OK MigrationSelfContainedTest (" . count($files) . " migrations)\n");
exit(0);
