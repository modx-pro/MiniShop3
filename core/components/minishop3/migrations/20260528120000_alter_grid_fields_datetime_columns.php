<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Align ms3_grid_fields.created_at/updated_at with msGridField model on existing installs:
 * DATETIME + ON UPDATE CURRENT_TIMESTAMP for updated_at (MySQL 5.6.5+).
 */
final class AlterGridFieldsDatetimeColumns extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('ms3_grid_fields')) {
            return;
        }

        $table = $this->prefixedTableName();

        $this->execute(
            "ALTER TABLE `{$table}` "
            . 'MODIFY COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, '
            . 'MODIFY COLUMN `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'
        );
    }

    public function down(): void
    {
        if (!$this->hasTable('ms3_grid_fields')) {
            return;
        }

        $table = $this->prefixedTableName();

        $this->execute(
            "ALTER TABLE `{$table}` "
            . 'MODIFY COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, '
            . 'MODIFY COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'
        );
    }

    private function prefixedTableName(): string
    {
        return (string) ($this->getAdapter()->getOption('table_prefix') ?? '') . 'ms3_grid_fields';
    }
}
