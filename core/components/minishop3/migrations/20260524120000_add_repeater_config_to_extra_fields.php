<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds repeater_config column to ms3_extra_fields for ms3-repeater field schema
 */
final class AddRepeaterConfigToExtraFields extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('ms3_extra_fields');

        if (!$table->hasColumn('repeater_config')) {
            $table->addColumn('repeater_config', 'text', [
                'null' => true,
                'after' => 'select_options',
                'comment' => 'JSON schema for ms3-repeater field type (columns, rankField, min/max rows)',
            ]);
            $table->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('ms3_extra_fields');

        if ($table->hasColumn('repeater_config')) {
            $table->removeColumn('repeater_config');
            $table->update();
        }
    }
}
