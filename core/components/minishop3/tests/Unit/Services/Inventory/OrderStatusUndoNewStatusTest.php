<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Inventory;

use MiniShop3\Services\Inventory\InventoryKey;
use MiniShop3\Services\Inventory\OrderInventoryCoordinator;
use MiniShop3\Services\Inventory\ProductStockInventory;
use MiniShop3\Services\Order\OrderStatusService;
use MiniShop3\Tests\RecordingMsOrder;
use MiniShop3\Tests\Support\InMemoryInventoryStockStore;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OrderStatusUndoNewStatusTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
        require_once dirname(__DIR__, 3) . '/RecordingMsOrder.php';
    }

    public function testUndoNewStatusReleasesStockAndRestoresDraft(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 4);
        $inventory = new ProductStockInventory($store);
        $modx = $this->enabledModx();
        $coordinator = new OrderInventoryCoordinator($modx, $inventory);

        $order = new RecordingMsOrder(['id' => 40, 'status_id' => 2]);
        $order->products = [$this->line(7, 2)];
        $coordinator->applyStatusChange($order, 2);
        self::assertSame(2.0, $inventory->getAvailable(new InventoryKey(7)));

        $service = (new ReflectionClass(OrderStatusService::class))->newInstanceWithoutConstructor();
        $modxProp = new \ReflectionProperty(OrderStatusService::class, 'modx');
        $modxProp->setValue($service, $modx);
        $invProp = new \ReflectionProperty(OrderStatusService::class, 'inventoryCoordinator');
        $invProp->setValue($service, $coordinator);

        $undo = new \ReflectionMethod(OrderStatusService::class, 'undoUncommittedNewStatus');
        $undo->invoke($service, $order, 1, 2);

        self::assertSame(4.0, $inventory->getAvailable(new InventoryKey(7)));
        self::assertSame(1, $order->get('status_id'));
        self::assertTrue($order->saved);
    }

    private function enabledModx(): modX
    {
        return new class extends modX {
            public function getOption(string $key, $options = null, $default = null)
            {
                return match ($key) {
                    'ms3_inventory_enabled' => true,
                    'ms3_status_new' => 2,
                    'ms3_status_paid' => 3,
                    'ms3_status_canceled' => 5,
                    'ms3_status_draft' => 1,
                    default => $default,
                };
            }
        };
    }

    private function line(int $productId, float $qty): object
    {
        return new class ($productId, $qty) {
            public function __construct(private int $productId, private float $qty)
            {
            }

            public function get($key)
            {
                return match ($key) {
                    'product_id' => $this->productId,
                    'count' => $this->qty,
                    default => null,
                };
            }
        };
    }
}
