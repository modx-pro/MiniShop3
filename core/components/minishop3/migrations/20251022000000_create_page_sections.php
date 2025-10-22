<?php

use Phinx\Migration\AbstractMigration;

class CreatePageSections extends AbstractMigration
{
    /**
     * Создание таблицы секций для страниц админки
     */
    public function up()
    {
        $table = $this->table('ms3_page_sections', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $table
            ->addColumn('id', 'integer', [
                'identity' => true,
                'signed' => false,
            ])
            ->addColumn('page_key', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'Ключ страницы (product_data, order, etc)',
            ])
            ->addColumn('section_key', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'Ключ секции (main, pricing, custom_section, etc)',
            ])
            ->addColumn('hidden', 'boolean', [
                'null' => false,
                'default' => false,
                'comment' => 'Скрыта ли секция',
            ])
            ->addColumn('sort_order', 'integer', [
                'null' => false,
                'default' => 0,
                'comment' => 'Порядок сортировки',
            ])
            ->addColumn('config', 'text', [
                'null' => true,
                'comment' => 'JSON с дополнительными настройками (lexicon_key, label и т.д.)',
            ])
            ->addColumn('is_default', 'boolean', [
                'null' => false,
                'default' => false,
                'comment' => 'Дефолтная секция из базовой поставки компонента',
            ])
            ->addColumn('created_at', 'timestamp', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addColumn('updated_at', 'timestamp', [
                'null' => true,
                'default' => null,
                'update' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['page_key', 'section_key'], [
                'unique' => true,
                'name' => 'idx_page_section',
            ])
            ->addIndex(['page_key'], [
                'name' => 'idx_page_key',
            ])
            ->create();

        $this->output->writeln('<info>Таблица ms3_page_sections создана</info>');

        // Вставка базовых секций для product_data
        $data = [
            [
                'page_key' => 'product_data',
                'section_key' => 'main',
                'hidden' => 0,
                'sort_order' => 10,
                'config' => '{"lexicon_key":"ms3_section_main"}',
                'is_default' => 1,
            ],
            [
                'page_key' => 'product_data',
                'section_key' => 'pricing',
                'hidden' => 0,
                'sort_order' => 20,
                'config' => '{"lexicon_key":"ms3_section_pricing"}',
                'is_default' => 0,
            ],
            [
                'page_key' => 'product_data',
                'section_key' => 'seo',
                'hidden' => 0,
                'sort_order' => 30,
                'config' => '{"lexicon_key":"ms3_section_seo"}',
                'is_default' => 0,
            ],
            [
                'page_key' => 'product_data',
                'section_key' => 'additional',
                'hidden' => 0,
                'sort_order' => 40,
                'config' => '{"lexicon_key":"ms3_section_additional"}',
                'is_default' => 0,
            ],
        ];

        $table->insert($data)->saveData();

        $this->output->writeln('<info>Базовые секции для product_data добавлены</info>');
    }

    /**
     * Откат миграции
     */
    public function down()
    {
        $this->table('ms3_page_sections')->drop()->save();
        $this->output->writeln('<info>Таблица ms3_page_sections удалена</info>');
    }
}
