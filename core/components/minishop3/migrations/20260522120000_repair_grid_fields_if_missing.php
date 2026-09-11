<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Self-heal ms3_grid_fields when initial_schema or seed migrations did not populate defaults.
 *
 * Covers:
 * - table missing after a silent/partial initial_schema run (#270)
 * - empty grid configs after seed migrations no-op'd (double-prefix bug in #271, fixed in #276)
 *
 * Only re-seeds grid keys that are empty; patch migrations run only for the affected grid.
 */
final class RepairGridFieldsIfMissing extends AbstractMigration
{
    /**
     * grid_key => seed migration + optional follow-up patches for that grid only.
     *
     * @var array<string, array{seed: array{class: string, file: string}, patches?: list<array{class: string, file: string}>}>
     */
    private const GRID_REPAIR_PLAN = [
        'customers' => [
            'seed' => ['class' => 'SeedCustomersGridConfig', 'file' => '20251127000002_seed_customers_grid_config.php'],
        ],
        'orders' => [
            'seed' => ['class' => 'SeedOrdersGridConfig', 'file' => '20251204140000_seed_orders_grid_config.php'],
            'patches' => [
                ['class' => 'FixOrdersGridFilterable', 'file' => '20260107120000_fix_orders_grid_filterable.php'],
                ['class' => 'UpdateOrdersGridStatusFields', 'file' => '20260119220000_update_orders_grid_status_fields.php'],
            ],
        ],
        'order_products' => [
            'seed' => ['class' => 'SeedOrderProductsGridConfig', 'file' => '20251215120000_seed_order_products_grid_config.php'],
        ],
        'deliveries' => [
            'seed' => ['class' => 'SeedDeliveriesGridConfig', 'file' => '20251222120000_seed_deliveries_grid_config.php'],
        ],
        'vendors' => [
            'seed' => ['class' => 'SeedVendorsGridConfig', 'file' => '20251223130000_seed_vendors_grid_config.php'],
        ],
        'category-products' => [
            'seed' => ['class' => 'SeedCategoryProductsGridConfig', 'file' => '20251231140000_seed_category_products_grid_config.php'],
            'patches' => [
                ['class' => 'AddDuplicatePublishToCategoryProductsActions', 'file' => '20260317120000_add_duplicate_publish_to_category_products_actions.php'],
            ],
        ],
    ];

    private string $prefix = '';

    public function up(): void
    {
        $this->prefix = (string) ($this->getAdapter()->getOption('table_prefix') ?? '');

        $emptyGridKeys = $this->findEmptyGridKeys();
        if ($emptyGridKeys === []) {
            return;
        }

        if (!$this->hasTable('ms3_grid_fields')) {
            $this->ensureGridFieldsTable();
        }

        if (!$this->hasTable('ms3_grid_fields')) {
            throw new \RuntimeException('ms3_grid_fields is still missing after repair attempt');
        }

        $this->loadMigrationFilesForGridKeys($emptyGridKeys);

        foreach ($emptyGridKeys as $gridKey) {
            $plan = self::GRID_REPAIR_PLAN[$gridKey];
            $this->runChildMigration($plan['seed']['class'], $plan['seed']['file']);

            foreach ($plan['patches'] ?? [] as $patch) {
                $this->runChildMigration($patch['class'], $patch['file']);
            }
        }
    }

    public function down(): void
    {
        // Repair is data correction; rolling back would remove restored defaults.
    }

    /**
     * @return list<string>
     */
    private function findEmptyGridKeys(): array
    {
        if (!$this->hasTable('ms3_grid_fields')) {
            return array_keys(self::GRID_REPAIR_PLAN);
        }

        $emptyGridKeys = [];
        foreach (array_keys(self::GRID_REPAIR_PLAN) as $gridKey) {
            if ($this->countGridRows($gridKey) === 0) {
                $emptyGridKeys[] = $gridKey;
            }
        }

        return $emptyGridKeys;
    }

    private function ensureGridFieldsTable(): void
    {
        require_once __DIR__ . '/_modx.php';
        $modx = ms3MigrationBootstrap();
        if ($modx === null) {
            throw new \RuntimeException('MODX could not be bootstrapped for RepairGridFields migration');
        }

        $tableFqn = $modx->getTableName(\MiniShop3\Model\msGridField::class);
        $created = $modx->getManager()->createObjectContainer(\MiniShop3\Model\msGridField::class);
        if (!$created || !$this->hasTable('ms3_grid_fields')) {
            throw new \RuntimeException('Failed to create table ' . trim($tableFqn, '`'));
        }

        $this->output->writeln('<info>Created missing table ' . trim($tableFqn, '`') . '</info>');
    }

    private function countGridRows(string $gridKey): int
    {
        $quotedGridKey = $this->getAdapter()->getConnection()->quote($gridKey);
        $row = $this->fetchRow(
            "SELECT COUNT(*) AS cnt FROM {$this->prefix}ms3_grid_fields WHERE grid_key = {$quotedGridKey}"
        );

        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * @param list<string> $gridKeys
     */
    private function loadMigrationFilesForGridKeys(array $gridKeys): void
    {
        $migrationsDir = __DIR__ . DIRECTORY_SEPARATOR;
        $filesToLoad = [];

        foreach ($gridKeys as $gridKey) {
            $plan = self::GRID_REPAIR_PLAN[$gridKey];
            $filesToLoad[$plan['seed']['file']] = true;

            foreach ($plan['patches'] ?? [] as $patch) {
                $filesToLoad[$patch['file']] = true;
            }
        }

        foreach (array_keys($filesToLoad) as $fileName) {
            require_once $migrationsDir . $fileName;
        }
    }

    private function runChildMigration(string $migrationClass, string $migrationFile): void
    {
        $version = (int) substr($migrationFile, 0, 14);

        /** @var AbstractMigration $migration */
        $migration = new $migrationClass(
            $this->getEnvironment(),
            $version,
            null,
            $this->output
        );
        $migration->setAdapter($this->getAdapter());
        $migration->up();
    }

}
