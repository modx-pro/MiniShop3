<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\Mysql;

use MiniShop3\Services\Shipment\PdoShipmentStore;
use MiniShop3\Tests\Support\MysqlTestConnection;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * PdoShipmentStore against MySQL (CI service).
 */
#[Group('mysql')]
final class PdoShipmentStoreMysqlTest extends TestCase
{
    private PDO $pdo;

    private string $shipmentsTable;

    private string $eventsTable;

    private PdoShipmentStore $store;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/support/MysqlTestConnection.php';

        $this->pdo = MysqlTestConnection::requireOrSkip($this);
        $suffix = bin2hex(random_bytes(4));
        $this->shipmentsTable = 'ms3_test_shipments_' . $suffix;
        $this->eventsTable = 'ms3_test_shipment_events_' . $suffix;
        $this->createTables();
        $this->store = new PdoShipmentStore($this->pdo, $this->shipmentsTable, $this->eventsTable);
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return;
        }
        $this->dropTable($this->eventsTable);
        $this->dropTable($this->shipmentsTable);
    }

    public function testCreateFindAndDuplicateOrderId(): void
    {
        $first = $this->store->create(10, 7, 'preparing', 'Cdek', ['city' => 'MSK']);
        $again = $this->store->create(10, 8, 'shipped', 'Other');
        $byOrder = $this->store->findByOrderId(10);
        $byId = $this->store->findById($first['id']);

        self::assertSame($first['id'], $again['id']);
        self::assertSame(7, $again['delivery_id']);
        self::assertSame($first['id'], $byOrder['id'] ?? null);
        self::assertSame('MSK', $byId['meta']['city'] ?? null);
        self::assertSame('preparing', $byId['status'] ?? null);
    }

    public function testUpdateAndExternalIdLookup(): void
    {
        $row = $this->store->create(11, 7, 'preparing', 'Cdek');
        $updated = $this->store->update($row['id'], [
            'status' => 'shipped',
            'tracking_number' => 'TRACK-1',
            'external_id' => 'cdek-11',
            'carrier' => 'CDEK',
        ]);
        $byExternal = $this->store->findByExternalId('Cdek', 'cdek-11', 7);

        self::assertSame('shipped', $updated['status']);
        self::assertSame('TRACK-1', $updated['tracking_number']);
        self::assertSame($row['id'], $byExternal['id'] ?? null);
        self::assertNull($this->store->findByExternalId('Cdek', 'cdek-11', 8));
    }

    public function testEventHistoryIsUniquePerShipment(): void
    {
        $row = $this->store->create(12, 7, 'preparing', 'Cdek');
        self::assertFalse($this->store->hasEvent($row['id'], 'evt-a'));
        $this->store->recordEvent($row['id'], 'evt-a');
        $this->store->recordEvent($row['id'], 'evt-a');
        $this->store->recordEvent($row['id'], 'evt-b');

        self::assertTrue($this->store->hasEvent($row['id'], 'evt-a'));
        self::assertTrue($this->store->hasEvent($row['id'], 'evt-b'));
        self::assertFalse($this->store->hasEvent($row['id'], 'evt-c'));

        $count = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM `' . $this->eventsTable . '` WHERE shipment_id = ' . (int) $row['id']
        )->fetchColumn();
        self::assertSame(2, $count);
    }

    private function dropTable(string $table): void
    {
        $this->pdo->query('DROP TABLE IF EXISTS `' . $table . '`');
    }

    private function createTables(): void
    {
        $shipments = $this->shipmentsTable;
        $events = $this->eventsTable;
        $this->pdo->query(
            "CREATE TABLE `{$shipments}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_id` INT UNSIGNED NOT NULL,
                `delivery_id` INT UNSIGNED NOT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT 'preparing',
                `tracking_number` VARCHAR(191) NULL DEFAULT NULL,
                `external_id` VARCHAR(191) NULL DEFAULT NULL,
                `provider` VARCHAR(191) NULL DEFAULT NULL,
                `carrier` VARCHAR(191) NULL DEFAULT NULL,
                `shipped_at` INT UNSIGNED NULL DEFAULT NULL,
                `delivered_at` INT UNSIGNED NULL DEFAULT NULL,
                `last_event_id` VARCHAR(191) NULL DEFAULT NULL,
                `meta` TEXT NULL,
                `createdon` INT UNSIGNED NULL DEFAULT NULL,
                `updatedon` INT UNSIGNED NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_shipment_order` (`order_id`),
                UNIQUE KEY `uniq_shipment_external` (`delivery_id`, `provider`, `external_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->pdo->query(
            "CREATE TABLE `{$events}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `shipment_id` INT UNSIGNED NOT NULL,
                `provider_event_id` VARCHAR(191) NOT NULL,
                `createdon` INT UNSIGNED NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_shipment_provider_event` (`shipment_id`, `provider_event_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
