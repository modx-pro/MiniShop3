<?php

/**
 * Static check for MODX table prefix in Phinx migration metadata table.
 *
 * Run: php tests/PhinxMigrationTablePrefixTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$loadPhinxConfig = static function (string $tablePrefix): array {
    $modx = new class ($tablePrefix) {
        public function __construct(private readonly string $tablePrefix)
        {
        }

        public function getOption(string $key, mixed $options = null, mixed $default = null): mixed
        {
            return match ($key) {
                'table_prefix' => $this->tablePrefix,
                'host' => 'localhost',
                'dbname' => 'modx',
                'username' => 'user',
                'password' => 'password',
                'port' => '3306',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                default => $default,
            };
        }
    };

    return require __DIR__ . '/../phinx.php';
};

$prefixedConfig = $loadPhinxConfig('ap53_');
$prefixedTable = $prefixedConfig['environments']['default_migration_table'] ?? null;
if ($prefixedTable !== 'ap53_ms3_migrations') {
    $fail("prefixed table: expected ap53_ms3_migrations, got {$prefixedTable}");
}

$plainConfig = $loadPhinxConfig('');
$plainTable = $plainConfig['environments']['default_migration_table'] ?? null;
if ($plainTable !== 'ms3_migrations') {
    $fail("plain table: expected ms3_migrations, got {$plainTable}");
}

fwrite(STDOUT, "OK PhinxMigrationTablePrefixTest\n");
exit(0);
