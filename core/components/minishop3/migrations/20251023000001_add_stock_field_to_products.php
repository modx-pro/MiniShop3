<?php

use Phinx\Migration\AbstractMigration;

class AddStockFieldToProducts extends AbstractMigration
{
    /**
     * Добавление поля stock в таблицу ms3_products
     */
    public function up()
    {
        $table = $this->table('ms3_products');

        if (!$table->hasColumn('stock')) {
            $table
                ->addColumn('stock', 'decimal', [
                    'precision' => 13,
                    'scale' => 3,
                    'null' => true,
                    'default' => '0.000',
                    'comment' => 'Остаток товара на складе',
                    'after' => 'old_price'
                ])
                ->addIndex(['stock'], [
                    'name' => 'stock',
                ])
                ->update();

            $this->output->writeln('<info>Поле stock добавлено в таблицу ms3_products</info>');
        } else {
            $this->output->writeln('<comment>Поле stock уже существует в таблице ms3_products</comment>');
        }
    }

    /**
     * Откат миграции
     */
    public function down()
    {
        $table = $this->table('ms3_products');

        if ($table->hasColumn('stock')) {
            $table
                ->removeIndex(['stock'])
                ->removeColumn('stock')
                ->update();

            $this->output->writeln('<info>Поле stock удалено из таблицы ms3_products</info>');
        }
    }
}
