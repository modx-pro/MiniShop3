<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Provider webhook event ids for shipment replay (#606).
 */
final class CreateShipmentEvents extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('ms3_shipment_events')) {
            return;
        }

        $this->table('ms3_shipment_events', [
            'id' => true,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('shipment_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('provider_event_id', 'string', ['limit' => 191, 'null' => false])
            ->addColumn('createdon', 'integer', ['signed' => false, 'null' => true, 'default' => null])
            ->addIndex(['shipment_id', 'provider_event_id'], [
                'unique' => true,
                'name' => 'uniq_shipment_provider_event',
            ])
            ->create();
    }
}
