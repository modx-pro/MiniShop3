<?php

use Phinx\Migration\AbstractMigration;

/**
 * Seed default configuration for category products grid
 */
class SeedCategoryProductsGridConfig extends AbstractMigration
{
    public function up()
    {
        $prefix = $this->getAdapter()->getOption('table_prefix') ?? '';

        // Defensive: ensure table exists
        if (!$this->hasTable('ms3_grid_fields')) {
            $this->output->writeln('<comment>Table ms3_grid_fields does not exist, skipping category-products grid seed</comment>');
            return;
        }

        // Idempotency check
        $count = $this->fetchRow("SELECT COUNT(*) as cnt FROM {$prefix}ms3_grid_fields WHERE grid_key = 'category-products'");
        if ($count['cnt'] > 0) {
            $this->output->writeln('<comment>Category products grid fields already exist, skipping</comment>');
            return;
        }

        $now = date('Y-m-d H:i:s');
        $data = [
            // ID column
            [
                'grid_key' => 'category-products',
                'field_name' => 'id',
                'label' => 'ID',
                'lexicon_key' => null,
                'visible' => 1,
                'sort_order' => 0,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '60px',
                'min_width' => '60px',
                'config' => null,
                'is_system' => 1,
                'is_default' => 1,
            ],
            // Image column
            [
                'grid_key' => 'category-products',
                'field_name' => 'thumb',
                'label' => null,
                'lexicon_key' => 'product_image',
                'visible' => 1,
                'sort_order' => 1,
                'sortable' => 0,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '60px',
                'min_width' => '60px',
                'config' => json_encode(['type' => 'image'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Pagetitle column (template with link)
            [
                'grid_key' => 'category-products',
                'field_name' => 'pagetitle',
                'label' => null,
                'lexicon_key' => 'product_pagetitle',
                'visible' => 1,
                'sort_order' => 2,
                'sortable' => 1,
                'filterable' => 1,
                'frozen' => 0,
                'width' => null,
                'min_width' => '200px',
                'config' => json_encode([
                    'type' => 'template',
                    'template' => '<span class="product-id">({id})</span> <a href="?a=resource/update&id={id}" target="_blank" class="product-link">{pagetitle}</a>'
                ], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Article column
            [
                'grid_key' => 'category-products',
                'field_name' => 'article',
                'label' => null,
                'lexicon_key' => 'product_article',
                'visible' => 1,
                'sort_order' => 3,
                'sortable' => 1,
                'filterable' => 1,
                'frozen' => 0,
                'width' => '100px',
                'min_width' => '80px',
                'config' => json_encode(['type' => 'model'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Price column
            [
                'grid_key' => 'category-products',
                'field_name' => 'price',
                'label' => null,
                'lexicon_key' => 'product_price',
                'visible' => 1,
                'sort_order' => 4,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '100px',
                'min_width' => '80px',
                'config' => json_encode(['type' => 'price'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Weight column
            [
                'grid_key' => 'category-products',
                'field_name' => 'weight',
                'label' => null,
                'lexicon_key' => 'product_weight',
                'visible' => 1,
                'sort_order' => 5,
                'sortable' => 1,
                'filterable' => 0,
                'frozen' => 0,
                'width' => '80px',
                'min_width' => '60px',
                'config' => json_encode(['type' => 'weight'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Published column
            [
                'grid_key' => 'category-products',
                'field_name' => 'published',
                'label' => null,
                'lexicon_key' => 'product_published',
                'visible' => 1,
                'sort_order' => 6,
                'sortable' => 1,
                'filterable' => 1,
                'frozen' => 0,
                'width' => '80px',
                'min_width' => '80px',
                'config' => json_encode(['type' => 'boolean'], JSON_UNESCAPED_UNICODE),
                'is_system' => 0,
                'is_default' => 1,
            ],
            // Actions column
            [
                'grid_key' => 'category-products',
                'field_name' => 'actions',
                'label' => null,
                'lexicon_key' => 'actions',
                'visible' => 1,
                'sort_order' => 99,
                'sortable' => 0,
                'filterable' => 0,
                'frozen' => 1,
                'width' => '140px',
                'min_width' => '140px',
                'config' => json_encode([
                    'type' => 'actions',
                    'actions' => [
                        ['name' => 'view', 'handler' => 'view', 'icon' => 'pi-eye', 'label' => 'view'],
                        ['name' => 'edit', 'handler' => 'edit', 'icon' => 'pi-pencil', 'label' => 'edit'],
                        ['name' => 'delete', 'handler' => 'delete', 'icon' => 'pi-trash', 'label' => 'delete', 'severity' => 'danger', 'confirm' => true, 'confirmMessage' => 'product_delete_confirm_message']
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
        $this->execute("DELETE FROM {$prefix}ms3_grid_fields WHERE grid_key = 'category-products'");
    }
}
