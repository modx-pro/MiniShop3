<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Update orders grid: replace old status_name field with new status badge structure
 * and update delivery_name/payment_name to use relation type
 *
 * Changes:
 * - Remove old status_name field (badge with direct color_field)
 * - Add status_color field (relation to msOrderStatus.color, hidden)
 * - Add status_name field (relation to msOrderStatus.name, hidden)
 * - Add order_status field (badge using status_name and status_color, visible)
 * - Update delivery_name to use relation type (msDelivery)
 * - Update payment_name to use relation type (msPayment)
 */
final class UpdateOrdersGridStatusFields extends AbstractMigration
{
    private string $prefix;

    public function up(): void
    {
        $this->prefix = $this->getAdapter()->getOption('table_prefix') ?? '';

        // Defensive: ensure table exists
        if (!$this->hasTable('ms3_grid_fields')) {
            $this->output->writeln('<comment>Table ms3_grid_fields does not exist, skipping</comment>');
            return;
        }

        $now = date('Y-m-d H:i:s');
        $table = $this->table('ms3_grid_fields');

        // Delete old status_name and cleanup new fields (idempotency for partial re-runs)
        $this->execute("DELETE FROM {$this->prefix}ms3_grid_fields WHERE grid_key = 'orders' AND field_name IN ('status_name', 'order_status', 'status_color')");

        // Add new fields
        $newFields = [
            // Status badge (visible)
            [
                'grid_key' => 'orders',
                'field_name' => 'order_status',
                'label' => 'Статус',
                'lexicon_key' => null,
                'visible' => 1,
                'sort_order' => 3,
                'sortable' => 0,
                'filterable' => 0,
                'frozen' => 0,
                'width' => null,
                'min_width' => null,
                'config' => json_encode([
                    'source_field' => 'status_name',
                    'color_field' => 'status_color',
                    'type' => 'badge'
                ], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // Status color (technical, hidden)
            [
                'grid_key' => 'orders',
                'field_name' => 'status_color',
                'label' => 'Цвет статуса (техническое поле)',
                'lexicon_key' => null,
                'visible' => 0,
                'sort_order' => 16,
                'sortable' => 0,
                'filterable' => 0,
                'frozen' => 0,
                'width' => null,
                'min_width' => null,
                'config' => json_encode([
                    'relation' => [
                        'table' => 'msOrderStatus',
                        'foreignKey' => 'status_id',
                        'displayField' => 'color',
                        'aggregation' => null,
                        'resolvedTableName' => 'msOrderStatus'
                    ],
                    'type' => 'relation'
                ], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // Status name (technical, hidden)
            [
                'grid_key' => 'orders',
                'field_name' => 'status_name',
                'label' => 'Имя статуса (техническое поле)',
                'lexicon_key' => null,
                'visible' => 0,
                'sort_order' => 17,
                'sortable' => 0,
                'filterable' => 0,
                'frozen' => 0,
                'width' => null,
                'min_width' => null,
                'config' => json_encode([
                    'relation' => [
                        'table' => 'msOrderStatus',
                        'foreignKey' => 'status_id',
                        'displayField' => 'name',
                        'aggregation' => null,
                        'resolvedTableName' => 'msOrderStatus'
                    ],
                    'type' => 'relation'
                ], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        $table->insert($newFields)->saveData();

        // Update delivery_name to use relation type
        $deliveryConfig = json_encode([
            'relation' => [
                'table' => 'msDelivery',
                'foreignKey' => 'delivery_id',
                'displayField' => 'name',
                'aggregation' => null,
                'resolvedTableName' => 'msDelivery'
            ],
            'type' => 'relation'
        ], JSON_UNESCAPED_UNICODE);
        $this->execute("UPDATE {$this->prefix}ms3_grid_fields SET config = '{$deliveryConfig}', updated_at = '{$now}' WHERE grid_key = 'orders' AND field_name = 'delivery_name'");

        // Update payment_name to use relation type
        $paymentConfig = json_encode([
            'relation' => [
                'table' => 'msPayment',
                'foreignKey' => 'payment_id',
                'displayField' => 'name',
                'aggregation' => null,
                'resolvedTableName' => 'msPayment'
            ],
            'type' => 'relation'
        ], JSON_UNESCAPED_UNICODE);
        $this->execute("UPDATE {$this->prefix}ms3_grid_fields SET config = '{$paymentConfig}', updated_at = '{$now}' WHERE grid_key = 'orders' AND field_name = 'payment_name'");
    }

    public function down(): void
    {
        $this->prefix = $this->getAdapter()->getOption('table_prefix') ?? '';

        // Defensive: skip if table doesn't exist
        if (!$this->hasTable('ms3_grid_fields')) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        // Delete new fields
        $this->execute("DELETE FROM {$this->prefix}ms3_grid_fields WHERE grid_key = 'orders' AND field_name IN ('order_status', 'status_color', 'status_name')");

        // Restore old status_name field
        $oldField = [
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
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        $this->table('ms3_grid_fields')->insert($oldField)->saveData();

        // Restore delivery_name to old config (text type)
        $oldDeliveryConfig = json_encode([
            'type' => 'text',
            'sort_field' => 'delivery_id'
        ], JSON_UNESCAPED_UNICODE);
        $this->execute("UPDATE {$this->prefix}ms3_grid_fields SET config = '{$oldDeliveryConfig}', updated_at = '{$now}' WHERE grid_key = 'orders' AND field_name = 'delivery_name'");

        // Restore payment_name to old config (text type)
        $oldPaymentConfig = json_encode([
            'type' => 'text',
            'sort_field' => 'payment_id'
        ], JSON_UNESCAPED_UNICODE);
        $this->execute("UPDATE {$this->prefix}ms3_grid_fields SET config = '{$oldPaymentConfig}', updated_at = '{$now}' WHERE grid_key = 'orders' AND field_name = 'payment_name'");
    }
}
