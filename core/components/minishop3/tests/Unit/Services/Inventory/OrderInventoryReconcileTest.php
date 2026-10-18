<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Inventory;

use MiniShop3\Services\Inventory\InventoryContext;
use MiniShop3\Services\Inventory\InventoryException;
use MiniShop3\Services\Inventory\InventoryKey;
use MiniShop3\Services\Inventory\InventoryReservationState;
use MiniShop3\Services\Inventory\InventoryServiceInterface;
use MiniShop3\Services\Inventory\InventoryStockStoreInterface;
use MiniShop3\Services\Inventory\OrderInventoryCoordinator;
use MiniShop3\Services\Inventory\ProductStockInventory;
use MiniShop3\Tests\RecordingMsOrder;
use MiniShop3\Tests\Support\InMemoryInventoryStockStore;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class OrderInventoryReconcileTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
        require_once dirname(__DIR__, 3) . '/RecordingMsOrder.php';
    }

    public function testIncreaseDecreaseAddAndDelete(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 10);
        $store->seedStock(8, 4);
        $inventory = new ProductStockInventory($store);
        $coordinator = new OrderInventoryCoordinator($this->modx(), $inventory);
        $order = $this->order(2);

        $coordinator->reconcile($order, [7 => 2]);
        self::assertSame(8.0, $inventory->getAvailable(new InventoryKey(7)));
        self::assertSame(InventoryReservationState::RESERVED, $store->findReservation(40, 7)['state']);
        self::assertSame(2.0, $store->findReservation(40, 7)['qty']);

        $coordinator->reconcile($order, [7 => 5]);
        self::assertSame(5.0, $inventory->getAvailable(new InventoryKey(7)));
        self::assertSame(5.0, $store->findReservation(40, 7)['qty']);

        $coordinator->reconcile($order, [7 => 1, 8 => 2]);
        self::assertSame(9.0, $inventory->getAvailable(new InventoryKey(7)));
        self::assertSame(2.0, $inventory->getAvailable(new InventoryKey(8)));

        $coordinator->reconcile($order, [8 => 2]);
        self::assertSame(10.0, $inventory->getAvailable(new InventoryKey(7)));
        self::assertSame(InventoryReservationState::RELEASED, $store->findReservation(40, 7)['state']);
        self::assertSame(2.0, $store->findReservation(40, 8)['qty']);
    }

    public function testTwoVariantsShareOneProductHold(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 10);
        $inventory = new ProductStockInventory($store);
        $coordinator = new OrderInventoryCoordinator($this->modx(), $inventory);

        $coordinator->reconcile($this->order(2), [7 => 5]);

        self::assertSame(5.0, $inventory->getAvailable(new InventoryKey(7)));
        self::assertSame(5.0, $store->findReservation(40, 7)['qty']);
    }

    public function testCommittedOrderKeepsCommittedState(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 8);
        $store->saveReservation(40, 7, 2, InventoryReservationState::COMMITTED);
        $inventory = new ProductStockInventory($store);
        $coordinator = new OrderInventoryCoordinator($this->modx(), $inventory);
        $order = $this->order(2);

        $coordinator->reconcile($order, [7 => 5]);

        self::assertSame(5.0, $inventory->getAvailable(new InventoryKey(7)));
        self::assertSame(InventoryReservationState::COMMITTED, $store->findReservation(40, 7)['state']);
        self::assertSame(5.0, $store->findReservation(40, 7)['qty']);
    }

    public function testPaidAndSentCreateCommittedRows(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 10);
        $inventory = new ProductStockInventory($store);
        $coordinator = new OrderInventoryCoordinator($this->modx(), $inventory);

        $coordinator->reconcile($this->order(3), [7 => 2]);
        self::assertSame(InventoryReservationState::COMMITTED, $store->findReservation(40, 7)['state']);

        $store->deleteReservation(40, 7);
        $store->seedStock(7, 10);
        $coordinator->reconcile($this->order(4), [7 => 1]);
        self::assertSame(InventoryReservationState::COMMITTED, $store->findReservation(40, 7)['state']);
        self::assertSame(9.0, $inventory->getAvailable(new InventoryKey(7)));
    }

    public function testDraftAndCanceledDoNotChangeTheLedger(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 10);
        $store->saveReservation(40, 7, 2, InventoryReservationState::RESERVED);
        $inventory = new ProductStockInventory($store);
        $coordinator = new OrderInventoryCoordinator($this->modx(), $inventory);

        self::assertSame([], $coordinator->reconcile($this->order(1), [7 => 9]));
        self::assertSame([], $coordinator->reconcile($this->order(5), [7 => 9]));
        self::assertSame(10.0, $inventory->getAvailable(new InventoryKey(7)));
        self::assertSame(2.0, $store->findReservation(40, 7)['qty']);
    }

    public function testShortageWithoutForceLeavesStockUntouched(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 1);
        $inventory = new ProductStockInventory($store);
        $coordinator = new OrderInventoryCoordinator($this->modx(), $inventory);

        try {
            $coordinator->reconcile($this->order(2), [7 => 5]);
            self::fail('expected InventoryException');
        } catch (InventoryException $exception) {
            self::assertSame('ms3_err_inventory_short', $exception->getLexiconKey());
            self::assertSame(1.0, $exception->getPlaceholders()['available']);
            self::assertSame(5.0, $exception->getPlaceholders()['required']);
            self::assertSame(1.0, $inventory->getAvailable(new InventoryKey(7)));
            self::assertNull($store->findReservation(40, 7));
        }
    }

    public function testForceWritesNegativeStockAndReportsTheOverride(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 1);
        $inventory = new ProductStockInventory($store);
        $coordinator = new OrderInventoryCoordinator($this->modx(), $inventory);

        $overrides = $coordinator->reconcile($this->order(2), [7 => 5], true);

        self::assertSame(-4.0, $inventory->getAvailable(new InventoryKey(7)));
        self::assertSame(5.0, $store->findReservation(40, 7)['qty']);
        self::assertSame([
            ['product_id' => 7, 'available' => 1.0, 'required' => 5.0],
        ], $overrides);
    }

    public function testFailedSecondLineRollsTheFirstLineBack(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 10);
        $store->seedStock(8, 0);
        $inventory = new ProductStockInventory($store);
        $coordinator = new OrderInventoryCoordinator($this->modx(), $inventory);

        try {
            $coordinator->reconcile($this->order(2), [7 => 3, 8 => 1]);
            self::fail('expected InventoryException');
        } catch (InventoryException) {
            self::assertSame(10.0, $inventory->getAvailable(new InventoryKey(7)));
            self::assertSame(0.0, $inventory->getAvailable(new InventoryKey(8)));
            self::assertSame(
                InventoryReservationState::RELEASED,
                $store->findReservation(40, 7)['state']
            );
            self::assertNull($store->findReservation(40, 8));
        }
    }

    public function testSnapshotReconcileRestoresThePreviousHold(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 10);
        $inventory = new ProductStockInventory($store);
        $coordinator = new OrderInventoryCoordinator($this->modx(), $inventory);
        $order = $this->order(2);
        $coordinator->reconcile($order, [7 => 4]);

        $coordinator->reconcile($order, [], true);

        self::assertSame(10.0, $inventory->getAvailable(new InventoryKey(7)));
        self::assertSame(InventoryReservationState::RELEASED, $store->findReservation(40, 7)['state']);
    }

    public function testNonAdjustableInventoryIsRejectedWhenTheOrderHoldsStock(): void
    {
        $coordinator = new OrderInventoryCoordinator($this->modx(), new NonAdjustableInventory());

        try {
            $coordinator->reconcile($this->order(2), [7 => 1]);
            self::fail('expected InventoryException');
        } catch (InventoryException $exception) {
            self::assertSame('ms3_err_inventory_adjust_unsupported', $exception->getLexiconKey());
        }

        self::assertSame([], $coordinator->reconcile($this->order(1), [7 => 1]));
    }

    public function testEditThenPaidAndCanceledMatchTheEditedQty(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 10);
        $inventory = new ProductStockInventory($store);
        $coordinator = new OrderInventoryCoordinator($this->modx(), $inventory);
        $order = $this->order(2);
        $order->products = [$this->line(7, 2)];

        $coordinator->applyStatusChange($order, 2);
        $coordinator->reconcile($order, [7 => 5]);
        self::assertSame(5.0, $inventory->getAvailable(new InventoryKey(7)));

        $coordinator->applyStatusChange($order, 3);
        self::assertSame(5.0, $inventory->getAvailable(new InventoryKey(7)));
        self::assertSame(InventoryReservationState::COMMITTED, $store->findReservation(40, 7)['state']);
        self::assertSame(5.0, $store->findReservation(40, 7)['qty']);

        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 10);
        $inventory = new ProductStockInventory($store);
        $coordinator = new OrderInventoryCoordinator($this->modx(), $inventory);
        $order = $this->order(2);
        $order->products = [$this->line(7, 2)];
        $coordinator->applyStatusChange($order, 2);
        $coordinator->reconcile($order, [7 => 5]);
        $order->products = [$this->line(7, 5)];
        $coordinator->applyStatusChange($order, 5);

        self::assertSame(10.0, $inventory->getAvailable(new InventoryKey(7)));
        self::assertSame(InventoryReservationState::RELEASED, $store->findReservation(40, 7)['state']);
    }

    public function testAdjustEmitsBeforeThenAfter(): void
    {
        $events = [];
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 5);
        $inventory = new ProductStockInventory($store, $this->ms3RecordingEvents($events));

        $inventory->adjust(new InventoryKey(7), 2, new InventoryContext(40, 'order', InventoryReservationState::RESERVED));

        self::assertSame(['msOnBeforeInventoryAdjust', 'msOnInventoryAdjust'], $events);
    }

    public function testAfterEventFailureLeavesTheCommittedWrite(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 5);
        $inventory = new ProductStockInventory($store, $this->ms3FailingEvent('msOnInventoryAdjust'));

        $inventory->adjust(new InventoryKey(7), 2, new InventoryContext(40, 'order', InventoryReservationState::RESERVED));

        self::assertSame(3.0, $store->getAvailable(7));
        self::assertSame(2.0, $store->findReservation(40, 7)['qty']);
    }

    public function testSilentAdjustIgnoresACancellingPlugin(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 5);
        $inventory = new ProductStockInventory($store, $this->ms3FailingEvent('msOnBeforeInventoryAdjust'));

        $inventory->adjust(
            new InventoryKey(7),
            2,
            new InventoryContext(40, 'order', InventoryReservationState::RESERVED),
            false,
            false
        );

        self::assertSame(3.0, $store->getAvailable(7));
        self::assertSame(2.0, $store->findReservation(40, 7)['qty']);
    }

    public function testRuntimeFailureRollsTheEarlierProductBack(): void
    {
        $inner = new InMemoryInventoryStockStore();
        $inner->seedStock(7, 10);
        $inner->seedStock(8, 10);
        $store = new ThrowingInventoryStockStore($inner, 8);
        $coordinator = new OrderInventoryCoordinator($this->modx(), new ProductStockInventory($store));

        try {
            $coordinator->reconcile($this->order(2), [7 => 3.0, 8 => 1.0]);
            self::fail('expected the store to fail');
        } catch (\RuntimeException) {
        }

        self::assertSame(10.0, $inner->getAvailable(7));
        self::assertSame('released', $inner->findReservation(40, 7)['state']);
        self::assertSame(10.0, $inner->getAvailable(8));
    }

    private function modx(): modX
    {
        return new class extends modX {
            public function getOption(string $key, $options = null, $default = null)
            {
                return match ($key) {
                    'ms3_inventory_enabled' => true,
                    'ms3_status_draft' => 1,
                    'ms3_status_new' => 2,
                    'ms3_status_paid' => 3,
                    'ms3_status_canceled' => 5,
                    default => $default,
                };
            }

            public function getObject($class, $criteria = null, $cacheFlag = true)
            {
                $id = is_array($criteria) ? (int) ($criteria['id'] ?? 0) : (int) $criteria;
                $position = [
                    1 => 0,
                    2 => 1,
                    3 => 2,
                    4 => 3,
                    5 => 4,
                    6 => 1,
                ][$id] ?? null;
                if ($position === null) {
                    return null;
                }

                return new class ($position) {
                    public function __construct(private int $position)
                    {
                    }

                    public function get($key)
                    {
                        return $key === 'position' ? $this->position : null;
                    }
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

    private function ms3FailingEvent(string $failingEvent): \MiniShop3\MiniShop3
    {
        $ms3 = $this->getMockBuilder(\MiniShop3\MiniShop3::class)
            ->disableOriginalConstructor()
            ->getMock();
        $ms3->utils = new class ($failingEvent) {
            public function __construct(private string $failingEvent)
            {
            }

            public function invokeEvent(string $eventName, array $params = []): array
            {
                if ($eventName === $this->failingEvent) {
                    return ['success' => false, 'message' => 'cancelled'];
                }

                return ['success' => true, 'message' => '', 'data' => $params];
            }
        };

        return $ms3;
    }

    private function order(int $statusId): RecordingMsOrder
    {
        $order = new RecordingMsOrder(['id' => 40, 'status_id' => $statusId]);

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

final class NonAdjustableInventory implements InventoryServiceInterface
{
    public function getAvailable(InventoryKey $key): float
    {
        return 0;
    }

    public function assertAvailable(InventoryKey $key, float $qty): void
    {
    }

    public function reserve(InventoryKey $key, float $qty, InventoryContext $ctx, bool $notify = true): void
    {
    }

    public function release(InventoryKey $key, float $qty, InventoryContext $ctx, bool $notify = true): void
    {
    }

    public function commit(InventoryKey $key, float $qty, InventoryContext $ctx): void
    {
    }
}

final class ThrowingInventoryStockStore implements InventoryStockStoreInterface
{
    public function __construct(
        private readonly InMemoryInventoryStockStore $inner,
        private readonly int $failingProductId,
    ) {
    }

    public function getAvailable(int $productId): float
    {
        return $this->inner->getAvailable($productId);
    }

    public function tryDecrement(int $productId, float $qty): bool
    {
        if ($productId === $this->failingProductId) {
            throw new \RuntimeException('store failed');
        }

        return $this->inner->tryDecrement($productId, $qty);
    }

    public function decrement(int $productId, float $qty): bool
    {
        return $this->inner->decrement($productId, $qty);
    }

    public function increment(int $productId, float $qty): void
    {
        $this->inner->increment($productId, $qty);
    }

    public function listReservations(int $orderId): array
    {
        return $this->inner->listReservations($orderId);
    }

    public function findReservation(int $orderId, int $productId): ?array
    {
        return $this->inner->findReservation($orderId, $productId);
    }

    public function lockReservation(int $orderId, int $productId): ?array
    {
        return $this->inner->lockReservation($orderId, $productId);
    }

    public function saveReservation(int $orderId, int $productId, float $qty, string $state): void
    {
        $this->inner->saveReservation($orderId, $productId, $qty, $state);
    }

    public function insertReservation(int $orderId, int $productId, float $qty, string $state): bool
    {
        return $this->inner->insertReservation($orderId, $productId, $qty, $state);
    }

    public function transitionReservation(
        int $orderId,
        int $productId,
        string $fromState,
        string $toState,
        ?float $qty = null
    ): bool {
        return $this->inner->transitionReservation($orderId, $productId, $fromState, $toState, $qty);
    }

    public function deleteReservation(int $orderId, int $productId): void
    {
        $this->inner->deleteReservation($orderId, $productId);
    }

    public function runInTransaction(callable $work): void
    {
        $this->inner->runInTransaction($work);
    }
}
