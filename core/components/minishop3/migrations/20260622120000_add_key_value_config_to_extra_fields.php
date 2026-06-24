<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds key_value_config column to ms3_extra_fields for ms3-key-value field schema
 */
final class AddKeyValueConfigToExtraFields extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('ms3_extra_fields');

        if (!$table->hasColumn('key_value_config')) {
            $table->addColumn('key_value_config', 'text', [
                'null' => true,
                'after' => 'repeater_config',
                'comment' => 'JSON schema for ms3-key-value field type (mode, keys, value types, required flags)',
            ]);
            $table->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('ms3_extra_fields');

        if ($table->hasColumn('key_value_config')) {
            $table->removeColumn('key_value_config');
            $table->update();
        }
    }
}
