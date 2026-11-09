<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Hash customer tokens for security (#927) — step 1 of transition.
 *
 * - Adds `token_hash` (varchar 64, SHA-256 hex).
 * - Backfills hashes from existing plain tokens, then clears the plain
 *   column so existing tokens are no longer exposed in dumps.
 * - Drops the unique index on `token` (all rows become '') and adds a
 *   non-unique index on `token_hash`.
 *
 * New tokens still write the plain column for backward compatibility with
 * callers that read `$tokenObj->get('token')` to return the token to the
 * client. Step 2 (stop writing the plain column, expire legacy rows,
 * drop the column) is tracked as a follow-up; #927 stays open until then.
 */
final class HashCustomerTokens extends AbstractMigration
{
    public function up(): void
    {
        $prefix = (string) $this->adapter->getOption('table_prefix');
        $table = $this->table('ms3_customer_tokens');

        if ($table->hasColumn('token_hash')) {
            return;
        }

        $table->addColumn('token_hash', 'string', [
            'limit' => 64,
            'null' => true,
            'default' => null,
            'after' => 'customer_id',
        ])->save();

        // Backfill hashes from plain tokens (prefix-aware: fetchAll/execute
        // do not substitute the table prefix, unlike table()).
        $rows = $this->fetchAll(
            "SELECT id, token FROM {$prefix}ms3_customer_tokens WHERE token != '' AND token_hash IS NULL"
        );
        foreach ($rows as $row) {
            $hash = hash('sha256', (string) $row['token']);
            $id = (int) $row['id'];
            $this->execute(
                "UPDATE {$prefix}ms3_customer_tokens SET token_hash = '{$hash}' WHERE id = {$id}"
            );
        }

        // Plain tokens will become '' for every row — the unique index on
        // `token` would collide. Drop it before clearing.
        if ($table->hasIndexByName('idx_token_unique')) {
            $table->removeIndexByName('idx_token_unique')->save();
        }

        $this->execute(
            "UPDATE {$prefix}ms3_customer_tokens SET token = '' WHERE token != ''"
        );

        $table->addIndex(['token_hash'], [
            'name' => 'idx_token_hash',
            'unique' => false,
        ])->save();
    }

    public function down(): void
    {
        $prefix = (string) $this->adapter->getOption('table_prefix');
        $table = $this->table('ms3_customer_tokens');

        if ($table->hasIndexByName('idx_token_hash')) {
            $table->removeIndexByName('idx_token_hash')->save();
        }
        if ($table->hasColumn('token_hash')) {
            $table->removeColumn('token_hash')->save();
        }
        // Plain tokens cleared by up() cannot be restored.
        $this->output->writeln(
            "HashCustomerTokens::down: {$prefix}ms3_customer_tokens.token values were cleared in up() and cannot be restored."
        );
    }
}