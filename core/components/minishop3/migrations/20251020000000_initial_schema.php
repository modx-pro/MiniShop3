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
        'msCustomerToken',
        'msDelivery',
        'msDeliveryMember',
        'msExtraField',
        'msGridField',
        'msLink',
        'msNotificationConfig',
        'msOption',
        'msOptionGroup',
        'msOrder',
        'msOrderAddress',
        'msOrderLog',
        'msOrderProduct',
        'msOrderStatus',
        'msPageSection',
        'msPayment',
        'msProduct',
        'msProductData',
        'msProductField',
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
        $modxConfigPath = dirname(__FILE__, 5) . '/config.core.php';
        if (!file_exists($modxConfigPath)) {
            throw new \RuntimeException('MODX config.core.php not found');
        }

        require_once $modxConfigPath;
        if (!defined('MODX_CORE_PATH')) {
            throw new \RuntimeException('MODX_CORE_PATH not defined');
        }

        require_once MODX_CORE_PATH . 'vendor/autoload.php';
        require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

        $modx = new \MODX\Revolution\modX();
        $modx->initialize('mgr');

        // Add MiniShop3 package with correct path
        $modelPath = MODX_CORE_PATH . 'components/minishop3/src/Model/';
        $modx->addPackage('MiniShop3\\Model', $modelPath, null, 'MiniShop3\\');

        $manager = $modx->getManager();
        $failedTables = [];

        $this->output->writeln('<info>Creating MiniShop3 tables...</info>');
        $this->output->writeln("<comment>Model path: {$modelPath}</comment>");

        foreach ($this->modelClasses as $className) {
            $fullClassName = 'MiniShop3\\Model\\' . $className;

            // Check if model class exists
            $mysqlClass = 'MiniShop3\\Model\\mysql\\' . $className;
            if (!class_exists($mysqlClass)) {
                $this->output->writeln("<error>  ✗ Model class not found: {$mysqlClass}</error>");
                $failedTables[] = $this->resolveLogicalTableName($className) ?? $className;
                continue;
            }

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
            $created = $manager->createObjectContainer($fullClassName);
            if ($created) {
                $this->output->writeln("<info>  ✓ Created table: {$tableName}</info>");
            } else {
                $logicalTable = $this->resolveLogicalTableName($className) ?? trim($tableName, '`');
                $this->output->writeln("<error>  ✗ Failed to create table: {$tableName}</error>");
                $failedTables[] = $logicalTable;

                foreach ($modx->errorHandler->errors as $error) {
                    $message = is_array($error) ? ($error['message'] ?? json_encode($error)) : (string) $error;
                    $this->output->writeln("<error>    {$message}</error>");
                }
                continue;
            }

            $logicalTable = $this->resolveLogicalTableName($className);
            if ($logicalTable !== null && !$this->hasTable($logicalTable)) {
                $this->output->writeln(
                    "<error>  ✗ Failed to create table: {$tableName} (createObjectContainer succeeded but table is missing)</error>"
                );
                $failedTables[] = $logicalTable;
            }
        }

        if ($failedTables !== []) {
            throw new \RuntimeException(
                'MiniShop3 initial schema failed for tables: ' . implode(', ', $failedTables)
            );
        }

        $this->output->writeln('<info>MiniShop3 schema creation completed!</info>');

        // Add foreign keys after all tables are created
        $this->addForeignKeys();
    }

    /**
     * Add foreign key constraints
     */
    protected function addForeignKeys()
    {
        $this->output->writeln('<info>Adding foreign key constraints...</info>');

        // Phinx API (hasTable / $this->table / addForeignKey target) auto-prefixes
        // table names — pass UNPREFIXED. The previous code prepended $prefix manually,
        // resulting in double-prefixed lookups (modx_modx_*) which silently never matched,
        // so FK constraints were never created. Same bug fix as #276 for seed-migrations.
        // msProductField.section -> msPageSection.id
        if ($this->hasTable('ms3_product_fields') && $this->hasTable('ms3_page_sections')) {
            try {
                $table = $this->table('ms3_product_fields');
                $table->addForeignKey('section', 'ms3_page_sections', 'id', [
                    'delete' => 'RESTRICT',
                    'update' => 'CASCADE',
                    'constraint' => 'fk_product_fields_section',
                ])->update();

                $this->output->writeln('<info>  ✓ Added FK: ms3_product_fields.section -> ms3_page_sections.id</info>');
            } catch (\Exception $e) {
                $this->output->writeln('<comment>  ⚠ FK already exists or error: ' . $e->getMessage() . '</comment>');
            }
        }

        $this->output->writeln('<info>Foreign keys setup completed!</info>');
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
        $this->output->writeln('<info>Dropping MiniShop3 tables...</info>');

        $prefix = $this->adapter->getOption('table_prefix');

        foreach ($this->modelClasses as $className) {
            // Unprefixed name for Phinx API; prefixed FQN only for logging.
            $tableName = 'ms3_' . $this->camelToSnake($className);
            $tableFqn = $prefix . $tableName;

            if ($this->hasTable($tableName)) {
                $this->table($tableName)->drop()->save();
                $this->output->writeln("<info>  ✓ Dropped table: {$tableFqn}</info>");
            }
        }

        // Drop migrations table
        $migrationsTable = 'ms3_migrations';
        $migrationsTableFqn = $prefix . $migrationsTable;
        if ($this->hasTable($migrationsTable)) {
            $this->table($migrationsTable)->drop()->save();
            $this->output->writeln("<info>  ✓ Dropped migrations table: {$migrationsTableFqn}</info>");
        }

        $this->output->writeln('<info>MiniShop3 schema removal completed!</info>');
    }

    /**
     * Unprefixed Phinx table name for a model class (e.g. msGridField -> ms3_grid_fields).
     */
    protected function resolveLogicalTableName(string $className): ?string
    {
        $mysqlClass = 'MiniShop3\\Model\\mysql\\' . $className;
        if (!class_exists($mysqlClass)) {
            return null;
        }

        return $mysqlClass::$metaMap['table'] ?? null;
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
