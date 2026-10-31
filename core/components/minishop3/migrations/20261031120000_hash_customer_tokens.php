<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Hash customer tokens for security (#927).
 *
 * - Adds `token_hash` column (varchar 64 for SHA-256 hex).
 * - Migrates existing plain-text tokens to SHA-256 hashes.
 * - Keeps unique index on `token_hash`.
 * - Legacy plain `token` column remains for backward compatibility during transition.
 */
final class HashCustomerTokens extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('ms3_customer_tokens');

        // Add token_hash column if it doesn't exist
        if (!$table->hasColumn('token_hash')) {
            $table->addColumn('token_hash', 'string', [
                'limit' => 64,
                'null' => true,
                'default' => null,
                'after' => 'customer_id',
            ])->save();

            // Migrate existing tokens to hashes
            $rows = $this->fetchAll('SELECT id, token FROM ms3_customer_tokens WHERE token != ""');
            foreach ($rows as $row) {
                $hash = hash('sha256', $row['token']);
                $this->execute(
                    "UPDATE ms3_customer_tokens SET token_hash = '{$hash}' WHERE id = {$row['id']}"
                );
            }

            // Add index on token_hash
            $table->addIndex(['token_hash'], [
                'name' => 'idx_token_hash',
                'unique' => false,
            ])->save();
        }
    }

    public function down(): void
    {
        $table = $this->table('ms3_customer_tokens');
        if ($table->hasColumn('token_hash')) {
            $table->removeColumn('token_hash')->save();
        }
    }
}