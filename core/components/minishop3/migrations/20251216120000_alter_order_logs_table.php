<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Alters ms3_order_logs table:
 * - Changes entry column from VARCHAR(255) to TEXT for JSON data
 * - Adds visible column for customer/manager visibility control
 */
final class AlterOrderLogsTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('ms3_order_logs');

        // Add visible column if not exists
        if (!$table->hasColumn('visible')) {
            $table->addColumn('visible', 'boolean', [
                'null' => false,
                'default' => true,
                'after' => 'entry',
                'comment' => 'Visible to customer (true) or manager only (false)',
            ]);
        }

        // Change entry column type to TEXT for JSON storage
        $table->changeColumn('entry', 'text', [
            'null' => false,
            'comment' => 'Log entry data (JSON format)',
        ]);

        $table->update();
    }

    public function down(): void
    {
        $table = $this->table('ms3_order_logs');

        if ($table->hasColumn('visible')) {
            $table->removeColumn('visible');
        }

        // Revert entry to varchar(255)
        $table->changeColumn('entry', 'string', [
            'limit' => 255,
            'null' => false,
        ]);

        $table->update();
    }
}
