<?php

use Phinx\Migration\AbstractMigration;

/**
 * Replace `msOption.modcategory_id` (-> modCategory) with new dedicated `msOptionGroup` model (#10)
 *
 * Steps:
 * 1) Create table `ms3_option_groups` via xPDO Manager (same path as initial_schema).
 * 2) Add nullable column `option_group_id` to `ms3_options`.
 * 3) For each distinct `modcategory_id` referenced by options, create a row in `ms3_option_groups`
 *    using `modCategory.category` as name and modCategory rank/id ordering for sort_order.
 *    Update `ms3_options.option_group_id` accordingly.
 * 4) Drop the legacy `modcategory_id` column.
 *
 * Each step is idempotent (re-runs safely after a partial failure — Phinx + MySQL has no DDL transactions).
 */
class CreateOptionGroupsAndMigrate extends AbstractMigration
{
    public function up(): void
    {
        $prefix = $this->getAdapter()->getOption('table_prefix') ?? '';
        $optionGroupsTable = $prefix . 'ms3_option_groups';
        $optionsTable = $prefix . 'ms3_options';

        // --- Step 1: create ms3_option_groups table via xPDO Manager ---
        if (!$this->hasTable($optionGroupsTable)) {
            $modx = $this->bootstrapModx();
            if ($modx === null) {
                $this->output->writeln('<error>Cannot bootstrap MODX, aborting migration</error>');
                return;
            }
            $manager = $modx->getManager();
            $created = $manager->createObjectContainer(\MiniShop3\Model\msOptionGroup::class);
            if (!$created) {
                $this->output->writeln('<error>Failed to create table ' . $optionGroupsTable . '</error>');
                return;
            }
            $this->output->writeln('<info>Created table ' . $optionGroupsTable . '</info>');
        } else {
            $this->output->writeln('<comment>Table ' . $optionGroupsTable . ' already exists, skipping create</comment>');
        }

        if (!$this->hasTable($optionsTable)) {
            $this->output->writeln('<comment>Table ' . $optionsTable . ' does not exist, skipping column/data migration</comment>');
            return;
        }

        // --- Step 2: add option_group_id column to ms3_options ---
        $optionsTableHelper = $this->table($optionsTable);
        if (!$optionsTableHelper->hasColumn('option_group_id')) {
            $optionsTableHelper
                ->addColumn('option_group_id', 'integer', [
                    'null' => true,
                    'default' => null,
                    'signed' => false,
                    'after' => 'measure_unit',
                ])
                ->addIndex('option_group_id', ['name' => 'option_group_id'])
                ->update();
            $this->output->writeln('<info>Added column option_group_id to ' . $optionsTable . '</info>');
        } else {
            $this->output->writeln('<comment>Column option_group_id already exists in ' . $optionsTable . ', skipping add</comment>');
        }

        // --- Step 3: migrate data from modcategory_id ---
        // Only run while modcategory_id still exists; on re-run after step 4 this becomes a no-op.
        if ($optionsTableHelper->hasColumn('modcategory_id')) {
            $modxPrefix = $this->getModxTablePrefix();
            if ($modxPrefix !== null) {
                $this->migrateGroupData($prefix, $modxPrefix, $optionsTable, $optionGroupsTable);
            } else {
                $this->output->writeln('<error>Cannot resolve MODX table prefix — skipping data migration. Existing modcategory_id values will be lost on column drop.</error>');
            }

            // --- Step 4: drop modcategory_id ---
            $optionsTableHelper = $this->table($optionsTable); // refresh handle
            if ($optionsTableHelper->hasIndex('modcategory_id')) {
                $optionsTableHelper->removeIndexByName('modcategory_id')->update();
            }
            $this->table($optionsTable)
                ->removeColumn('modcategory_id')
                ->update();
            $this->output->writeln('<info>Dropped column modcategory_id from ' . $optionsTable . '</info>');
        } else {
            $this->output->writeln('<comment>Column modcategory_id already removed, skipping data migration + drop</comment>');
        }
    }

    public function down(): void
    {
        $prefix = $this->getAdapter()->getOption('table_prefix') ?? '';
        $optionGroupsTable = $prefix . 'ms3_option_groups';
        $optionsTable = $prefix . 'ms3_options';

        if (!$this->hasTable($optionsTable)) {
            return;
        }

        $optionsTableHelper = $this->table($optionsTable);

        // Restore modcategory_id (data NOT restored — irreversible by design)
        if (!$optionsTableHelper->hasColumn('modcategory_id')) {
            $optionsTableHelper
                ->addColumn('modcategory_id', 'integer', [
                    'null' => false,
                    'default' => 0,
                    'signed' => false,
                    'after' => 'measure_unit',
                ])
                ->addIndex('modcategory_id', ['name' => 'modcategory_id'])
                ->update();
        }

        // Drop option_group_id
        $optionsTableHelper = $this->table($optionsTable);
        if ($optionsTableHelper->hasIndex('option_group_id')) {
            $optionsTableHelper->removeIndexByName('option_group_id')->update();
        }
        if ($this->table($optionsTable)->hasColumn('option_group_id')) {
            $this->table($optionsTable)->removeColumn('option_group_id')->update();
        }

        // Drop ms3_option_groups table
        if ($this->hasTable($optionGroupsTable)) {
            $this->table($optionGroupsTable)->drop()->update();
        }
    }

    /**
     * Bootstrap MODX with MS3 package registered. Returns null on failure.
     *
     * Same pattern as 20251020000000_initial_schema.php.
     */
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

    /**
     * Read MODX table prefix from config.core.php → MODX_CONFIG_KEY → core/config/{key}.inc.php.
     */
    private function getModxTablePrefix(): ?string
    {
        $modxConfigPath = dirname(__FILE__, 5) . '/config.core.php';
        if (!file_exists($modxConfigPath)) {
            return null;
        }
        require_once $modxConfigPath;
        if (!defined('MODX_CORE_PATH') || !defined('MODX_CONFIG_KEY')) {
            return null;
        }

        $cfgPath = MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
        if (!file_exists($cfgPath)) {
            return null;
        }

        // The MODX config script defines $config_options['table_prefix'] and other variables.
        $config_options = [];
        $database_dsn = null;
        $database_user = null;
        $database_password = null;
        $database_type = null;
        $table_prefix = null;

        // phpcs:ignore — variables become available after include
        include $cfgPath;

        if (is_string($table_prefix)) {
            return $table_prefix;
        }
        if (is_array($config_options) && isset($config_options['table_prefix'])) {
            return (string) $config_options['table_prefix'];
        }
        return null;
    }

    /**
     * Read distinct (modcategory_id, name) pairs from modCategory and create matching msOptionGroup rows.
     * Then UPDATE ms3_options.option_group_id by old → new id mapping.
     */
    private function migrateGroupData(
        string $msPrefix,
        string $modxPrefix,
        string $optionsTable,
        string $optionGroupsTable
    ): void {
        // Already migrated? (re-run safety)
        $existingGroups = $this->fetchRow("SELECT COUNT(*) AS cnt FROM `{$optionGroupsTable}`");
        if (isset($existingGroups['cnt']) && (int) $existingGroups['cnt'] > 0) {
            $this->output->writeln('<comment>Option groups table already populated, skipping data migration</comment>');
            return;
        }

        // Get distinct modcategory_id referenced by options + their modCategory names.
        // LEFT JOIN so options pointing at non-existent categories also surface (their name will be NULL).
        $sql = "
            SELECT
                o.modcategory_id AS old_id,
                c.category AS name,
                c.`rank` AS modx_rank
            FROM `{$optionsTable}` o
            LEFT JOIN `{$modxPrefix}categories` c ON c.id = o.modcategory_id
            WHERE o.modcategory_id IS NOT NULL AND o.modcategory_id > 0
            GROUP BY o.modcategory_id, c.category, c.`rank`
            ORDER BY COALESCE(c.`rank`, 0) ASC, o.modcategory_id ASC
        ";
        $rows = $this->fetchAll($sql);

        if (empty($rows)) {
            $this->output->writeln('<comment>No existing modcategory_id references found, nothing to migrate</comment>');
            return;
        }

        $idMap = [];
        $position = 0;
        $now = date('Y-m-d H:i:s');
        $pdo = $this->getAdapter()->getConnection();

        foreach ($rows as $row) {
            $oldId = (int) $row['old_id'];
            if ($oldId <= 0) {
                continue;
            }

            $name = $row['name'];
            // Orphaned modcategory_id (category deleted) — synthesize a stub name.
            if ($name === null || $name === '') {
                $name = 'Imported group #' . $oldId;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO `{$optionGroupsTable}` (name, sort_order, created_at) VALUES (:name, :sort_order, :created_at)"
            );
            $stmt->execute([
                ':name' => $name,
                ':sort_order' => $position,
                ':created_at' => $now,
            ]);
            $newId = (int) $pdo->lastInsertId();
            $idMap[$oldId] = $newId;
            $position++;
        }

        if (empty($idMap)) {
            return;
        }

        $this->output->writeln('<info>Created ' . count($idMap) . ' option groups from modCategory references</info>');

        // Apply mapping: UPDATE option_group_id per old modcategory_id (small N, separate statements OK).
        foreach ($idMap as $oldId => $newId) {
            $stmt = $pdo->prepare(
                "UPDATE `{$optionsTable}` SET option_group_id = :new_id WHERE modcategory_id = :old_id"
            );
            $stmt->execute([
                ':new_id' => $newId,
                ':old_id' => $oldId,
            ]);
        }

        $this->output->writeln('<info>Updated option_group_id for migrated options</info>');
    }
}
