<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Hash API bearer tokens kept in ms3_orders.token and ms3_customers.token (#1058).
 *
 * Both columns historically stored the same plaintext token that is accepted
 * by the public API, so a database dump contained working credentials. Only
 * only bare 64-hex API tokens are converted: integration order tokens are
 * md5-based, and values already written by StoredTokenHash carry a `sha256$`
 * prefix, so a re-run or a code-first rolling deploy cannot double-hash.
 *
 * PHP resolves both columns through StoredTokenHash with a plaintext fallback,
 * so legacy rows keep working before the migration runs.
 */
final class HashOrderCustomerTokens extends AbstractMigration
{
    public function up(): void
    {
        $prefix = (string) $this->getAdapter()->getOption('table_prefix');

        foreach (['ms3_orders', 'ms3_customers'] as $table) {
            if (!$this->hasTable($table)) {
                continue;
            }

            $fullName = $prefix . $table;
            $this->execute(
                "UPDATE `{$fullName}` SET `token` = SHA2(`token`, 256)"
                . " WHERE `token` REGEXP '^[0-9a-f]{64}$'"
            );
        }
    }

    public function down(): void
    {
        $this->output->writeln(
            '<comment>HashOrderCustomerTokens::down: plaintext tokens cannot be restored from hashes.</comment>'
        );
    }
}
