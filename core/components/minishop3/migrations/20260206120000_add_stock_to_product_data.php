<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds stock column to ms3_products table
 * for tracking product inventory
 */
final class AddStockToProductData extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('ms3_products');

        if (!$table->hasColumn('stock')) {
            $table->addColumn('stock', 'decimal', [
                'precision' => 13,
                'scale' => 3,
                'null' => true,
                'default' => 0,
                'after' => 'old_price',
                'comment' => 'Product stock quantity',
            ]);
            $table->addIndex(['stock'], ['name' => 'stock']);
            $table->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('ms3_products');

        if ($table->hasIndex(['stock'])) {
            $table->removeIndex(['stock']);
        }

        if ($table->hasColumn('stock')) {
            $table->removeColumn('stock');
        }

        $table->update();
    }
}
