<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Variant stock ledger key for #1001.
 *
 * `variant_key` distinguishes SKU-level holds of the same product.
 * '' means product-level (equivalent to variantKey = null): a single
 * NOT NULL column keeps the unique index meaningful — in MySQL a
 * UNIQUE index over NULLs would allow duplicate product-level rows.
 */
final class AddVariantKeyToInventoryReservations extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('ms3_inventory_reservations')) {
            return;
        }

        $table = $this->table('ms3_inventory_reservations');

        if (!$table->hasColumn('variant_key')) {
            $table->addColumn('variant_key', 'string', [
                'limit' => 128,
                'null' => false,
                'default' => '',
                'after' => 'product_id',
            ])->save();
        }

        if ($table->hasIndexByName('order_product')) {
            $table->removeIndexByName('order_product')->save();
        }

        if (!$table->hasIndexByName('order_product_variant')) {
            $table->addIndex(['order_id', 'product_id', 'variant_key'], [
                'unique' => true,
                'name' => 'order_product_variant',
            ])->save();
        }
    }

    public function down(): void
    {
        if (!$this->hasTable('ms3_inventory_reservations')) {
            return;
        }

        $table = $this->table('ms3_inventory_reservations');

        if ($table->hasIndexByName('order_product_variant')) {
            $table->removeIndexByName('order_product_variant')->save();
        }
        if ($table->hasColumn('variant_key')) {
            $table->removeColumn('variant_key')->save();
        }
    }
}
