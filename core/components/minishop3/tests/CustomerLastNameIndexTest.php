<?php

/**
 * #905: last_name must be a non-unique index in schema and generated map,
 * and the migration must not restore uniqueness on rollback.
 *
 * Run: php tests/CustomerLastNameIndexTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$schema = (string) file_get_contents($root . '/core/components/minishop3/schema/minishop3.mysql.schema.xml');
if (!str_contains($schema, '<index alias="last_name" name="last_name" primary="false" unique="false"')) {
    $fail('Schema must declare last_name as non-unique (#905)');
}

$map = (string) file_get_contents($root . '/core/components/minishop3/src/Model/mysql/msCustomer.php');
if (!preg_match("/'last_name'\s*=>\s*\[[\s\S]{0,120}?'unique'\s*=>\s*false/", $map)) {
    $fail('Generated msCustomer map must declare last_name non-unique (#905)');
}

$migrations = glob($root . '/core/components/minishop3/migrations/*remove_unique_last_name_from_customer.php') ?: [];
if (count($migrations) !== 1) {
    $fail('Expected exactly one last_name migration (#905)');
}
$migration = (string) file_get_contents($migrations[0]);
if (str_contains($migration, "'unique' => true")) {
    $fail('Migration rollback must not restore UNIQUE(last_name) (#905)');
}
if (!str_contains($migration, 'Non_unique')) {
    $fail('Migration must avoid needless DDL when index is already non-unique (#905)');
}

fwrite(STDOUT, "OK CustomerLastNameIndexTest (#905)\n");
exit(0);
