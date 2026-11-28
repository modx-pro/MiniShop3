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

        // A product can have several SKU rows. Restoring the old UNIQUE
        // (order_id, product_id) index after dropping variant_key would fail
        // with "Duplicate entry" and abort the rollback. Check while the
        // variant column still distinguishes the rows and fall back to a
        // non-unique index — losing hold rows to make down() pass is worse.
        $duplicates = $this->fetchAll(
            'SELECT order_id, product_id, COUNT(*) AS c
             FROM ms3_inventory_reservations
             GROUP BY order_id, product_id
             HAVING c > 1
             LIMIT 1'
        );

        if ($table->hasIndexByName('order_product_variant')) {
            $table->removeIndexByName('order_product_variant')->save();
        }
        if ($table->hasColumn('variant_key')) {
            $table->removeColumn('variant_key')->save();
        }

        // Restore the original order_product index (non-unique when SKU rows
        // were collapsed into product rows by the column drop).
        if (!$table->hasIndexByName('order_product')) {
            $table->addIndex(['order_id', 'product_id'], [
                'unique' => $duplicates === [],
                'name' => 'order_product',
            ])->save();
        }
    }
}
