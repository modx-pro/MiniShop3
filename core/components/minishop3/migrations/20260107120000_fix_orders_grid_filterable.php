<?php

use Phinx\Migration\AbstractMigration;

/**
 * Fix duplicate filters in orders grid
 *
 * Problem: delivery_name and payment_name had filterable=1,
 * but static filter config also defines delivery_id and payment_id filters.
 * This caused duplicate filters (text + select) for delivery and payment.
 *
 * Solution: Remove filterable from *_name fields since they are display-only.
 * Filtering should use *_id fields defined in static config.
 */
class FixOrdersGridFilterable extends AbstractMigration
{
    public function up()
    {
        $prefix = $this->getAdapter()->getOption('table_prefix') ?? '';
        $table = $prefix . 'ms3_grid_fields';

        // Defensive: skip if table doesn't exist
        if (!$this->hasTable($table)) {
            $this->output->writeln('<comment>Table ms3_grid_fields does not exist, skipping</comment>');
            return;
        }

        // Remove filterable from display-only fields
        $this->execute("
            UPDATE `{$table}`
            SET filterable = 0
            WHERE grid_key = 'orders'
            AND field_name IN ('delivery_name', 'payment_name', 'status_name')
        ");
    }

    public function down()
    {
        $prefix = $this->getAdapter()->getOption('table_prefix') ?? '';
        $table = $prefix . 'ms3_grid_fields';

        // Defensive: skip if table doesn't exist
        if (!$this->hasTable($table)) {
            return;
        }

        // Restore filterable (original state)
        $this->execute("
            UPDATE `{$table}`
            SET filterable = 1
            WHERE grid_key = 'orders'
            AND field_name IN ('delivery_name', 'payment_name', 'status_name')
        ");
    }
}
