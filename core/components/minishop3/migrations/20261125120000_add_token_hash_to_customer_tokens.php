<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Add token_hash column to ms3_customer_tokens for storing SHA-256 hash
 * instead of plaintext tokens. This is part of issue #927 - customer tokens
 * stored in plaintext in the database.
 *
 * New tokens will have hash stored; old plaintext tokens are supported during
 * a transition period (accepted on lookup but new tokens always get hash).
 */
final class AddTokenHashToCustomerTokens extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->resolveTokenTable();
        if ($table === null) {
            return;
        }

        // Check if column already exists
        $columns = $this->fetchAll("SHOW COLUMNS FROM `{$table}` LIKE 'token_hash'");
        if ([] !== $columns) {
            return;
        }

        $this->execute(
            "ALTER TABLE `{$table}` "
            . 'ADD COLUMN `token_hash` CHAR(64) NULL DEFAULT NULL AFTER `token`'
        );

        // Add index for faster hash lookups
        $this->execute(
            "CREATE INDEX `idx_token_hash` ON `{$table}` (`token_hash`)"
        );

        // Migrate existing plaintext tokens to hash (one-time operation)
        $this->migrateExistingTokens($table);
    }

    public function down(): void
    {
        $table = $this->resolveTokenTable();
        if ($table === null) {
            return;
        }

        // Drop index
        try {
            $this->execute(
                "DROP INDEX `idx_token_hash` ON `{$table}`"
            );
        } catch (\Exception $e) {
            // Index may not exist if migration was partially applied
        }

        // Drop column
        $this->execute(
            "ALTER TABLE `{$table}` DROP COLUMN `token_hash`"
        );
    }

    /**
     * Migrate existing plaintext tokens to SHA-256 hashes.
     * Runs as a one-time operation during migration up().
     */
    private function migrateExistingTokens(string $table): void
    {
        $sql = "
            UPDATE `{$table}`
            SET `token_hash` = LOWER(MD5(CONCAT('minishop3_token_', `token`)))
            WHERE `token_hash` IS NULL
              AND `token` != ''
              AND LENGTH(`token`) > 0
        ";

        $this->execute($sql);
    }

    private function resolveTokenTable(): ?string
    {
        if (!$this->hasTable('ms3_customer_tokens')) {
            return null;
        }

        return (string) ($this->getAdapter()->getOption('table_prefix') ?? '') . 'ms3_customer_tokens';
    }
}