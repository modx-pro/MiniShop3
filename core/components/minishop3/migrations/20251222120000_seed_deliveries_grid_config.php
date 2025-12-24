<?php

use Phinx\Migration\AbstractMigration;

/**
 * Seed default configuration for deliveries grid
 */
class SeedDeliveriesGridConfig extends AbstractMigration
{
    public function up()
    {
        $data = [
            // ID column
            [
                'grid_key' => 'deliveries',
                'field_name' => 'id',
                'label' => 'ID',
                'lexicon_key' => null,
                'visible' => 1,
                'sort_order' => 0,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 1,
                'width' => '80px',
                'min_width' => '80px',
                'config' => null,
                'is_system' => 1,
                'is_default' => 1,
            ],
            // Name column
            [
                'grid_key' => 'deliveries',
                'field_name' => 'name',
                'label' => null,
                'lexicon_key' => 'delivery_name',
                'visible' => 1,
                'sort_order' => 1,
                'sortable' => 1,
                'filterable' => 1,
                'frozen' => 0,
                'width' => null,
                'min_width' => '200px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Description column
            [
                'grid_key' => 'deliveries',
                'field_name' => 'description',
                'label' => null,
                'lexicon_key' => 'delivery_description',
                'visible' => 0,
                'sort_order' => 2,
                'sortable' => 0,
                'filterable' => 0,
                'frozen' => 0,
                'width' => null,
                'min_width' => '200px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Price column
            [
                'grid_key' => 'deliveries',
                'field_name' => 'price',
                'label' => null,
                'lexicon_key' => 'delivery_price',
                'visible' => 1,
                'sort_order' => 3,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '120px',
                'min_width' => '100px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Weight price column
            [
                'grid_key' => 'deliveries',
                'field_name' => 'weight_price',
                'label' => null,
                'lexicon_key' => 'delivery_weight_price',
                'visible' => 0,
                'sort_order' => 4,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '120px',
                'min_width' => '100px',
                'config' => json_encode(['type' => 'model', 'format' => 'number'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Free delivery amount column
            [
                'grid_key' => 'deliveries',
                'field_name' => 'free_delivery_amount',
                'label' => null,
                'lexicon_key' => 'delivery_free_amount',
                'visible' => 1,
                'sort_order' => 5,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '150px',
                'min_width' => '120px',
                'config' => json_encode(['type' => 'model', 'format' => 'number'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Active column
            [
                'grid_key' => 'deliveries',
                'field_name' => 'active',
                'label' => null,
                'lexicon_key' => 'delivery_active',
                'visible' => 1,
                'sort_order' => 6,
                'sortable' => 1,
                'filterable' => 1,
                'frozen' => 0,
                'width' => '100px',
                'min_width' => '100px',
                'config' => json_encode(['type' => 'boolean'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Position column
            [
                'grid_key' => 'deliveries',
                'field_name' => 'position',
                'label' => null,
                'lexicon_key' => 'delivery_position',
                'visible' => 1,
                'sort_order' => 7,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '100px',
                'min_width' => '80px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Class column
            [
                'grid_key' => 'deliveries',
                'field_name' => 'class',
                'label' => null,
                'lexicon_key' => 'delivery_class',
                'visible' => 0,
                'sort_order' => 8,
                'sortable' => 0,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '200px',
                'min_width' => '150px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Actions column
            [
                'grid_key' => 'deliveries',
                'field_name' => 'actions',
                'label' => null,
                'lexicon_key' => 'actions',
                'visible' => 1,
                'sort_order' => 99,
                'sortable' => 0,
                'filterable' => 0,
                'frozen' => 1,
                'width' => '120px',
                'min_width' => '120px',
                'config' => json_encode([
                    'type' => 'actions',
                    'actions' => [
                        ['name' => 'edit', 'handler' => 'edit', 'icon' => 'pi-pencil', 'label' => 'edit'],
                        ['name' => 'delete', 'handler' => 'delete', 'icon' => 'pi-trash', 'label' => 'delete', 'severity' => 'danger', 'confirm' => true, 'confirmMessage' => 'delivery_delete_confirm_message']
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
        $this->execute("DELETE FROM ms3_grid_fields WHERE grid_key = 'deliveries'");
    }
}
