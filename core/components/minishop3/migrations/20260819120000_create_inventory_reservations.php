<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Ledger for inventory reserve / commit / release (idempotent per order_id + product_id).
 */
final class CreateInventoryReservations extends AbstractMigration
{
    public function up(): void
    {
        if ($this->hasTable('ms3_inventory_reservations')) {
            return;
        }

        $table = $this->table('ms3_inventory_reservations', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => 'Inventory reservation ledger',
        ]);
        $table
            ->addColumn('id', 'integer', [
                'identity' => true,
                'signed' => false,
                'null' => false,
            ])
            ->addColumn('order_id', 'integer', [
                'signed' => false,
                'null' => false,
            ])
            ->addColumn('product_id', 'integer', [
                'signed' => false,
                'null' => false,
            ])
            ->addColumn('qty', 'decimal', [
                'precision' => 13,
                'scale' => 3,
                'null' => false,
                'default' => 0,
            ])
            ->addColumn('state', 'string', [
                'limit' => 16,
                'null' => false,
                'default' => 'reserved',
            ])
            ->addColumn('createdon', 'integer', [
                'signed' => false,
                'null' => true,
            ])
            ->addColumn('updatedon', 'integer', [
                'signed' => false,
                'null' => true,
            ])
            ->addIndex(['order_id', 'product_id'], [
                'unique' => true,
                'name' => 'order_product',
            ])
            ->addIndex(['product_id'], ['name' => 'product_id'])
            ->create();
    }

    public function down(): void
    {
        if ($this->hasTable('ms3_inventory_reservations')) {
            $this->table('ms3_inventory_reservations')->drop()->save();
        }
    }
}
