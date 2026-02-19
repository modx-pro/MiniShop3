<?php

use Phinx\Migration\AbstractMigration;

/**
 * Seed default configuration for orders grid
 */
class SeedOrdersGridConfig extends AbstractMigration
{
    public function up()
    {
        $prefix = $this->adapter->getOption('table_prefix');

        // Check if data already exists (idempotency for partial re-runs)
        $count = $this->fetchRow("SELECT COUNT(*) as cnt FROM {$prefix}ms3_grid_fields WHERE grid_key = 'orders'");
        if ($count['cnt'] > 0) {
            $this->output->writeln('<comment>Orders grid fields already exist, skipping</comment>');
            return;
        }

        $now = date('Y-m-d H:i:s');
        $data = [
            // ID column
            [
                'grid_key' => 'orders',
                'field_name' => 'id',
                'label' => 'ID',
                'lexicon_key' => null,
                'visible' => 1,
                'sort_order' => 0,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 1,
                'width' => '80px',
                'min_width' => '60px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 1,
                'is_default' => 1,
            ],
            // Order number
            [
                'grid_key' => 'orders',
                'field_name' => 'num',
                'label' => null,
                'lexicon_key' => 'order_num',
                'visible' => 1,
                'sort_order' => 1,
                'sortable' => 1,
                'filterable' => 1,
                'frozen' => 0,
                'width' => '100px',
                'min_width' => '80px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Customer name (composed from Address.first_name + Address.last_name)
            [
                'grid_key' => 'orders',
                'field_name' => 'customer',
                'label' => null,
                'lexicon_key' => 'order_customer',
                'visible' => 1,
                'sort_order' => 2,
                'sortable' => 0,
                'filterable' => 1,
                'frozen' => 0,
                'width' => null,
                'min_width' => '150px',
                'config' => json_encode([
                    'type' => 'template',
                    'template' => '{first_name} {last_name}',
                    'link' => [
                        'condition' => 'customer_id',
                        'url' => '?a=mgr/customers&namespace=minishop3&customer={customer_id}'
                    ]
                ], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Status
            [
                'grid_key' => 'orders',
                'field_name' => 'status_name',
                'label' => null,
                'lexicon_key' => 'order_status',
                'visible' => 1,
                'sort_order' => 3,
                'sortable' => 1,
                'filterable' => 1,
                'frozen' => 0,
                'width' => '120px',
                'min_width' => '100px',
                'config' => json_encode([
                    'type' => 'badge',
                    'sort_field' => 'status_id',
                    'color_field' => 'color'
                ], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Cost (total order cost)
            [
                'grid_key' => 'orders',
                'field_name' => 'cost',
                'label' => null,
                'lexicon_key' => 'order_cost',
                'visible' => 1,
                'sort_order' => 4,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '120px',
                'min_width' => '100px',
                'config' => json_encode(['type' => 'price'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Cart cost
            [
                'grid_key' => 'orders',
                'field_name' => 'cart_cost',
                'label' => null,
                'lexicon_key' => 'order_cart_cost',
                'visible' => 0,
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
            // Delivery cost
            [
                'grid_key' => 'orders',
                'field_name' => 'delivery_cost',
                'label' => null,
                'lexicon_key' => 'order_delivery_cost',
                'visible' => 0,
                'sort_order' => 6,
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
                'grid_key' => 'orders',
                'field_name' => 'weight',
                'label' => null,
                'lexicon_key' => 'order_weight',
                'visible' => 0,
                'sort_order' => 7,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '100px',
                'min_width' => '80px',
                'config' => json_encode(['type' => 'weight'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Delivery name
            [
                'grid_key' => 'orders',
                'field_name' => 'delivery_name',
                'label' => null,
                'lexicon_key' => 'order_delivery',
                'visible' => 1,
                'sort_order' => 8,
                'sortable' => 1,
                'filterable' => 1,
                'frozen' => 0,
                'width' => '150px',
                'min_width' => '120px',
                'config' => json_encode([
                    'type' => 'text',
                    'sort_field' => 'delivery_id'
                ], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Payment name
            [
                'grid_key' => 'orders',
                'field_name' => 'payment_name',
                'label' => null,
                'lexicon_key' => 'order_payment',
                'visible' => 1,
                'sort_order' => 9,
                'sortable' => 1,
                'filterable' => 1,
                'frozen' => 0,
                'width' => '150px',
                'min_width' => '120px',
                'config' => json_encode([
                    'type' => 'text',
                    'sort_field' => 'payment_id'
                ], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Created on
            [
                'grid_key' => 'orders',
                'field_name' => 'createdon',
                'label' => null,
                'lexicon_key' => 'order_createdon',
                'visible' => 1,
                'sort_order' => 10,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '150px',
                'min_width' => '130px',
                'config' => json_encode(['type' => 'datetime'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Updated on
            [
                'grid_key' => 'orders',
                'field_name' => 'updatedon',
                'label' => null,
                'lexicon_key' => 'order_updatedon',
                'visible' => 0,
                'sort_order' => 11,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '150px',
                'min_width' => '130px',
                'config' => json_encode(['type' => 'datetime'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Context
            [
                'grid_key' => 'orders',
                'field_name' => 'context',
                'label' => null,
                'lexicon_key' => 'order_context',
                'visible' => 0,
                'sort_order' => 12,
                'sortable' => 1,
                'filterable' => 1,
                'frozen' => 0,
                'width' => '100px',
                'min_width' => '80px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Customer email (from Address)
            [
                'grid_key' => 'orders',
                'field_name' => 'email',
                'label' => null,
                'lexicon_key' => 'order_email',
                'visible' => 0,
                'sort_order' => 13,
                'sortable' => 0,
                'filterable' => 1,
                'frozen' => 0,
                'width' => '200px',
                'min_width' => '150px',
                'config' => json_encode(['type' => 'text'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Customer phone (from Address)
            [
                'grid_key' => 'orders',
                'field_name' => 'phone',
                'label' => null,
                'lexicon_key' => 'order_phone',
                'visible' => 0,
                'sort_order' => 14,
                'sortable' => 0,
                'filterable' => 1,
                'frozen' => 0,
                'width' => '150px',
                'min_width' => '120px',
                'config' => json_encode(['type' => 'text'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Actions column
            [
                'grid_key' => 'orders',
                'field_name' => 'actions',
                'label' => null,
                'lexicon_key' => 'actions',
                'visible' => 1,
                'sort_order' => 99,
                'sortable' => 0,
                'filterable' => 0,
                'frozen' => 1,
                'width' => '120px',
                'min_width' => '100px',
                'config' => json_encode([
                    'type' => 'actions',
                    'actions' => [
                        ['name' => 'edit', 'handler' => 'edit', 'icon' => 'pi-pencil', 'label' => 'edit'],
                        ['name' => 'delete', 'handler' => 'delete', 'icon' => 'pi-trash', 'label' => 'delete', 'severity' => 'danger', 'confirm' => true, 'confirmMessage' => 'order_delete_confirm_message']
                    ]
                ], JSON_UNESCAPED_UNICODE),
                'is_system' => 1,
                'is_default' => 1,
            ],
        ];

        // Add timestamps to each record
        foreach ($data as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }

        $this->table('ms3_grid_fields')->insert($data)->saveData();
    }

    public function down()
    {
        $this->execute("DELETE FROM ms3_grid_fields WHERE grid_key = 'orders'");
    }
}
