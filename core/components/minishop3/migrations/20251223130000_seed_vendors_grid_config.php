<?php

use Phinx\Migration\AbstractMigration;

/**
 * Seed default configuration for vendors grid
 */
class SeedVendorsGridConfig extends AbstractMigration
{
    public function up()
    {
        $prefix = $this->getAdapter()->getOption('table_prefix') ?? '';

        // Defensive: ensure table exists
        if (!$this->hasTable('ms3_grid_fields')) {
            $this->output->writeln('<comment>Table ms3_grid_fields does not exist, skipping vendors grid seed</comment>');
            return;
        }

        // Idempotency check
        $count = $this->fetchRow("SELECT COUNT(*) as cnt FROM {$prefix}ms3_grid_fields WHERE grid_key = 'vendors'");
        if ($count['cnt'] > 0) {
            $this->output->writeln('<comment>Vendors grid fields already exist, skipping</comment>');
            return;
        }

        $now = date('Y-m-d H:i:s');
        $data = [
            // ID column
            [
                'grid_key' => 'vendors',
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
                'grid_key' => 'vendors',
                'field_name' => 'name',
                'label' => null,
                'lexicon_key' => 'vendor_name',
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
            // Country column
            [
                'grid_key' => 'vendors',
                'field_name' => 'country',
                'label' => null,
                'lexicon_key' => 'vendor_country',
                'visible' => 1,
                'sort_order' => 2,
                'sortable' => 1,
                'filterable' => 1,
                'frozen' => 0,
                'width' => '150px',
                'min_width' => '100px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Email column
            [
                'grid_key' => 'vendors',
                'field_name' => 'email',
                'label' => null,
                'lexicon_key' => 'vendor_email',
                'visible' => 1,
                'sort_order' => 3,
                'sortable' => 1,
                'filterable' => 1,
                'frozen' => 0,
                'width' => '200px',
                'min_width' => '150px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Phone column
            [
                'grid_key' => 'vendors',
                'field_name' => 'phone',
                'label' => null,
                'lexicon_key' => 'vendor_phone',
                'visible' => 1,
                'sort_order' => 4,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '150px',
                'min_width' => '120px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Description column (hidden by default)
            [
                'grid_key' => 'vendors',
                'field_name' => 'description',
                'label' => null,
                'lexicon_key' => 'vendor_description',
                'visible' => 0,
                'sort_order' => 5,
                'sortable' => 0,
                'filterable' => 0,
                'frozen' => 0,
                'width' => null,
                'min_width' => '200px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Logo column (hidden by default)
            [
                'grid_key' => 'vendors',
                'field_name' => 'logo',
                'label' => null,
                'lexicon_key' => 'vendor_logo',
                'visible' => 0,
                'sort_order' => 6,
                'sortable' => 0,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '200px',
                'min_width' => '150px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Address column (hidden by default)
            [
                'grid_key' => 'vendors',
                'field_name' => 'address',
                'label' => null,
                'lexicon_key' => 'vendor_address',
                'visible' => 0,
                'sort_order' => 7,
                'sortable' => 0,
                'filterable' => 0,
                'frozen' => 0,
                'width' => null,
                'min_width' => '200px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Resource ID column (hidden by default)
            [
                'grid_key' => 'vendors',
                'field_name' => 'resource_id',
                'label' => null,
                'lexicon_key' => 'vendor_resource',
                'visible' => 0,
                'sort_order' => 8,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '100px',
                'min_width' => '80px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Position column (hidden by default)
            [
                'grid_key' => 'vendors',
                'field_name' => 'position',
                'label' => null,
                'lexicon_key' => 'vendor_position',
                'visible' => 0,
                'sort_order' => 9,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '100px',
                'min_width' => '80px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Actions column
            [
                'grid_key' => 'vendors',
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
                        ['name' => 'delete', 'handler' => 'delete', 'icon' => 'pi-trash', 'label' => 'delete', 'severity' => 'danger', 'confirm' => true, 'confirmMessage' => 'vendor_delete_confirm_message']
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
        $prefix = $this->getAdapter()->getOption('table_prefix') ?? '';
        if (!$this->hasTable('ms3_grid_fields')) {
            return;
        }
        $this->execute("DELETE FROM {$prefix}ms3_grid_fields WHERE grid_key = 'vendors'");
    }
}
