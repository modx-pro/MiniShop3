<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Inventory;

use MiniShop3\Services\Inventory\InventoryKey;
use MiniShop3\Services\Inventory\OrderInventoryCoordinator;
use MiniShop3\Services\Inventory\PdoInventoryStockStore;
use MiniShop3\Services\Inventory\ProductStockInventory;
use MiniShop3\Services\Order\NullOrderLifecyclePorts;
use MiniShop3\Services\Order\OrderStatusService;
use MiniShop3\Tests\RecordingMsOrder;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Status persist and the inventory store must share $modx->pdo.
 * Passing modX itself nests beginTransaction() and throws (#603 review).
 */
final class PdoInventoryNestedTransactionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\MODX\Revolution\modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
        require_once dirname(__DIR__, 3) . '/RecordingMsOrder.php';
    }

    public function testRunInTransactionJoinsOpenPdoTransaction(): void
    {
        $pdo = $this->pdo();
        $store = new PdoInventoryStockStore($pdo, 'products', 'reservations');
        $pdo->beginTransaction();

        $store->runInTransaction(static function (): void {
        });

        self::assertTrue($pdo->inTransaction());
        $pdo->rollBack();
    }

    public function testStatusPersistReservesInsideTheSamePdoTransaction(): void
    {
        $pdo = $this->pdo();
        $this->sql($pdo, 'CREATE TABLE products (id INTEGER PRIMARY KEY, stock REAL NOT NULL)');
        $this->sql($pdo, 'CREATE TABLE reservations (
            order_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            qty REAL NOT NULL,
            state TEXT NOT NULL,
            createdon INTEGER,
            updatedon INTEGER,
            UNIQUE (order_id, product_id)
        )');
        $this->sql($pdo, 'INSERT INTO products (id, stock) VALUES (7, 10)');

        $store = new PdoInventoryStockStore($pdo, 'products', 'reservations');
        $modx = $this->modx($pdo);
        $coordinator = new OrderInventoryCoordinator($modx, new ProductStockInventory($store));
        $service = (new ReflectionClass(OrderStatusService::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(OrderStatusService::class, 'modx'))->setValue($service, $modx);
        (new \ReflectionProperty(OrderStatusService::class, 'inventoryCoordinator'))->setValue($service, $coordinator);
        (new \ReflectionProperty(OrderStatusService::class, 'lifecyclePorts'))->setValue(
            $service,
            new NullOrderLifecyclePorts()
        );

        $order = new RecordingMsOrder(['id' => 40, 'status_id' => 1]);
        $order->products = [new class {
            public function get($key)
            {
                return match ($key) {
                    'product_id' => 7,
                    'count' => 2,
                    default => null,
                };
            }
        }];

        $persist = new \ReflectionMethod(OrderStatusService::class, 'persistStatusWithInventory');
        $error = $persist->invoke($service, $order, 2, 1);

        self::assertNull($error);
        self::assertFalse($pdo->inTransaction());
        self::assertSame(2, $order->get('status_id'));
        self::assertSame(8.0, (new ProductStockInventory($store))->getAvailable(new InventoryKey(7)));
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private function sql(PDO $pdo, string $statement): void
    {
        $pdo->query($statement);
    }

    private function modx(PDO $pdo): \MODX\Revolution\modX
    {
        return new class ($pdo) extends \MODX\Revolution\modX {
            public function __construct(public PDO $pdo)
            {
                parent::__construct();
            }

            public function beginTransaction(): void
            {
                $this->pdo->beginTransaction();
            }

            public function commit(): void
            {
                $this->pdo->commit();
            }

            public function rollBack(): void
            {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
            }

            public function getOption(string $key, $options = null, $default = null)
            {
                return match ($key) {
                    'ms3_inventory_enabled' => true,
                    'ms3_status_new' => 2,
                    'ms3_status_paid' => 3,
                    'ms3_status_canceled' => 5,
                    default => $default,
                };
            }

            public function lexicon(string $key, array $params = []): string
            {
                return $key;
            }
        };
    }
}
