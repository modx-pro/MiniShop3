<?php

use Phinx\Migration\AbstractMigration;

/**
 * Seed default configuration for customers grid
 */
class SeedCustomersGridConfig extends AbstractMigration
{
    public function up()
    {
        $prefix = $this->getAdapter()->getOption('table_prefix') ?? '';

        // Defensive: ensure table exists (initial schema may not have run yet)
        if (!$this->hasTable('ms3_grid_fields')) {
            $this->output->writeln('<comment>Table ms3_grid_fields does not exist, skipping customers grid seed</comment>');
            return;
        }

        // Idempotency check
        $count = $this->fetchRow("SELECT COUNT(*) as cnt FROM {$prefix}ms3_grid_fields WHERE grid_key = 'customers'");
        if ($count['cnt'] > 0) {
            $this->output->writeln('<comment>Customers grid fields already exist, skipping</comment>');
            return;
        }

        $now = date('Y-m-d H:i:s');
        $data = [
            // ID column
            [
                'grid_key' => 'customers',
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
            // Name column (composed from first_name + last_name)
            [
                'grid_key' => 'customers',
                'field_name' => 'customer_name',
                'label' => null,
                'lexicon_key' => 'customer_name',
                'visible' => 1,
                'sort_order' => 1,
                'sortable' => 0, // Template field - cannot be sorted
                'filterable' => 1,
                'frozen' => 0,
                'width' => null,
                'min_width' => '200px',
                'config' => json_encode(
                    ['type' => 'template', 'template' => '{first_name} {last_name}'],
                    JSON_UNESCAPED_UNICODE
                ),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Email column
            [
                'grid_key' => 'customers',
                'field_name' => 'email',
                'label' => null,
                'lexicon_key' => 'customer_email',
                'visible' => 1,
                'sort_order' => 2,
                'sortable' => 1,
                'filterable' => 1,
                'frozen' => 0,
                'width' => null,
                'min_width' => '200px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Phone column
            [
                'grid_key' => 'customers',
                'field_name' => 'phone',
                'label' => null,
                'lexicon_key' => 'customer_phone',
                'visible' => 1,
                'sort_order' => 3,
                'sortable' => 0,
                'filterable' => 1,
                'frozen' => 0,
                'width' => '150px',
                'min_width' => '120px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Active column
            [
                'grid_key' => 'customers',
                'field_name' => 'is_active',
                'label' => null,
                'lexicon_key' => 'customer_active',
                'visible' => 1,
                'sort_order' => 4,
                'sortable' => 1,
                'filterable' => 1,
                'frozen' => 0,
                'width' => '100px',
                'min_width' => '100px',
                'config' => json_encode(['type' => 'boolean'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Created at column
            [
                'grid_key' => 'customers',
                'field_name' => 'created_at',
                'label' => null,
                'lexicon_key' => 'created_at',
                'visible' => 1,
                'sort_order' => 5,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '180px',
                'min_width' => '150px',
                'config' => json_encode(['type' => 'model', 'format' => 'datetime'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Actions column
            [
                'grid_key' => 'customers',
                'field_name' => 'actions',
                'label' => null,
                'lexicon_key' => 'actions',
                'visible' => 1,
                'sort_order' => 99, // Always the last column
                'sortable' => 0,
                'filterable' => 0,
                'frozen' => 1, // Actions are always visible
                'width' => '150px',
                'min_width' => '150px',
                'config' => json_encode([
                    'type' => 'actions',
                    'actions' => [
                        [
                            'name' => 'addresses',
                            'handler' => 'addresses',
                            'icon' => 'pi-map-marker',
                            'label' => 'addresses',
                        ],
                        [
                            'name' => 'edit',
                            'handler' => 'edit',
                            'icon' => 'pi-pencil',
                            'label' => 'edit',
                        ],
                        [
                            'name' => 'delete',
                            'handler' => 'delete',
                            'icon' => 'pi-trash',
                            'label' => 'delete',
                            'severity' => 'danger',
                            'confirm' => true,
                            'confirmTitle' => 'customer_delete_confirm_title',
                            'confirmMessage' => 'customer_delete_confirm_message',
                            'confirmAccept' => 'delete',
                        ],
                    ],
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
        $this->execute("DELETE FROM {$prefix}ms3_grid_fields WHERE grid_key = 'customers'");
    }
}
