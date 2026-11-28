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

        // Fresh installs from the corrected schema already have a non-unique
        // index: avoid needless DDL on a large customers table.
        if ($table->hasIndexByName('last_name')) {
            $index = null;
            foreach ($this->fetchAll("SHOW INDEX FROM `{$this->getAdapter()->getOption('table_prefix')}ms3_customers` WHERE `Key_name` = 'last_name'") as $row) {
                $index = $row;
                break;
            }
            if (is_array($index) && (int) ($index['Non_unique'] ?? 1) === 1) {
                return;
            }
            $table->removeIndexByName('last_name')->update();
        }

        $table->addIndex(['last_name'], [
            'name' => 'last_name',
            'unique' => false,
        ])->update();
    }

    public function down(): void
    {
        // Intentional no-op: re-adding UNIQUE(last_name) would fail as soon as
        // two customers share a surname — exactly what issue #905 allows.
        // Dropping the index here would leave the column unindexed, so leave
        // the non-unique index in place.
        $this->output->writeln(
            '<comment>RemoveUniqueLastNameFromCustomer::down: unique constraint is not restored (#905).</comment>'
        );
    }
}
