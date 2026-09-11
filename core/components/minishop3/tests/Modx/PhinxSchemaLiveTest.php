<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx;

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msGridField;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Model\msPayment;
use MiniShop3\Tests\Modx\Support\ExtraTestCase;
use MiniShop3\Tests\Modx\Support\PackageModels;
use MiniShop3\Tests\Modx\Support\PhinxSchemaBootstrap;

/**
 * Asserts the live suite schema comes from Phinx migrations (#695), not PackageDefinition::tables().
 *
 * ExtraTestCase already migrated once per process before setUp(); these tests check outcomes.
 */
final class PhinxSchemaLiveTest extends ExtraTestCase
{
    /**
     * Phinx metadata table is not an xPDO model — expected DB-only table.
     *
     * @var list<string> logical (unprefixed) names
     */
    private const DB_ONLY_TABLES = [
        'ms3_migrations',
    ];

    public function testTablePrefixIsNonEmpty(): void
    {
        $prefix = (string) $this->modx->getOption('table_prefix', null, '');
        self::assertNotSame('', $prefix, 'testbench must use a non-empty table prefix (#276)');
    }

    public function testSeedOrderStatusesExist(): void
    {
        $expected = [
            'ms3_order_status_draft',
            'ms3_order_status_new',
            'ms3_order_status_paid',
            'ms3_order_status_sent',
            'ms3_order_status_cancelled',
        ];
        foreach ($expected as $name) {
            self::assertGreaterThanOrEqual(
                1,
                $this->modx->getCount(msOrderStatus::class, ['name' => $name]),
                'missing seeded status ' . $name
            );
        }
    }

    public function testSeedDeliveryAndPaymentExist(): void
    {
        self::assertGreaterThanOrEqual(1, $this->modx->getCount(msDelivery::class));
        self::assertGreaterThanOrEqual(1, $this->modx->getCount(msPayment::class));
    }

    public function testSeedGridConfigsExist(): void
    {
        foreach (['orders', 'customers', 'deliveries', 'vendors', 'order_products', 'category-products'] as $gridKey) {
            self::assertGreaterThanOrEqual(
                1,
                $this->modx->getCount(msGridField::class, ['grid_key' => $gridKey]),
                'missing grid config rows for ' . $gridKey
            );
        }
    }

    public function testSchemaMatchesXpdoMapBidirectionally(): void
    {
        $prefix = (string) $this->modx->getOption('table_prefix', null, '');
        // PackageModels lists dedicated ms3_* tables only. msProduct/msCategory extend
        // modResource (site_content) and are checked via SchemaTablesTest / persist tests.
        $mappedTables = [];
        foreach (PackageModels::tables() as $class) {
            $quoted = $this->modx->getTableName($class);
            self::assertNotFalse($quoted, 'no table for ' . $class);
            $table = str_replace('`', '', (string) $quoted);
            $mappedTables[$table] = $class;

            // Compare against the generated mysql map, not runtime $modx->map (ExtraFields::loadMap
            // can leave process-local fields after ExtraFieldMapTest).
            $mysqlClass = str_replace('\\Model\\', '\\Model\\mysql\\', $class);
            self::assertTrue(class_exists($mysqlClass), 'missing mysql map class ' . $mysqlClass);
            /** @var array{fields?: array<string, mixed>} $metaMap */
            $metaMap = $mysqlClass::$metaMap;
            $mapFields = array_keys($metaMap['fields'] ?? []);
            // PK comes from xPDOSimpleObject; generated maps omit it from fields.
            if (!in_array('id', $mapFields, true)) {
                $mapFields[] = 'id';
            }
            $dbColumns = $this->listColumns($table);

            self::assertSame(
                [],
                array_values(array_diff($mapFields, $dbColumns)),
                $class . ' map fields missing in ' . $table
            );
            self::assertSame(
                [],
                array_values(array_diff($dbColumns, $mapFields)),
                $table . ' has columns absent from ' . $class . ' map'
            );
        }

        $allowedExtra = array_map(
            static fn (string $logical): string => $prefix . $logical,
            self::DB_ONLY_TABLES
        );
        $unexpected = array_values(array_diff(
            $this->listPrefixedMs3Tables($prefix),
            array_keys($mappedTables),
            $allowedExtra
        ));

        self::assertSame([], $unexpected, 'ms3 tables present in DB but not in xPDO map allowlist');
    }

    public function testSecondMigrateIsIdempotent(): void
    {
        $seedClasses = [msOrderStatus::class, msDelivery::class, msPayment::class, msGridField::class];
        $before = [];
        foreach ($seedClasses as $class) {
            $before[$class] = $this->modx->getCount($class);
        }

        PhinxSchemaBootstrap::migrate($this->modx, $this->extraCorePath());

        foreach ($seedClasses as $class) {
            self::assertSame($before[$class], $this->modx->getCount($class), $class);
        }
    }

    /**
     * @return list<string>
     */
    private function listColumns(string $table): array
    {
        $statement = $this->modx->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
        self::assertNotFalse($statement);
        $columns = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $columns[] = (string) $row['Field'];
        }

        return $columns;
    }

    /**
     * @return list<string>
     */
    private function listPrefixedMs3Tables(string $prefix): array
    {
        $like = $prefix . 'ms3\_%';
        $statement = $this->modx->prepare('SHOW TABLES LIKE ?');
        $statement->execute([$like]);
        $tables = [];
        while ($row = $statement->fetch(\PDO::FETCH_NUM)) {
            $tables[] = (string) $row[0];
        }

        return $tables;
    }
}
