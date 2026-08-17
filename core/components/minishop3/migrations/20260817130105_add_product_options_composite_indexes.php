<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Composite indexes on ms3_product_options for storefront facets (#565).
 *
 * Facet queries filter by key (+ product scope). Product option loads filter by
 * product_id (+ key). Single-column indexes are superseded by these composites
 * (leftmost prefix covers the old lookups).
 */
final class AddProductOptionsCompositeIndexes extends AbstractMigration
{
    private const TABLE = 'ms3_product_options';

    private const INDEX_KEY_PRODUCT = 'key_product_id';
    private const INDEX_PRODUCT_KEY = 'product_id_key';

    public function up(): void
    {
        $table = $this->table(self::TABLE);

        if (!$table->hasIndexByName(self::INDEX_KEY_PRODUCT)) {
            $table->addIndex(['key', 'product_id'], [
                'name' => self::INDEX_KEY_PRODUCT,
            ]);
        }

        if (!$table->hasIndexByName(self::INDEX_PRODUCT_KEY)) {
            $table->addIndex(['product_id', 'key'], [
                'name' => self::INDEX_PRODUCT_KEY,
            ]);
        }

        $table->update();

        $table = $this->table(self::TABLE);

        // Drop redundant single-column indexes after composites exist.
        if ($table->hasIndexByName('key') && $table->hasIndexByName(self::INDEX_KEY_PRODUCT)) {
            $table->removeIndexByName('key');
        }
        if ($table->hasIndexByName('product_id') && $table->hasIndexByName(self::INDEX_PRODUCT_KEY)) {
            $table->removeIndexByName('product_id');
        }

        $table->update();
    }

    public function down(): void
    {
        $table = $this->table(self::TABLE);

        if (!$table->hasIndexByName('product_id')) {
            $table->addIndex(['product_id'], ['name' => 'product_id']);
        }
        if (!$table->hasIndexByName('key')) {
            $table->addIndex(['key'], ['name' => 'key']);
        }

        $table->update();

        $table = $this->table(self::TABLE);

        if ($table->hasIndexByName(self::INDEX_KEY_PRODUCT)) {
            $table->removeIndexByName(self::INDEX_KEY_PRODUCT);
        }
        if ($table->hasIndexByName(self::INDEX_PRODUCT_KEY)) {
            $table->removeIndexByName(self::INDEX_PRODUCT_KEY);
        }

        $table->update();
    }
}
