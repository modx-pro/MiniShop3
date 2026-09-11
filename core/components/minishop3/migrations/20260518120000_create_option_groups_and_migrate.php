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
 *
 * NOTE on table names: Phinx API methods (`hasTable`, `$this->table()`, `hasColumn`) accept the
 * UNPREFIXED table name — the adapter applies `table_prefix` automatically. Raw SQL and the
 * xPDO Manager call do need the prefix manually.
 */
class CreateOptionGroupsAndMigrate extends AbstractMigration
{
    public function up(): void
    {
        $prefix = $this->getAdapter()->getOption('table_prefix') ?? '';
        // Fully-qualified names for raw SQL / xPDO logging only.
        $optionGroupsFqn = $prefix . 'ms3_option_groups';
        $optionsFqn = $prefix . 'ms3_options';

        // --- Step 1: create ms3_option_groups table via xPDO Manager ---
        if (!$this->hasTable('ms3_option_groups')) {
            require_once __DIR__ . '/_modx.php';
            $modx = ms3MigrationBootstrap();
            if ($modx === null) {
                $this->output->writeln('<error>Cannot bootstrap MODX, aborting migration</error>');
                return;
            }
            $manager = $modx->getManager();
            $created = $manager->createObjectContainer(\MiniShop3\Model\msOptionGroup::class);
            if (!$created) {
                $this->output->writeln('<error>Failed to create table ' . $optionGroupsFqn . '</error>');
                return;
            }
            $this->output->writeln('<info>Created table ' . $optionGroupsFqn . '</info>');
        } else {
            $this->output->writeln('<comment>Table ' . $optionGroupsFqn . ' already exists, skipping create</comment>');
        }

        if (!$this->hasTable('ms3_options')) {
            $this->output->writeln('<comment>Table ' . $optionsFqn . ' does not exist, skipping column/data migration</comment>');
            return;
        }

        // --- Step 2: add option_group_id column to ms3_options ---
        $optionsTable = $this->table('ms3_options');
        if (!$optionsTable->hasColumn('option_group_id')) {
            $optionsTable
                ->addColumn('option_group_id', 'integer', [
                    'null' => true,
                    'default' => null,
                    'signed' => false,
                    'after' => 'measure_unit',
                ])
                ->addIndex('option_group_id', ['name' => 'option_group_id'])
                ->update();
            $this->output->writeln('<info>Added column option_group_id to ' . $optionsFqn . '</info>');
        } else {
            $this->output->writeln('<comment>Column option_group_id already exists in ' . $optionsFqn . ', skipping add</comment>');
        }

        // --- Step 3: migrate data from modcategory_id ---
        // Only run while modcategory_id still exists; on re-run after step 4 this becomes a no-op.
        $optionsTable = $this->table('ms3_options'); // refresh handle after schema change
        if ($optionsTable->hasColumn('modcategory_id')) {
            $this->migrateGroupData($prefix, $optionsFqn, $optionGroupsFqn);

            // --- Step 4: drop modcategory_id ---
            $optionsTable = $this->table('ms3_options'); // refresh handle
            if ($optionsTable->hasIndex('modcategory_id')) {
                $optionsTable->removeIndexByName('modcategory_id')->update();
            }
            $this->table('ms3_options')
                ->removeColumn('modcategory_id')
                ->update();
            $this->output->writeln('<info>Dropped column modcategory_id from ' . $optionsFqn . '</info>');
        } else {
            $this->output->writeln('<comment>Column modcategory_id already removed, skipping data migration + drop</comment>');
        }
    }

    public function down(): void
    {
        if (!$this->hasTable('ms3_options')) {
            return;
        }

        $optionsTable = $this->table('ms3_options');

        // Restore modcategory_id (data NOT restored — irreversible by design)
        if (!$optionsTable->hasColumn('modcategory_id')) {
            $optionsTable
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
        $optionsTable = $this->table('ms3_options');
        if ($optionsTable->hasIndex('option_group_id')) {
            $optionsTable->removeIndexByName('option_group_id')->update();
        }
        if ($this->table('ms3_options')->hasColumn('option_group_id')) {
            $this->table('ms3_options')->removeColumn('option_group_id')->update();
        }

        // Drop ms3_option_groups table
        if ($this->hasTable('ms3_option_groups')) {
            $this->table('ms3_option_groups')->drop()->update();
        }
    }

    /**
     * Read distinct (modcategory_id, name) pairs from modCategory and create matching msOptionGroup rows.
     * Then UPDATE ms3_options.option_group_id by old → new id mapping.
     */
    private function migrateGroupData(
        string $modxPrefix,
        string $optionsFqn,
        string $optionGroupsFqn
    ): void {
        // Already migrated? (re-run safety)
        $existingGroups = $this->fetchRow("SELECT COUNT(*) AS cnt FROM `{$optionGroupsFqn}`");
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
            FROM `{$optionsFqn}` o
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
                "INSERT INTO `{$optionGroupsFqn}` (name, sort_order, created_at) VALUES (:name, :sort_order, :created_at)"
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
                "UPDATE `{$optionsFqn}` SET option_group_id = :new_id WHERE modcategory_id = :old_id"
            );
            $stmt->execute([
                ':new_id' => $newId,
                ':old_id' => $oldId,
            ]);
        }

        $this->output->writeln('<info>Updated option_group_id for migrated options</info>');
    }
}
