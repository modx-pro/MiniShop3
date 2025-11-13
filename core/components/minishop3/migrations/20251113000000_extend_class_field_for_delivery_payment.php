<?php

use Phinx\Migration\AbstractMigration;

/**
 * Расширение поля `class` в таблицах ms3_deliveries и ms3_payments
 *
 * Проблема:
 * Поле `class` имело размер VARCHAR(50), что недостаточно для хранения
 * полных namespaces классов доставки/оплаты.
 *
 * Пример:
 * \MiniShop3\Controllers\Delivery\FreeDeliveryOver100k = 54 символа
 * \MiniShop3\Controllers\Payment\CustomPaymentGateway = 50+ символов
 *
 * Решение:
 * Увеличиваем размер поля до VARCHAR(255) для обеих таблиц.
 *
 * Связанные изменения:
 * - core/components/minishop3/src/Model/mysql/msDelivery.php
 * - core/components/minishop3/src/Model/mysql/msPayment.php
 */
class ExtendClassFieldForDeliveryPayment extends AbstractMigration
{
    /**
     * Migrate Up - увеличиваем размер поля class
     */
    public function up()
    {
        $this->output->writeln('<info>Extending class field for ms3_deliveries and ms3_payments...</info>');

        // Изменяем поле class в таблице ms3_deliveries
        $this->table('ms3_deliveries')
            ->changeColumn('class', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Full namespace of delivery provider class',
            ])
            ->update();

        $this->output->writeln('<info>  ✓ Extended ms3_deliveries.class to VARCHAR(255)</info>');

        // Изменяем поле class в таблице ms3_payments
        $this->table('ms3_payments')
            ->changeColumn('class', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Full namespace of payment provider class',
            ])
            ->update();

        $this->output->writeln('<info>  ✓ Extended ms3_payments.class to VARCHAR(255)</info>');
    }

    /**
     * Migrate Down - возвращаем исходный размер
     *
     * ВНИМАНИЕ: При откате будут потеряны данные классов с длинными namespace!
     */
    public function down()
    {
        $this->output->writeln('<comment>Rolling back class field extension...</comment>');
        $this->output->writeln('<error>WARNING: Class values longer than 50 chars will be truncated!</error>');

        // Откат для ms3_deliveries
        $this->table('ms3_deliveries')
            ->changeColumn('class', 'string', [
                'limit' => 50,
                'null' => true,
            ])
            ->update();

        $this->output->writeln('<comment>  ✓ Rolled back ms3_deliveries.class to VARCHAR(50)</comment>');

        // Откат для ms3_payments
        $this->table('ms3_payments')
            ->changeColumn('class', 'string', [
                'limit' => 50,
                'null' => true,
            ])
            ->update();

        $this->output->writeln('<comment>  ✓ Rolled back ms3_payments.class to VARCHAR(50)</comment>');
    }
}
