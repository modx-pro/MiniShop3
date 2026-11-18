<?php

use Phinx\Migration\AbstractMigration;

/**
 * Remove unique constraint on msCustomer.last_name.
 * 
 * Issue #905: The last_name index was incorrectly marked as unique in the schema,
 * preventing multiple customers from having the same last name.
 * 
 * This migration drops the unique index and recreates it as a regular (non-unique) index.
 */
class RemoveUniqueLastNameFromCustomer extends AbstractMigration
{
    public function up(): void
    {
        // Drop the unique index
        $this->execute('ALTER TABLE `ms3_customers` DROP INDEX `last_name`;');

        // Re-create a non-unique index
        $this->execute('CREATE INDEX `last_name` ON `ms3_customers` (`last_name`);');
    }

    public function down(): void
    {
        // Re-create the original unique index (for rollback)
        $this->execute('ALTER TABLE `ms3_customers` DROP INDEX `last_name`;');
        $this->execute('CREATE UNIQUE INDEX `last_name` ON `ms3_customers` (`last_name`);');
    }
}
