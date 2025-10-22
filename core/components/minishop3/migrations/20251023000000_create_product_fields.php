<?php

use Phinx\Migration\AbstractMigration;

class CreateProductFields extends AbstractMigration
{
    /**
     * Создание таблицы полей товара
     */
    public function up()
    {
        $table = $this->table('ms3_product_fields', [
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
            ->addColumn('name', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'Уникальное имя поля (например: price, article)',
            ])
            ->addColumn('label', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Отображаемое название поля',
            ])
            ->addColumn('xtype', 'string', [
                'limit' => 50,
                'null' => false,
                'default' => 'textfield',
                'comment' => 'Тип поля ExtJS (textfield, numberfield, etc)',
            ])
            ->addColumn('section', 'string', [
                'limit' => 100,
                'null' => true,
                'comment' => 'Ключ секции для группировки',
            ])
            ->addColumn('visible', 'boolean', [
                'null' => false,
                'default' => true,
                'comment' => 'Показано ли поле в админке',
            ])
            ->addColumn('required', 'boolean', [
                'null' => false,
                'default' => false,
                'comment' => 'Обязательно ли поле для заполнения',
            ])
            ->addColumn('sort_order', 'integer', [
                'null' => false,
                'default' => 0,
                'comment' => 'Порядок сортировки',
            ])
            ->addColumn('width', 'integer', [
                'null' => false,
                'default' => 4,
                'comment' => 'Ширина в grid системе (1-12)',
            ])
            ->addColumn('description', 'text', [
                'null' => true,
                'comment' => 'Описание поля',
            ])
            ->addColumn('config', 'text', [
                'null' => true,
                'comment' => 'JSON с дополнительными настройками (decimalPrecision, allowBlank, и т.д.)',
            ])
            ->addColumn('is_system', 'boolean', [
                'null' => false,
                'default' => false,
                'comment' => 'Системное поле (нельзя удалить)',
            ])
            ->addColumn('is_default', 'boolean', [
                'null' => false,
                'default' => false,
                'comment' => 'Дефолтное поле из базовой поставки',
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
            ->addIndex(['name'], [
                'unique' => true,
                'name' => 'idx_unique_name',
            ])
            ->addIndex(['section'], [
                'name' => 'idx_section',
            ])
            ->addIndex(['visible', 'sort_order'], [
                'name' => 'idx_visible_sort',
            ])
            ->create();

        $this->output->writeln('<info>Таблица ms3_product_fields создана</info>');

        // Заполнение дефолтными полями
        $this->insertDefaultFields();
    }

    /**
     * Вставка дефолтных полей из JSON конфигов + новое поле stock
     */
    private function insertDefaultFields()
    {
        $data = [
            // Левая колонка (из data-tab-left.json)
            [
                'name' => 'price',
                'label' => null, // будет из лексикона
                'xtype' => 'numberfield',
                'section' => 'pricing',
                'visible' => true,
                'required' => false,
                'sort_order' => 10,
                'width' => 6,
                'config' => '{"decimalPrecision":2}',
                'is_system' => false,
                'is_default' => true,
            ],
            [
                'name' => 'old_price',
                'label' => null,
                'xtype' => 'numberfield',
                'section' => 'pricing',
                'visible' => true,
                'required' => false,
                'sort_order' => 20,
                'width' => 6,
                'config' => '{"decimalPrecision":2}',
                'is_system' => false,
                'is_default' => true,
            ],
            [
                'name' => 'stock',
                'label' => null,
                'xtype' => 'numberfield',
                'section' => 'pricing',
                'visible' => true,
                'required' => false,
                'sort_order' => 30,
                'width' => 6,
                'config' => '{"decimalPrecision":0,"allowNegative":false}',
                'is_system' => false,
                'is_default' => true,
            ],
            [
                'name' => 'article',
                'label' => null,
                'xtype' => 'textfield',
                'section' => 'main',
                'visible' => true,
                'required' => false,
                'sort_order' => 40,
                'width' => 6,
                'config' => null,
                'is_system' => false,
                'is_default' => true,
            ],
            [
                'name' => 'weight',
                'label' => null,
                'xtype' => 'numberfield',
                'section' => 'main',
                'visible' => true,
                'required' => false,
                'sort_order' => 50,
                'width' => 6,
                'config' => '{"decimalPrecision":3}',
                'is_system' => false,
                'is_default' => true,
            ],
            [
                'name' => 'color',
                'label' => null,
                'xtype' => 'ms3-combo-options',
                'section' => 'main',
                'visible' => true,
                'required' => false,
                'sort_order' => 60,
                'width' => 6,
                'config' => null,
                'is_system' => false,
                'is_default' => true,
            ],
            [
                'name' => 'size',
                'label' => null,
                'xtype' => 'ms3-combo-options',
                'section' => 'main',
                'visible' => true,
                'required' => false,
                'sort_order' => 70,
                'width' => 6,
                'config' => null,
                'is_system' => false,
                'is_default' => true,
            ],
            [
                'name' => 'vendor_id',
                'label' => null,
                'xtype' => 'ms3-combo-vendor',
                'section' => 'main',
                'visible' => true,
                'required' => false,
                'sort_order' => 80,
                'width' => 6,
                'config' => null,
                'is_system' => false,
                'is_default' => true,
            ],
            [
                'name' => 'made_in',
                'label' => null,
                'xtype' => 'ms3-combo-autocomplete',
                'section' => 'main',
                'visible' => true,
                'required' => false,
                'sort_order' => 90,
                'width' => 6,
                'config' => null,
                'is_system' => false,
                'is_default' => true,
            ],
            [
                'name' => 'tags',
                'label' => null,
                'xtype' => 'ms3-combo-options',
                'section' => 'additional',
                'visible' => true,
                'required' => false,
                'sort_order' => 100,
                'width' => 12,
                'config' => null,
                'is_system' => false,
                'is_default' => true,
            ],
            [
                'name' => 'new',
                'label' => null,
                'xtype' => 'xcheckbox',
                'section' => 'additional',
                'visible' => true,
                'required' => false,
                'sort_order' => 110,
                'width' => 4,
                'config' => '{"inputValue":1}',
                'is_system' => false,
                'is_default' => true,
            ],
            [
                'name' => 'favorite',
                'label' => null,
                'xtype' => 'xcheckbox',
                'section' => 'additional',
                'visible' => true,
                'required' => false,
                'sort_order' => 120,
                'width' => 4,
                'config' => '{"inputValue":1}',
                'is_system' => false,
                'is_default' => true,
            ],
            [
                'name' => 'popular',
                'label' => null,
                'xtype' => 'xcheckbox',
                'section' => 'additional',
                'visible' => true,
                'required' => false,
                'sort_order' => 130,
                'width' => 4,
                'config' => '{"inputValue":1}',
                'is_system' => false,
                'is_default' => true,
            ],
        ];

        $table = $this->table('ms3_product_fields');
        $table->insert($data)->saveData();

        $this->output->writeln('<info>Добавлено ' . count($data) . ' дефолтных полей товара</info>');
    }

    /**
     * Откат миграции
     */
    public function down()
    {
        $this->table('ms3_product_fields')->drop()->save();
        $this->output->writeln('<info>Таблица ms3_product_fields удалена</info>');
    }
}
