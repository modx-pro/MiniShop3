<?php
use Phinx\Migration\AbstractMigration;

/**
 * Add foreign key ms3_orders.customer_id → ms3_customers.id
 *
 * Handles legacy data where customer_id may be 0 or reference non-existent customers.
 * Sets orphaned orders to customer_id = NULL before adding the FK constraint.
 */
class AddFkOrderCustomer extends AbstractMigration
{
    public function up()
    {
        // If both tables exist – create FK
        if ($this->hasTable('ms3_orders') && $this->hasTable('ms3_customers')) {
            $adapter = $this->getAdapter();
            $prefix = $adapter->getOption('table_prefix');
            $ordersTable = $prefix . 'ms3_orders';
            $customersTable = $prefix . 'ms3_customers';

            // Ensure customer_id column allows NULL
            $this->output->writeln('<info>Ensuring customer_id allows NULL...</info>');
            $this->changeColumn('ms3_orders', 'customer_id', [
                'type' => 'integer',
                'signed' => true,
                'null' => true,
                'default' => null,
            ]);

            // Clean orphaned references (customer_id = 0 or non‑existent)
            $this->output->writeln('<info>Cleaning orphaned customer_id values...</info>');
            $sql = "UPDATE {$ordersTable} SET customer_id = NULL WHERE customer_id = 0 OR customer_id IS NULL";
            $this->execute($sql);
            $sql = "UPDATE {$ordersTable} SET customer_id = NULL WHERE customer_id NOT IN (SELECT id FROM {$customersTable}) AND customer_id IS NOT NULL";
            $this->execute($sql);

            // Add foreign key with SET NULL on delete
            $this->output->writeln('<info>Adding FK constraint...</info>');
            $this->table('ms3_orders')
                ->addForeignKey(
                    'customer_id',
                    'ms3_customers',
                    'id',
                    [
                        'delete' => 'SET NULL',
                        'update' => 'CASCADE',
                        'constraint' => 'fk_orders_customer',
                    ]
                )
                ->update();
        }
    }

    public function down()
    {
        if ($this->hasTable('ms3_orders')) {
            $this->output->writeln('<info>Dropping FK constraint...</info>');
            $this->table('ms3_orders')
                ->dropForeignKey('fk_orders_customer')
                ->update();
        }
    }
}
