<?php

use Phinx\Migration\AbstractMigration;

/**
 * Add foreign key ms3_orders.customer_id → ms3_customers.id
 */
class AddFkOrderCustomer extends AbstractMigration
{
    public function up()
    {
        // If both tables exist – create FK
        if ($this->hasTable('ms3_orders') && $this->hasTable('ms3_customers')) {
            $this->table('ms3_orders')
                ->addForeignKey(
                    'customer_id',
                    'ms3_customers',
                    'id',
                    [
                        'delete' => 'RESTRICT',
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
            $this->table('ms3_orders')
                ->dropForeignKey('fk_orders_customer')
                ->update();
        }
    }
}
