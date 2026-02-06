<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds default_value column to ms3_extra_fields table
 */
final class AddDefaultValueToExtraFields extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('ms3_extra_fields');

        if (!$table->hasColumn('default_value')) {
            $table->addColumn('default_value', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => '',
                'after' => 'select_options',
                'comment' => 'Default value for the field',
            ]);
            $table->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('ms3_extra_fields');

        if ($table->hasColumn('default_value')) {
            $table->removeColumn('default_value');
            $table->update();
        }
    }
}
