<?php

use Phinx\Migration\AbstractMigration;

/**
 * Seed default configuration for order_products grid
 *
 * Grid key: order_products
 * Model: msOrderProduct
 * Used in: OrderView.vue - products table
 */
class SeedOrderProductsGridConfig extends AbstractMigration
{
    public function up()
    {
        $data = [
            // ID column (system, hidden by default)
            [
                'grid_key' => 'order_products',
                'field_name' => 'id',
                'label' => 'ID',
                'lexicon_key' => null,
                'visible' => 0,
                'sort_order' => 0,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '60px',
                'min_width' => '50px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 1,
                'is_default' => 1,
            ],
            // Product name (main column)
            [
                'grid_key' => 'order_products',
                'field_name' => 'name',
                'label' => null,
                'lexicon_key' => 'order_product_name',
                'visible' => 1,
                'sort_order' => 1,
                'sortable' => 1,
                'filterable' => 1,
                'frozen' => 0,
                'width' => null,
                'min_width' => '200px',
                'config' => json_encode([
                    'type' => 'template',
                    'template' => '{name}',
                    'link' => [
                        'condition' => 'product_id',
                        'url' => '?a=resource/update&id={product_id}'
                    ]
                ], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Product image (thumbnail)
            [
                'grid_key' => 'order_products',
                'field_name' => 'image',
                'label' => null,
                'lexicon_key' => 'order_product_image',
                'visible' => 1,
                'sort_order' => 2,
                'sortable' => 0,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '80px',
                'min_width' => '60px',
                'config' => json_encode([
                    'type' => 'image',
                    'field' => 'image',
                    'width' => 50,
                    'height' => 50
                ], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Count (quantity)
            [
                'grid_key' => 'order_products',
                'field_name' => 'count',
                'label' => null,
                'lexicon_key' => 'order_product_count',
                'visible' => 1,
                'sort_order' => 3,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '100px',
                'min_width' => '80px',
                'config' => json_encode([
                    'type' => 'number',
                    'editable' => true
                ], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Price (unit price)
            [
                'grid_key' => 'order_products',
                'field_name' => 'price',
                'label' => null,
                'lexicon_key' => 'order_product_price',
                'visible' => 1,
                'sort_order' => 4,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '120px',
                'min_width' => '100px',
                'config' => json_encode([
                    'type' => 'price',
                    'editable' => true
                ], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Cost (total = price * count)
            [
                'grid_key' => 'order_products',
                'field_name' => 'cost',
                'label' => null,
                'lexicon_key' => 'order_product_cost',
                'visible' => 1,
                'sort_order' => 5,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '120px',
                'min_width' => '100px',
                'config' => json_encode(['type' => 'price'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Weight
            [
                'grid_key' => 'order_products',
                'field_name' => 'weight',
                'label' => null,
                'lexicon_key' => 'order_product_weight',
                'visible' => 0,
                'sort_order' => 6,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '100px',
                'min_width' => '80px',
                'config' => json_encode(['type' => 'weight'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Options (JSON display)
            [
                'grid_key' => 'order_products',
                'field_name' => 'options',
                'label' => null,
                'lexicon_key' => 'order_product_options',
                'visible' => 1,
                'sort_order' => 7,
                'sortable' => 0,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '200px',
                'min_width' => '150px',
                'config' => json_encode([
                    'type' => 'options',
                    'format' => 'chips'
                ], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Product ID (reference)
            [
                'grid_key' => 'order_products',
                'field_name' => 'product_id',
                'label' => null,
                'lexicon_key' => 'order_product_id',
                'visible' => 0,
                'sort_order' => 8,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '80px',
                'min_width' => '60px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Product key
            [
                'grid_key' => 'order_products',
                'field_name' => 'product_key',
                'label' => null,
                'lexicon_key' => 'order_product_key',
                'visible' => 0,
                'sort_order' => 9,
                'sortable' => 1,
                'filterable' => 1,
                'frozen' => 0,
                'width' => '150px',
                'min_width' => '120px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Actions column
            [
                'grid_key' => 'order_products',
                'field_name' => 'actions',
                'label' => null,
                'lexicon_key' => 'actions',
                'visible' => 1,
                'sort_order' => 99,
                'sortable' => 0,
                'filterable' => 0,
                'frozen' => 1,
                'width' => '100px',
                'min_width' => '80px',
                'config' => json_encode([
                    'type' => 'actions',
                    'actions' => [
                        ['name' => 'edit', 'handler' => 'edit', 'icon' => 'pi-pencil', 'label' => 'edit'],
                        ['name' => 'delete', 'handler' => 'delete', 'icon' => 'pi-trash', 'label' => 'delete', 'severity' => 'danger', 'confirm' => true, 'confirmMessage' => 'order_product_delete_confirm']
                    ]
                ], JSON_UNESCAPED_UNICODE),
                'is_system' => 1,
                'is_default' => 1,
            ],
        ];

        $this->table('ms3_grid_fields')->insert($data)->save();
    }

    public function down()
    {
        $this->execute("DELETE FROM ms3_grid_fields WHERE grid_key = 'order_products'");
    }
}
