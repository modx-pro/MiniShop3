<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds select_options column to ms3_extra_fields table
 * for storing dropdown options configuration
 */
final class AddSelectOptionsToExtraFields extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('ms3_extra_fields');

        if (!$table->hasColumn('select_options')) {
            $table->addColumn('select_options', 'text', [
                'null' => true,
                'after' => 'active',
                'comment' => 'Options for ms3-combo-select field type (one per line: value==label)',
            ]);
            $table->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('ms3_extra_fields');

        if ($table->hasColumn('select_options')) {
            $table->removeColumn('select_options');
            $table->update();
        }
    }
}
