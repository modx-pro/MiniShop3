<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Allow msProductField.section to be NULL so a page section can be deleted
 * without leaving fields pointing at a removed row (#827).
 */
final class MakeProductFieldSectionNullable extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('ms3_product_fields')) {
            return;
        }

        $prefix = (string) $this->getAdapter()->getOption('table_prefix');
        $this->execute(
            "ALTER TABLE `{$prefix}ms3_product_fields` MODIFY COLUMN `section` INT(10) UNSIGNED NULL DEFAULT NULL"
        );
    }

    public function down(): void
    {
        if (!$this->hasTable('ms3_product_fields')) {
            return;
        }

        // Fields detached by #827 now hold NULL; keep them as NULL instead of
        // coercing to 0 (which would violate the section FK).
        $this->output->writeln(
            '<comment>MakeProductFieldSectionNullable::down: column remains nullable (#827).</comment>'
        );
    }
}
