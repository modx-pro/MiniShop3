<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Remove unique constraint on msCustomer.last_name.
 *
 * Issue #905: The last_name index was incorrectly marked as unique in the schema,
 * preventing multiple customers from having the same last name.
 *
 * This migration drops the unique index and recreates it as a regular (non-unique) index.
 */
final class RemoveUniqueLastNameFromCustomer extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('ms3_customers');

        // Drop the unique index if it exists
        if ($table->hasIndexByName('last_name')) {
            $table->removeIndexByName('last_name')->update();
        }

        // Re-create a non-unique index
        $table->addIndex(['last_name'], [
            'name' => 'last_name',
            'unique' => false,
        ])->update();
    }

    public function down(): void
    {
        $table = $this->table('ms3_customers');

        // Drop the non-unique index
        if ($table->hasIndexByName('last_name')) {
            $table->removeIndexByName('last_name')->update();
        }

        // Re-create the original unique index (for rollback)
        $table->addIndex(['last_name'], [
            'name' => 'last_name',
            'unique' => true,
        ])->update();
    }
}
