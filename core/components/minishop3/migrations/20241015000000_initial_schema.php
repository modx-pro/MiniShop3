<?php

use Phinx\Migration\AbstractMigration;

/**
 * Initial MiniShop3 database schema
 * Creates all tables using xPDO Manager from model metadata
 */
class InitialSchema extends AbstractMigration
{
    /**
     * List of all MiniShop3 model classes
     */
    protected $modelClasses = [
        'msCategory',
        'msCategoryMember',
        'msCategoryOption',
        'msCustomer',
        'msCustomerAddress',
        'msDelivery',
        'msDeliveryMember',
        'msExtraField',
        'msLink',
        'msOption',
        'msOrder',
        'msOrderAddress',
        'msOrderLog',
        'msOrderProduct',
        'msOrderStatus',
        'msPayment',
        'msProduct',
        'msProductData',
        'msProductFile',
        'msProductLink',
        'msProductOption',
        'msVendor',
    ];

    /**
     * Migrate Up.
     */
    public function up()
    {
        // Get MODX instance
        $modxConfigPath = dirname(__FILE__, 3) . '/config.core.php';
        if (!file_exists($modxConfigPath)) {
            $this->output->writeln('<error>MODX config.core.php not found</error>');
            return;
        }

        require_once $modxConfigPath;
        if (!defined('MODX_CORE_PATH')) {
            $this->output->writeln('<error>MODX_CORE_PATH not defined</error>');
            return;
        }

        require_once MODX_CORE_PATH . 'vendor/autoload.php';
        require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

        $modx = new \MODX\Revolution\modX();
        $modx->initialize('mgr');

        // Add MiniShop3 package
        $modx->addPackage('MiniShop3\\Model', MODX_CORE_PATH . 'components/minishop3/src/', null, 'MiniShop3\\');

        $manager = $modx->getManager();

        $this->output->writeln('<info>Creating MiniShop3 tables...</info>');

        foreach ($this->modelClasses as $className) {
            $fullClassName = 'MiniShop3\\Model\\' . $className;
            $tableName = $modx->getTableName($fullClassName);

            // Check if table already exists
            $sql = "SHOW TABLES LIKE '" . trim($tableName, '`') . "'";
            $stmt = $this->adapter->getConnection()->prepare($sql);
            $stmt->execute();

            if ($stmt->fetch()) {
                $this->output->writeln("<comment>  Table {$tableName} already exists, skipping</comment>");
                continue;
            }

            // Create table
            if ($manager->createObjectContainer($fullClassName)) {
                $this->output->writeln("<info>  ✓ Created table: {$tableName}</info>");
            } else {
                $this->output->writeln("<error>  ✗ Failed to create table: {$tableName}</error>");
            }
        }

        $this->output->writeln('<info>MiniShop3 schema creation completed!</info>');
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
        $this->output->writeln('<info>Dropping MiniShop3 tables...</info>');

        $prefix = $this->adapter->getOption('table_prefix');

        foreach ($this->modelClasses as $className) {
            $tableName = $prefix . 'ms3_' . $this->camelToSnake($className);

            if ($this->hasTable($tableName)) {
                $this->table($tableName)->drop()->save();
                $this->output->writeln("<info>  ✓ Dropped table: {$tableName}</info>");
            }
        }

        // Drop migrations table
        $migrationsTable = $prefix . 'ms3_migrations';
        if ($this->hasTable($migrationsTable)) {
            $this->table($migrationsTable)->drop()->save();
            $this->output->writeln("<info>  ✓ Dropped migrations table: {$migrationsTable}</info>");
        }

        $this->output->writeln('<info>MiniShop3 schema removal completed!</info>');
    }

    /**
     * Convert CamelCase to snake_case
     * Example: msOrderProduct -> order_products
     */
    protected function camelToSnake($input)
    {
        // Remove 'ms' prefix
        $input = preg_replace('/^ms/', '', $input);

        // Convert to snake_case
        $output = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $input));

        // Pluralize (simple rules)
        if (substr($output, -1) === 'y') {
            $output = substr($output, 0, -1) . 'ies';
        } elseif (substr($output, -1) === 's') {
            $output .= 'es';
        } else {
            $output .= 's';
        }

        return $output;
    }
}
