<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Enforce the UNIQUE idx_token_hash promised by the schema (#1069).
 *
 * 20261125130000_hash_customer_tokens_sha256 downgrades idx_token_hash to a
 * plain index when duplicates are present, and that one-shot fallback is never
 * retried. This migration clears duplicate hashes (keeping one row per hash)
 * and then always creates the unique index, so affected installations converge
 * to the state declared in the schema.
 *
 * The duplicates are leftovers from the earlier MD5 backfill of rows with an
 * empty plaintext token; those tokens cannot be resolved anymore. If a row
 * with a plaintext token still exists in a group, that row is kept.
 */
final class EnforceUniqueCustomerTokenHash extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('ms3_customer_tokens')) {
            return;
        }

        $table = $this->table('ms3_customer_tokens');
        if (!$table->hasColumn('token_hash')) {
            return;
        }

        $quoted = $this->quoteIdentifier($this->tableName());

        // MySQL UNIQUE allows many NULLs. Clear the hash of every extra row
        // sharing a hash, keeping one resolvable row per hash.
        $cleared = (int) $this->execute(
            "UPDATE {$quoted} AS `t`"
            . " INNER JOIN ("
            . "   SELECT `token_hash`,"
            . "     COALESCE(MIN(CASE WHEN `token` <> '' THEN `id` END), MIN(`id`)) AS `keep_id`"
            . "   FROM {$quoted}"
            . "   WHERE `token_hash` IS NOT NULL"
            . "   GROUP BY `token_hash`"
            . "   HAVING COUNT(*) > 1"
            . " ) AS `d`"
            . " ON `d`.`token_hash` = `t`.`token_hash` AND `t`.`id` <> `d`.`keep_id`"
            . " SET `t`.`token_hash` = NULL"
        );
        if ($cleared > 0) {
            $this->output->writeln(
                "<comment>EnforceUniqueCustomerTokenHash: cleared {$cleared} duplicate token_hash values.</comment>"
            );
        }

        // Fail loudly instead of silently leaving the schema promise unfulfilled.
        $remaining = $this->fetchRow(
            "SELECT COUNT(*) AS cnt FROM ("
            . " SELECT 1 FROM {$quoted} WHERE `token_hash` IS NOT NULL"
            . " GROUP BY `token_hash` HAVING COUNT(*) > 1"
            . ") AS duplicates"
        );
        if ((int) ($remaining['cnt'] ?? 0) > 0) {
            throw new \RuntimeException(
                'Duplicate token_hash values remain; refusing to create UNIQUE idx_token_hash'
            );
        }

        if ($table->hasIndexByName('idx_token_hash')) {
            $table->removeIndexByName('idx_token_hash')->save();
        }
        $table->addIndex(['token_hash'], [
            'name' => 'idx_token_hash',
            'unique' => true,
        ])->save();
    }

    public function down(): void
    {
        if (!$this->hasTable('ms3_customer_tokens')) {
            return;
        }

        $table = $this->table('ms3_customer_tokens');
        if (!$table->hasIndexByName('idx_token_hash')) {
            return;
        }

        $table->removeIndexByName('idx_token_hash')->save();
        $table->addIndex(['token_hash'], [
            'name' => 'idx_token_hash',
            'unique' => false,
        ])->save();

        $this->output->writeln(
            '<comment>EnforceUniqueCustomerTokenHash::down: hashes cleared in up() cannot be restored.</comment>'
        );
    }

    private function tableName(): string
    {
        $prefix = (string) ($this->getAdapter()->getOption('table_prefix') ?? '');

        return $prefix . 'ms3_customer_tokens';
    }

    private function quoteIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
