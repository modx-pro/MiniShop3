<?php

/**
 * Static checks for Phinx MySQL charset configuration (without MODX).
 *
 * Run: php tests/PhinxCharsetConfigTest.php
 */

declare(strict_types=1);

require __DIR__ . '/support/modx_phinx_stub.php';

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$buildConfig = static function (array $options): array {
    $modx = ms3_create_modx_phinx_stub($options);

    return require __DIR__ . '/../phinx.php';
};

$config = $buildConfig([
    'dbname' => 'minishop3',
    'username' => 'user',
    'password' => 'secret',
    'charset' => 'UTF-8',
    'table_prefix' => 'modx_',
]);

$charset = $config['environments']['production']['charset'] ?? null;
if ($charset !== 'utf8mb4') {
    $fail("MODX charset UTF-8 must be normalized to MySQL charset utf8mb4, got {$charset}");
}

$collation = $config['environments']['production']['collation'] ?? null;
if ($collation !== 'utf8mb4_unicode_ci') {
    $fail("utf8mb4 charset must use utf8mb4_unicode_ci by default, got {$collation}");
}

$config = $buildConfig([
    'dbname' => 'minishop3',
    'username' => 'user',
    'password' => 'secret',
    'database_dsn' => 'mysql:host=localhost;dbname=minishop3;charset=utf8',
    'charset' => 'UTF-8',
    'table_prefix' => 'modx_',
]);

$charset = $config['environments']['production']['charset'] ?? null;
if ($charset !== 'utf8') {
    $fail("database_dsn charset must override MODX web charset, got {$charset}");
}

$config = $buildConfig([
    'dbname' => 'minishop3',
    'username' => 'user',
    'password' => 'secret',
    'database_charset' => '',
    'database_collation' => 'utf8mb4_general_ci',
    'charset' => 'UTF-8',
    'table_prefix' => 'modx_',
]);

$charset = $config['environments']['production']['charset'] ?? null;
if ($charset !== 'utf8mb4') {
    $fail("empty database_charset must fall back to normalized MODX charset, got {$charset}");
}

$collation = $config['environments']['production']['collation'] ?? null;
if ($collation !== 'utf8mb4_general_ci') {
    $fail("database_collation must override default collation, got {$collation}");
}

fwrite(STDOUT, "OK PhinxCharsetConfigTest\n");
exit(0);
