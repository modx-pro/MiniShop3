<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\Mysql;

use MiniShop3\Services\Payment\PdoPaymentAttemptStore;
use MiniShop3\Tests\Support\MysqlTestConnection;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * PdoPaymentAttemptStore against MySQL (CI service).
 */
#[Group('mysql')]
final class PdoPaymentAttemptStoreMysqlTest extends TestCase
{
    private PDO $pdo;

    private string $attemptsTable;

    private string $eventsTable;

    private PdoPaymentAttemptStore $store;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/support/MysqlTestConnection.php';

        $this->pdo = MysqlTestConnection::requireOrSkip($this);
        $suffix = bin2hex(random_bytes(4));
        $this->attemptsTable = 'ms3_test_payment_attempts_' . $suffix;
        $this->eventsTable = 'ms3_test_payment_events_' . $suffix;
        $this->createTables();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $this->store = new PdoPaymentAttemptStore($this->pdo, $this->attemptsTable, $this->eventsTable);
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return;
        }
        $this->dropTable($this->eventsTable);
        $this->dropTable($this->attemptsTable);
    }

    public function testUpdateFailureIsReportedUnderSilentErrMode(): void
    {
        self::assertSame(PDO::ERRMODE_SILENT, (int) $this->pdo->getAttribute(PDO::ATTR_ERRMODE));
        $row = $this->createAttempt('update-failure');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payment attempt store statement failed');

        $this->store->update($row['id'], ['currency' => str_repeat('X', 40)]);
    }

    public function testDuplicateCreateReturnsTheExistingAttemptUnderSilentErrMode(): void
    {
        $first = $this->createAttempt('duplicate');
        $this->createAttempt('newer-attempt');

        $duplicate = $this->createAttempt('duplicate');

        self::assertSame($first['id'], $duplicate['id']);
        self::assertSame($first['order_id'], $duplicate['order_id']);
    }

    public function testCreateFailureIncludesSqlErrorUnderSilentErrMode(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payment attempt store statement failed');

        $this->store->create(1, 2, 'Test', 'create-failure', 'new', 10.0, str_repeat('X', 40), []);
    }

    public function testFetchFailureIsReportedUnderSilentErrMode(): void
    {
        $store = new PdoPaymentAttemptStore($this->pdo, 'missing_attempts_table', $this->eventsTable);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payment attempt store statement failed');

        $store->findById(1);
    }

    /**
     * @return array<string, mixed>
     */
    private function createAttempt(string $externalId): array
    {
        static $orderId = 0;
        $orderId++;

        return $this->store->create($orderId, 2, 'Test', $externalId, 'new', 10.0, 'RUB', []);
    }

    private function dropTable(string $table): void
    {
        $this->pdo->query('DROP TABLE IF EXISTS `' . $table . '`');
    }

    private function createTables(): void
    {
        $attempts = $this->attemptsTable;
        $events = $this->eventsTable;
        $this->pdo->query(
            "CREATE TABLE `{$attempts}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_id` INT UNSIGNED NOT NULL,
                `payment_method_id` INT UNSIGNED NOT NULL,
                `provider` VARCHAR(191) NOT NULL,
                `external_id` VARCHAR(191) NULL DEFAULT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
                `amount` DECIMAL(13,3) NOT NULL DEFAULT 0.000,
                `currency` VARCHAR(8) NOT NULL DEFAULT 'RUB',
                `payload` TEXT NULL,
                `refunded_amount` DECIMAL(13,3) NOT NULL DEFAULT 0.000,
                `refund_external_id` VARCHAR(191) NULL DEFAULT NULL,
                `refundedon` INT UNSIGNED NULL DEFAULT NULL,
                `createdon` INT UNSIGNED NULL DEFAULT NULL,
                `updatedon` INT UNSIGNED NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_payment_attempt_external` (`payment_method_id`, `provider`, `external_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->pdo->query(
            "CREATE TABLE `{$events}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `attempt_id` INT UNSIGNED NOT NULL,
                `event_type` VARCHAR(32) NOT NULL,
                `provider_event_id` VARCHAR(191) NOT NULL,
                `createdon` INT UNSIGNED NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_payment_attempt_event` (`attempt_id`, `event_type`, `provider_event_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
