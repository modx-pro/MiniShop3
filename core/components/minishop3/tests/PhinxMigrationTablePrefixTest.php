<?php

/**
 * Static checks for MODX table prefix in Phinx migration metadata table.
 *
 * Run: php tests/PhinxMigrationTablePrefixTest.php
 */

declare(strict_types=1);

require __DIR__ . '/support/modx_phinx_stub.php';

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$createMockPdo = static function (array $existingTables): object {
    return new class ($existingTables) {
        /** @var list<string> */
        public array $execCalls = [];

        public function __construct(private array $existingTables)
        {
        }

        public function query(string $sql): object
        {
            return new class ($sql, $this->existingTables) {
                public function __construct(
                    private string $sql,
                    private array $existingTables,
                ) {
                }

                public function fetch(): array|false
                {
                    if (!preg_match("/SHOW TABLES LIKE '([^']+)'/", $this->sql, $matches)) {
                        return false;
                    }

                    return in_array($matches[1], $this->existingTables, true) ? [1] : false;
                }
            };
        }

        public function exec(string $sql): int
        {
            $this->execCalls[] = $sql;

            return 0;
        }
    };
};

$loadPhinxConfig = static function (
    string $tablePrefix,
    ?object $pdo = null,
): array {
    $modx = ms3_create_modx_phinx_stub([
        'table_prefix' => $tablePrefix,
        'host' => 'localhost',
        'dbname' => 'modx',
        'username' => 'user',
        'password' => 'password',
        'port' => '3306',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ], $pdo);

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

$legacyRenamePdo = $createMockPdo(['ms3_migrations']);
$loadPhinxConfig('modx_', $legacyRenamePdo);

if (count($legacyRenamePdo->execCalls) !== 1) {
    $fail('legacy rename: expected one RENAME TABLE call');
}

$expectedRename = 'RENAME TABLE `ms3_migrations` TO `modx_ms3_migrations`';
if ($legacyRenamePdo->execCalls[0] !== $expectedRename) {
    $fail("legacy rename: expected {$expectedRename}, got {$legacyRenamePdo->execCalls[0]}");
}

$bothExistPdo = $createMockPdo(['ms3_migrations', 'modx_ms3_migrations']);
$loadPhinxConfig('modx_', $bothExistPdo);

if ($bothExistPdo->execCalls !== []) {
    $fail('both tables exist: expected no RENAME TABLE call');
}

$freshInstallPdo = $createMockPdo([]);
$loadPhinxConfig('modx_', $freshInstallPdo);

if ($freshInstallPdo->execCalls !== []) {
    $fail('fresh install: expected no RENAME TABLE call');
}

$noPrefixPdo = $createMockPdo(['ms3_migrations']);
$loadPhinxConfig('', $noPrefixPdo);

if ($noPrefixPdo->execCalls !== []) {
    $fail('empty prefix: expected no RENAME TABLE call');
}

fwrite(STDOUT, "OK PhinxMigrationTablePrefixTest\n");
exit(0);
