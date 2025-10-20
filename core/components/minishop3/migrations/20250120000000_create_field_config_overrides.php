<?php

use Phinx\Migration\AbstractMigration;

class CreateFieldConfigOverrides extends AbstractMigration
{
    /**
     * Создание таблицы для переопределения конфигурации полей
     */
    public function up()
    {
        $table = $this->table('ms3_field_config_overrides', [
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
                'comment' => 'Ключ страницы (product_data, product_left, etc)',
            ])
            ->addColumn('field_name', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'Имя поля (article, price, etc)',
            ])
            ->addColumn('hidden', 'boolean', [
                'null' => false,
                'default' => false,
                'comment' => 'Скрыто ли поле',
            ])
            ->addColumn('sort_order', 'integer', [
                'null' => false,
                'default' => 0,
                'comment' => 'Порядок сортировки',
            ])
            ->addColumn('config', 'text', [
                'null' => true,
                'comment' => 'JSON с дополнительными переопределениями',
            ])
            ->addColumn('context_key', 'string', [
                'limit' => 50,
                'null' => false,
                'default' => 'web',
                'comment' => 'Ключ контекста (web, mgr)',
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
            ->addIndex(['page_key', 'field_name', 'context_key'], [
                'unique' => true,
                'name' => 'idx_page_field_context',
            ])
            ->addIndex(['page_key', 'context_key'], [
                'name' => 'idx_page_context',
            ])
            ->create();

        $this->output->writeln('<info>Таблица ms3_field_config_overrides создана</info>');
    }

    /**
     * Откат миграции
     */
    public function down()
    {
        $this->table('ms3_field_config_overrides')->drop()->save();
        $this->output->writeln('<info>Таблица ms3_field_config_overrides удалена</info>');
    }
}
