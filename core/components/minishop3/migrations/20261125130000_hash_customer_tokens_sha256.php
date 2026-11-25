<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Store customer tokens as SHA-256 hashes only (#927).
 *
 * Follow-up to 20261125120000_add_token_hash_to_customer_tokens, which added
 * the token_hash column but backfilled it with MD5(CONCAT('minishop3_token_', token))
 * while the PHP code looks up hash('sha256', $token). This migration:
 *
 * - normalizes token_hash to VARCHAR(64) and ensures idx_token_hash exists;
 * - recomputes token_hash = SHA-256(token) for every row that still has a
 *   plaintext token (repairs the MD5 backfill, keeps active tokens usable);
 * - drops the UNIQUE index on `token` (new rows store '' there, uniqueness
 *   would collide) and clears the plaintext column so dumps/backups no longer
 *   contain usable credentials.
 *
 * Active tokens keep working: their hashes were backfilled from the plaintext
 * before it was cleared. Rows written by old code during a rolling deploy are
 * still accepted by the plaintext fallback in TokenService/AuthManager until
 * their TTL expires.
 */
final class HashCustomerTokensSha256 extends AbstractMigration
{
    public function up(): void
    {
        $prefix = (string) $this->getAdapter()->getOption('table_prefix');
        $table = $prefix . 'ms3_customer_tokens';

        if (!$this->hasTable('ms3_customer_tokens')) {
            return;
        }

        $phinxTable = $this->table('ms3_customer_tokens');

        if (!$phinxTable->hasColumn('token_hash')) {
            $phinxTable->addColumn('token_hash', 'string', [
                'limit' => 64,
                'null' => true,
                'default' => null,
                'after' => 'token',
            ])->save();
        } else {
            // 20261125120000 created CHAR(64); normalize to VARCHAR(64) as in the schema.
            $columns = $this->fetchAll("SHOW COLUMNS FROM `{$table}` LIKE 'token_hash'");
            $type = strtolower((string) ($columns[0]['Type'] ?? ''));
            if (str_starts_with($type, 'char')) {
                $this->execute(
                    "ALTER TABLE `{$table}` MODIFY COLUMN `token_hash` VARCHAR(64) NULL DEFAULT NULL"
                );
            }
        }

        if (!$phinxTable->hasIndexByName('idx_token_hash')) {
            $phinxTable->addIndex(['token_hash'], [
                'name' => 'idx_token_hash',
                'unique' => false,
            ])->save();
        }

        // New tokens store '' in `token`; the legacy UNIQUE index would collide.
        if ($phinxTable->hasIndexByName('idx_token_unique')) {
            $phinxTable->removeIndexByName('idx_token_unique')->save();
        }

        // Backfill/repair hashes from plaintext. fetchAll/execute do not
        // substitute the table prefix, unlike table().
        $rows = $this->fetchAll(
            "SELECT `id`, `token` FROM `{$table}` WHERE `token` != ''"
        );
        foreach ($rows as $row) {
            $hash = hash('sha256', (string) $row['token']);
            $id = (int) $row['id'];
            $this->execute(
                "UPDATE `{$table}` SET `token_hash` = '{$hash}' WHERE `id` = {$id}"
            );
        }

        // Hashes are backfilled — active tokens resolve via token_hash now.
        $this->execute(
            "UPDATE `{$table}` SET `token` = '' WHERE `token` != ''"
        );
    }

    public function down(): void
    {
        if (!$this->hasTable('ms3_customer_tokens')) {
            return;
        }

        $phinxTable = $this->table('ms3_customer_tokens');

        // Cleared plaintext cannot be restored; restoring the UNIQUE index is
        // only possible while no two rows share the empty token value.
        if (!$phinxTable->hasIndexByName('idx_token_unique')) {
            try {
                $phinxTable->addIndex(['token'], [
                    'name' => 'idx_token_unique',
                    'unique' => true,
                ])->save();
            } catch (\Exception $e) {
                $this->output->writeln(
                    '<comment>HashCustomerTokensSha256::down: cannot restore idx_token_unique (duplicate empty tokens).</comment>'
                );
            }
        }

        $this->output->writeln(
            '<comment>HashCustomerTokensSha256::down: plaintext tokens cleared in up() cannot be restored.</comment>'
        );
    }
}