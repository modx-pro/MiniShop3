<?php

use Phinx\Migration\AbstractMigration;

/**
 * Создание таблицы ms3_grid_fields для хранения конфигурации колонок гридов
 */
class CreateGridFieldsTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('ms3_grid_fields', [
            'id' => true,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $table
            ->addColumn('grid_key', 'string', ['limit' => 50, 'null' => false, 'comment' => 'Ключ грида (customers, orders, products)'])
            ->addColumn('field_name', 'string', ['limit' => 100, 'null' => false, 'comment' => 'Имя поля'])
            ->addColumn('label', 'string', ['limit' => 255, 'null' => true, 'comment' => 'Прямой label (переопределяет lexicon)'])
            ->addColumn('lexicon_key', 'string', ['limit' => 255, 'null' => true, 'comment' => 'Ключ лексикона для label'])
            ->addColumn('visible', 'boolean', ['default' => 1, 'comment' => 'Видимость колонки'])
            ->addColumn('sort_order', 'integer', ['default' => 0, 'comment' => 'Порядок отображения'])
            ->addColumn('sortable', 'boolean', ['default' => 1, 'comment' => 'Можно ли сортировать'])
            ->addColumn('filterable', 'boolean', ['default' => 0, 'comment' => 'Можно ли фильтровать'])
            ->addColumn('frozen', 'boolean', ['default' => 0, 'comment' => 'Закреплена ли колонка (слева/справа)'])
            ->addColumn('width', 'string', ['limit' => 50, 'null' => true, 'comment' => 'Ширина колонки (например: 150px, 20%)'])
            ->addColumn('min_width', 'string', ['limit' => 50, 'null' => true, 'comment' => 'Минимальная ширина'])
            ->addColumn('config', 'json', ['null' => true, 'comment' => 'Дополнительная конфигурация (template, type, format)'])
            ->addColumn('is_system', 'boolean', ['default' => 0, 'comment' => 'Системное поле (нельзя удалить)'])
            ->addColumn('is_default', 'boolean', ['default' => 1, 'comment' => 'Дефолтное поле (из seed)'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', [
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'
            ])
            ->addIndex(['grid_key', 'field_name'], [
                'unique' => true,
                'name' => 'idx_grid_field'
            ])
            ->addIndex(['grid_key'], ['name' => 'idx_grid_key'])
            ->addIndex(['visible'], ['name' => 'idx_visible'])
            ->create();
    }
}
