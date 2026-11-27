<?php

/**
 * #927 review: token_hash is the new bearer identity — backfill in one pass,
 * enforce uniqueness at DB level, keep schema/map in sync.
 *
 * Standalone (no MODX). Run: php tests/TokenHashMigrationContractTest.php
 */

declare(strict_types=1);

$component = dirname(__DIR__);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertTrue = static function (bool $condition, string $case) use ($fail): void {
    if (!$condition) {
        $fail($case);
    }
};

$migrationPath = $component . '/migrations/20261125130000_hash_customer_tokens_sha256.php';
$migration = (string) file_get_contents($migrationPath);

$assertTrue(
    str_contains($migration, 'SHA2(`token`, 256)'),
    'hash backfill is a single set-based UPDATE with SHA2(token, 256)'
);
$assertTrue(!str_contains($migration, 'SELECT `id`, `token`'), 'no row-by-row backfill SELECT remains');
$assertTrue(str_contains($migration, "'unique' => \$unique"), 'idx_token_hash uniqueness is decided at runtime');
$assertTrue(str_contains($migration, 'duplicate token_hash'), 'duplicate hashes keep the migration runnable');
$assertTrue(str_ends_with($migration, "\n"), 'migration file ends with a newline');

// #1069: the one-shot fallback used by the migration above must be catchable later.
$followupPath = $component . '/migrations/20261127120000_enforce_unique_customer_token_hash.php';
$followup = (string) file_get_contents($followupPath);

$assertTrue(
    str_contains($followup, 'HAVING COUNT(*) > 1'),
    'follow-up detects duplicate hashes set-wise'
);
$assertTrue(
    str_contains($followup, "COALESCE(MIN(CASE WHEN `token` <> '' THEN `id` END), MIN(`id`))"),
    'a row with a plaintext token is preferred as the keeper'
);
$assertTrue(
    str_contains($followup, 'SET `t`.`token_hash` = NULL'),
    'extra hashes are cleared instead of deleting rows'
);
$assertTrue(!str_contains($followup, 'DELETE FROM'), 'follow-up never deletes token rows');
$assertTrue(
    str_contains($followup, 'Duplicate token_hash values remain'),
    'follow-up refuses to add UNIQUE while duplicates remain'
);
$assertTrue(
    str_contains($followup, "'unique' => true"),
    'follow-up creates the unique index unconditionally'
);
$assertTrue(
    str_contains($followup, "hasIndexByName('idx_token_hash')"),
    'follow-up replaces an index left by the fallback'
);
$assertTrue(
    str_contains($followup, "'unique' => false"),
    'down() restores the non-unique index'
);
$assertTrue(str_ends_with($followup, "\n"), 'follow-up migration file ends with a newline');

$schema = (string) file_get_contents($component . '/schema/minishop3.mysql.schema.xml');
$assertTrue(
    str_contains($schema, '<index alias="idx_token_hash" name="idx_token_hash" primary="false" unique="true"'),
    'schema declares idx_token_hash as UNIQUE'
);

$mapPath = $component . '/src/Model/mysql/msCustomerToken.php';
$map = (string) file_get_contents($mapPath);
$assertTrue(
    preg_match("/'idx_token_hash'\s*=>\s*\[[\s\S]*?'unique'\s*=>\s*true/", $map) === 1,
    'generated xPDO map declares idx_token_hash as UNIQUE'
);

fwrite(STDOUT, "OK TokenHashMigrationContractTest\n");
exit(0);
