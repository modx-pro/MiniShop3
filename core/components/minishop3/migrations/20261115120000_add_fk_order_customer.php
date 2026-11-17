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

            // Step 1: Ensure customer_id column allows NULL using raw SQL
            $this->output->writeln('<info>Ensuring customer_id allows NULL...</info>');
            $this->execute("ALTER TABLE {$ordersTable} MODIFY COLUMN customer_id INT(10) UNSIGNED NULL DEFAULT NULL");

            // Step 2: Clean orphaned references (customer_id = 0 or non‑existent)
            $this->output->writeln('<info>Cleaning orphaned customer_id values...</info>');
            
            // Set customer_id = 0 to NULL
            $sql = "UPDATE {$ordersTable} SET customer_id = NULL WHERE customer_id = 0";
            $this->execute($sql);
            
            // Set customer_id to NULL where it references non-existent customers
            $sql = "UPDATE {$ordersTable} 
                    SET customer_id = NULL 
                    WHERE customer_id NOT IN (SELECT id FROM {$customersTable}) 
                      AND customer_id IS NOT NULL";
            $this->execute($sql);

            // Step 3: Add the foreign key constraint with SET NULL on delete
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

            $this->output->writeln('<info>  ✓ Added FK: ms3_orders.customer_id -> ms3_customers.id (SET NULL on delete)</info>');
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
