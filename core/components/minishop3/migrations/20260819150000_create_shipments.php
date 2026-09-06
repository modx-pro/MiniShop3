<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Order shipments for fulfillment and tracking (#591).
 */
final class CreateShipments extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('ms3_shipments')) {
            return;
        }

        $this->table('ms3_shipments', [
            'id' => true,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('order_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('delivery_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('status', 'string', ['limit' => 32, 'null' => false, 'default' => 'preparing'])
            ->addColumn('tracking_number', 'string', ['limit' => 191, 'null' => true, 'default' => null])
            ->addColumn('external_id', 'string', ['limit' => 191, 'null' => true, 'default' => null])
            ->addColumn('provider', 'string', ['limit' => 191, 'null' => true, 'default' => null])
            ->addColumn('carrier', 'string', ['limit' => 191, 'null' => true, 'default' => null])
            ->addColumn('shipped_at', 'integer', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('delivered_at', 'integer', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('last_event_id', 'string', ['limit' => 191, 'null' => true, 'default' => null])
            ->addColumn('meta', 'text', ['null' => true])
            ->addColumn('createdon', 'integer', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('updatedon', 'integer', ['signed' => false, 'null' => true, 'default' => null])
            ->addIndex(['order_id'], ['unique' => true, 'name' => 'uniq_shipment_order'])
            ->addIndex(['delivery_id', 'provider', 'external_id'], [
                'unique' => true,
                'name' => 'uniq_shipment_external',
            ])
            ->create();
    }
}
