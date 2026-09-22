<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Inventory;

use MiniShop3\Model\msOrder;
use MiniShop3\Services\Inventory\InventoryContext;
use MiniShop3\Services\Inventory\InventoryException;
use MiniShop3\Services\Inventory\InventoryKey;
use MiniShop3\Services\Inventory\InventoryServiceInterface;
use MiniShop3\Tests\Support\InMemoryInventoryStockStore;
use MiniShop3\Services\Inventory\OrderInventoryCoordinator;
use MiniShop3\Services\Inventory\ProductStockInventory;
use MiniShop3\Tests\RecordingMsOrder;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class OrderInventoryCoordinatorTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
        require_once dirname(__DIR__, 3) . '/RecordingMsOrder.php';
    }

    public function testDisabledModeDoesNotTouchInventory(): void
    {
        $fake = new RecordingInventoryService();
        $coordinator = new OrderInventoryCoordinator($this->modx(false), $fake);
        $order = $this->order(7, 1);

        $coordinator->assertOrderAvailable($order);
        $coordinator->applyStatusChange($order, 2);
        $coordinator->releaseOrder($order);

        self::assertSame([], $fake->ops);
    }

    public function testReserveCommitReleaseLifecycle(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 5);
        $inventory = new ProductStockInventory($store);
        $coordinator = new OrderInventoryCoordinator($this->modx(true), $inventory);
        $order = $this->order(7, 2);

        $coordinator->assertOrderAvailable($order);
        $coordinator->applyStatusChange($order, 2);
        self::assertSame(3.0, $inventory->getAvailable(new InventoryKey(7)));

        $coordinator->applyStatusChange($order, 3);
        self::assertSame(3.0, $inventory->getAvailable(new InventoryKey(7)));

        $coordinator->applyStatusChange($order, 5);
        self::assertSame(3.0, $inventory->getAvailable(new InventoryKey(7)));
    }

    public function testCancelBeforeCommitRestoresStock(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 5);
        $inventory = new ProductStockInventory($store);
        $coordinator = new OrderInventoryCoordinator($this->modx(true), $inventory);
        $order = $this->order(7, 2);

        $coordinator->applyStatusChange($order, 2);
        $coordinator->applyStatusChange($order, 5);
        self::assertSame(5.0, $inventory->getAvailable(new InventoryKey(7)));
    }

    public function testSubmitRejectWhenStockMissing(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 1);
        $inventory = new ProductStockInventory($store);
        $coordinator = new OrderInventoryCoordinator($this->modx(true), $inventory);

        try {
            $coordinator->assertOrderAvailable($this->order(7, 2));
            self::fail('expected InventoryException');
        } catch (InventoryException) {
            self::assertSame(1.0, $inventory->getAvailable(new InventoryKey(7)));
        }
    }

    public function testPartialReserveRollback(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(1, 5);
        $store->seedStock(2, 0);
        $inventory = new ProductStockInventory($store);
        $coordinator = new OrderInventoryCoordinator($this->modx(true), $inventory);

        $order = new RecordingMsOrder(['id' => 50]);
        $order->products = [
            $this->line(1, 1),
            $this->line(2, 1),
        ];

        try {
            $coordinator->applyStatusChange($order, 2);
            self::fail('expected InventoryException');
        } catch (InventoryException) {
            self::assertSame(5.0, $inventory->getAvailable(new InventoryKey(1)));
            self::assertSame(0.0, $inventory->getAvailable(new InventoryKey(2)));
        }
    }

    public function testPartialReserveDoesNotEmitAfterEvents(): void
    {
        $events = [];
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(1, 5);
        $store->seedStock(2, 0);
        $inventory = new ProductStockInventory($store, $this->ms3RecordingEvents($events));
        $coordinator = new OrderInventoryCoordinator($this->modx(true), $inventory);

        $order = new RecordingMsOrder(['id' => 51]);
        $order->products = [
            $this->line(1, 1),
            $this->line(2, 1),
        ];

        try {
            $coordinator->applyStatusChange($order, 2);
            self::fail('expected InventoryException');
        } catch (InventoryException) {
            self::assertSame(
                [
                    'msOnBeforeInventoryReserve',
                    'msOnBeforeInventoryReserve',
                ],
                $events,
                'after-reserve must not fire for a batch that rolled back (#762)'
            );
            self::assertNotContains('msOnInventoryReserve', $events);
        }
    }

    public function testSuccessfulMultiLineReserveEmitsAfterEventsOncePerLine(): void
    {
        $events = [];
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(1, 5);
        $store->seedStock(2, 5);
        $inventory = new ProductStockInventory($store, $this->ms3RecordingEvents($events));
        $coordinator = new OrderInventoryCoordinator($this->modx(true), $inventory);

        $order = new RecordingMsOrder(['id' => 52]);
        $order->products = [
            $this->line(1, 1),
            $this->line(2, 1),
        ];

        $coordinator->applyStatusChange($order, 2);

        self::assertSame(
            [
                'msOnBeforeInventoryReserve',
                'msOnBeforeInventoryReserve',
                'msOnInventoryReserve',
                'msOnInventoryReserve',
            ],
            $events
        );
    }

    public function testCompensateUnpersistedNewReleasesWithoutNotify(): void
    {
        $fake = new RecordingInventoryService();
        $coordinator = new OrderInventoryCoordinator($this->modx(true), $fake);
        $order = $this->order(7, 2);
        $order->set('status_id', 1);

        $fake->ops = [];
        $coordinator->compensateUnpersistedChange($order, 2);

        self::assertSame(['release'], $fake->ops);
        self::assertSame([false], $fake->releaseNotify);
    }

    public function testCompensateUnpersistedCancelRestoresNewHoldSilently(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 5);
        $events = [];
        $inventory = new ProductStockInventory($store, $this->ms3RecordingEvents($events));
        $coordinator = new OrderInventoryCoordinator($this->modx(true), $inventory);
        $order = $this->order(7, 2);

        $coordinator->applyStatusChange($order, 2);
        self::assertSame(3.0, $inventory->getAvailable(new InventoryKey(7)));

        $order->set('status_id', 2);
        $coordinator->applyStatusChange($order, 5);
        self::assertSame(5.0, $inventory->getAvailable(new InventoryKey(7)));

        array_splice($events, 0);
        // Simulate OrderStatusService restore: previous status New after failed cancel persist.
        $order->set('status_id', 2);
        $coordinator->compensateUnpersistedChange($order, 5);

        self::assertSame(3.0, $inventory->getAvailable(new InventoryKey(7)));
        self::assertSame([], $events, 'compensation must not notify inside an open foreign TX (#762)');
    }

    public function testCompensateUnpersistedCancelFromNonNewIsNoOp(): void
    {
        $fake = new RecordingInventoryService();
        $coordinator = new OrderInventoryCoordinator($this->modx(true), $fake);
        $order = $this->order(7, 2);
        $order->set('status_id', 4);

        $coordinator->compensateUnpersistedChange($order, 5);

        self::assertSame([], $fake->ops);
    }

    public function testPaidStatusDoesNotReleaseStock(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 5);
        $inventory = new ProductStockInventory($store);
        $coordinator = new OrderInventoryCoordinator($this->modx(true), $inventory);
        $order = $this->order(7, 2);
        $order->set('status_id', 3);

        $coordinator->applyStatusChange($order, 2);
        self::assertSame(3.0, $inventory->getAvailable(new InventoryKey(7)));
        $coordinator->applyStatusChange($order, 5);
        self::assertSame(3.0, $inventory->getAvailable(new InventoryKey(7)));
    }

    public function testReplacementInventoryReceivesReserveCommit(): void
    {
        $fake = new RecordingInventoryService();
        $coordinator = new OrderInventoryCoordinator($this->modx(true), $fake);
        $order = $this->order(9, 3);

        $coordinator->assertOrderAvailable($order);
        $coordinator->applyStatusChange($order, 2);
        $coordinator->applyStatusChange($order, 3);

        self::assertSame(['assert', 'reserve', 'commit'], $fake->ops);
    }

    private function modx(bool $enabled): modX
    {
        return new class ($enabled) extends modX {
            public function __construct(private bool $enabled)
            {
                parent::__construct();
            }

            public function getOption(string $key, $options = null, $default = null)
            {
                return match ($key) {
                    'ms3_inventory_enabled' => $this->enabled,
                    'ms3_status_new' => 2,
                    'ms3_status_paid' => 3,
                    'ms3_status_canceled' => 5,
                    default => $default,
                };
            }
        };
    }

    /**
     * @param list<string> $events
     */
    private function ms3RecordingEvents(array &$events): \MiniShop3\MiniShop3
    {
        $ms3 = $this->getMockBuilder(\MiniShop3\MiniShop3::class)
            ->disableOriginalConstructor()
            ->getMock();
        $ms3->utils = new class ($events) {
            /** @param list<string> $events */
            public function __construct(private array &$events)
            {
            }

            public function invokeEvent(string $eventName, array $params = []): array
            {
                $this->events[] = $eventName;

                return ['success' => true, 'message' => '', 'data' => $params];
            }
        };

        return $ms3;
    }

    private function order(int $productId, float $qty): msOrder
    {
        $order = new RecordingMsOrder(['id' => 40]);
        $order->products = [$this->line($productId, $qty)];

        return $order;
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

final class RecordingInventoryService implements InventoryServiceInterface
{
    /** @var list<string> */
    public array $ops = [];

    /** @var list<bool> */
    public array $releaseNotify = [];

    /** @var list<bool> */
    public array $reserveNotify = [];

    public function getAvailable(InventoryKey $key): float
    {
        return 99;
    }

    public function assertAvailable(InventoryKey $key, float $qty): void
    {
        $this->ops[] = 'assert';
    }

    public function reserve(InventoryKey $key, float $qty, InventoryContext $ctx, bool $notify = true): void
    {
        $this->ops[] = 'reserve';
        $this->reserveNotify[] = $notify;
    }

    public function release(InventoryKey $key, float $qty, InventoryContext $ctx, bool $notify = true): void
    {
        $this->ops[] = 'release';
        $this->releaseNotify[] = $notify;
    }

    public function commit(InventoryKey $key, float $qty, InventoryContext $ctx): void
    {
        $this->ops[] = 'commit';
    }
}
