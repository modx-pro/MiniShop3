<?php

use Phinx\Migration\AbstractMigration;

/**
 * Customer groups linked to MODX user groups for catalog RG visibility (#669).
 *
 * Creates ms3_customer_groups and nullable customer_group_id on ms3_customers.
 * `user_group_id` is not unique: several customer segments may share one MODX
 * modUserGroup principal (same ACL, different labels).
 */
class CreateCustomerGroups extends AbstractMigration
{
    public function up(): void
    {
        $prefix = $this->getAdapter()->getOption('table_prefix') ?? '';
        $groupsFqn = $prefix . 'ms3_customer_groups';

        if (!$this->hasTable('ms3_customer_groups')) {
            require_once __DIR__ . '/_modx.php';
            $modx = ms3MigrationBootstrap();
            if ($modx === null) {
                throw new \RuntimeException('Cannot bootstrap MODX for ms3_customer_groups');
            }
            $created = $modx->getManager()->createObjectContainer(\MiniShop3\Model\msCustomerGroup::class);
            if (!$created) {
                throw new \RuntimeException('Failed to create table ' . $groupsFqn);
            }
            ms3MigrationRefreshPhinxTransaction($this->getAdapter());
            $this->output->writeln('<info>Created table ' . $groupsFqn . '</info>');
        } else {
            $this->output->writeln('<comment>Table ' . $groupsFqn . ' already exists, skipping create</comment>');
        }

        if (!$this->hasTable('ms3_customers')) {
            $this->output->writeln('<comment>Table ms3_customers does not exist, skipping column add</comment>');

            return;
        }

        $customersTable = $this->table('ms3_customers');
        if (!$customersTable->hasColumn('customer_group_id')) {
            $customersTable
                ->addColumn('customer_group_id', 'integer', [
                    'null' => true,
                    'default' => null,
                    'signed' => false,
                    'after' => 'user_id',
                ])
                ->addIndex('customer_group_id', ['name' => 'customer_group_id'])
                ->update();
            $this->output->writeln('<info>Added column customer_group_id to ms3_customers</info>');
        } else {
            $this->output->writeln('<comment>Column customer_group_id already exists, skipping add</comment>');
        }
    }

    public function down(): void
    {
        if ($this->hasTable('ms3_customers') && $this->table('ms3_customers')->hasColumn('customer_group_id')) {
            $this->table('ms3_customers')
                ->removeIndexByName('customer_group_id')
                ->removeColumn('customer_group_id')
                ->update();
        }

        if ($this->hasTable('ms3_customer_groups')) {
            $this->table('ms3_customer_groups')->drop()->save();
        }
    }
}
