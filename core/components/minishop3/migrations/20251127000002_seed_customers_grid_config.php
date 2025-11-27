<?php

use Phinx\Migration\AbstractMigration;

/**
 * Seed дефолтной конфигурации для грида клиентов
 */
class SeedCustomersGridConfig extends AbstractMigration
{
    public function up()
    {
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
                'field_name' => 'name',
                'label' => null,
                'lexicon_key' => 'customer_name',
                'visible' => 1,
                'sort_order' => 1,
                'sortable' => 1,
                'filterable' => 1,
                'frozen' => 0,
                'width' => null,
                'min_width' => '200px',
                'config' => json_encode(['template' => '{first_name} {last_name}'], JSON_UNESCAPED_UNICODE),
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
                'config' => null,
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
                'config' => null,
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
                'config' => json_encode(['format' => 'datetime'], JSON_UNESCAPED_UNICODE),
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
                'sort_order' => 6,
                'sortable' => 0,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '120px',
                'min_width' => '120px',
                'config' => json_encode(['type' => 'actions'], JSON_UNESCAPED_UNICODE),
                'is_system' => 1,
                'is_default' => 1,
            ],
        ];

        $this->table('ms3_grid_fields')->insert($data)->save();
    }

    public function down()
    {
        $this->execute("DELETE FROM ms3_grid_fields WHERE grid_key = 'customers'");
    }
}
