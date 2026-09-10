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
        if (!$this->hasTable('ms3_customer_groups')) {
            $modx = $this->bootstrapModx();
            if ($modx === null) {
                $this->output->writeln('<error>Cannot bootstrap MODX, aborting migration</error>');

                return;
            }
            $manager = $modx->getManager();
            $created = $manager->createObjectContainer(\MiniShop3\Model\msCustomerGroup::class);
            if (!$created) {
                $prefix = $this->getAdapter()->getOption('table_prefix') ?? '';
                $this->output->writeln('<error>Failed to create table ' . $prefix . 'ms3_customer_groups</error>');

                return;
            }
            $this->output->writeln('<info>Created table ms3_customer_groups</info>');
        } else {
            $this->output->writeln('<comment>Table ms3_customer_groups already exists, skipping create</comment>');
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

    private function bootstrapModx(): ?\MODX\Revolution\modX
    {
        $modxConfigPath = dirname(__FILE__, 5) . '/config.core.php';
        if (!file_exists($modxConfigPath)) {
            $this->output->writeln('<error>MODX config.core.php not found</error>');

            return null;
        }

        require_once $modxConfigPath;
        if (!defined('MODX_CORE_PATH')) {
            $this->output->writeln('<error>MODX_CORE_PATH not defined</error>');

            return null;
        }

        require_once MODX_CORE_PATH . 'vendor/autoload.php';
        require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

        $modx = new \MODX\Revolution\modX();
        $modx->initialize('mgr');

        $modelPath = MODX_CORE_PATH . 'components/minishop3/src/Model/';
        $modx->addPackage('MiniShop3\\Model', $modelPath, null, 'MiniShop3\\');

        return $modx;
    }
}
