<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\Mysql;

use MiniShop3\Services\Inventory\InventoryContext;
use MiniShop3\Services\Inventory\InventoryException;
use MiniShop3\Services\Inventory\InventoryKey;
use MiniShop3\Services\Inventory\PdoInventoryStockStore;
use MiniShop3\Services\Inventory\ProductStockInventory;
use MiniShop3\Tests\Support\MysqlTestConnection;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Atomic decrement against MySQL: the second checkout of the last unit must fail.
 */
#[Group('mysql')]
final class InventoryStockConcurrentMysqlTest extends TestCase
{
    private PDO $pdo;

    private string $productsTable;

    private string $reservationsTable;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/support/MysqlTestConnection.php';

        $this->pdo = MysqlTestConnection::requireOrSkip($this);
        $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
        $this->productsTable = 'ms3_inv_products_' . $suffix;
        $this->reservationsTable = 'ms3_inv_res_' . $suffix;

        $this->pdo->query(
            "CREATE TABLE `{$this->productsTable}` (
                id INT UNSIGNED NOT NULL PRIMARY KEY,
                stock DECIMAL(13,3) NOT NULL DEFAULT 0
            ) ENGINE=InnoDB"
        );
        $this->pdo->query(
            "CREATE TABLE `{$this->reservationsTable}` (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                order_id INT UNSIGNED NOT NULL,
                product_id INT UNSIGNED NOT NULL,
                qty DECIMAL(13,3) NOT NULL DEFAULT 0,
                state VARCHAR(16) NOT NULL DEFAULT 'reserved',
                createdon INT UNSIGNED NULL,
                updatedon INT UNSIGNED NULL,
                UNIQUE KEY order_product (order_id, product_id)
            ) ENGINE=InnoDB"
        );
        $this->pdo->query("INSERT INTO `{$this->productsTable}` (id, stock) VALUES (15, 1.000)");
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return;
        }
        $this->pdo->query("DROP TABLE IF EXISTS `{$this->reservationsTable}`");
        $this->pdo->query("DROP TABLE IF EXISTS `{$this->productsTable}`");
    }

    public function testSecondReserveOfLastUnitFailsAndStockStaysAtZero(): void
    {
        $store = new PdoInventoryStockStore($this->pdo, $this->productsTable, $this->reservationsTable);
        $inventory = new ProductStockInventory($store);
        $key = new InventoryKey(15);

        $inventory->reserve($key, 1, new InventoryContext(1));

        try {
            $inventory->reserve($key, 1, new InventoryContext(2));
            self::fail('second reserve must fail');
        } catch (InventoryException) {
            self::assertSame(0.0, $inventory->getAvailable($key));
        }
    }

    public function testSecondConnectionCannotTakeLockedLastUnit(): void
    {
        $pdoA = $this->pdo;
        $pdoB = MysqlTestConnection::connect();
        $pdoB->query('SET SESSION innodb_lock_wait_timeout = 1');

        $sql = "UPDATE `{$this->productsTable}` SET stock = stock - 1 WHERE id = 15 AND stock >= 1";

        $pdoA->beginTransaction();
        $affectedA = $pdoA->query($sql);
        self::assertNotFalse($affectedA);
        self::assertSame(1, $affectedA->rowCount());

        $pdoB->beginTransaction();
        $blockedOrEmpty = false;
        try {
            $affectedB = $pdoB->query($sql);
            $blockedOrEmpty = $affectedB === false || $affectedB->rowCount() === 0;
        } catch (\PDOException) {
            $blockedOrEmpty = true;
        }
        self::assertTrue($blockedOrEmpty, 'second connection must not decrement the locked last unit');

        $pdoA->commit();
        if ($pdoB->inTransaction()) {
            $pdoB->rollBack();
        }

        $stock = $pdoA->query("SELECT stock FROM `{$this->productsTable}` WHERE id = 15");
        self::assertNotFalse($stock);
        self::assertSame(0.0, (float) $stock->fetchColumn());
    }
}
