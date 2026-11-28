<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Inventory;

use MiniShop3\Services\Inventory\InventoryContext;
use MiniShop3\Services\Inventory\InventoryException;
use MiniShop3\Services\Inventory\InventoryKey;
use MiniShop3\Services\Inventory\InventoryReservationState;
use MiniShop3\Services\Inventory\OrderInventoryCoordinator;
use MiniShop3\Services\Inventory\PdoInventoryStockStore;
use MiniShop3\Services\Inventory\ProductStockInventory;
use MiniShop3\Tests\RecordingMsOrder;
use MiniShop3\Tests\Support\InMemoryInventoryStockStore;
use MODX\Revolution\modX;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Review regressions for #1001 variant-key stock accounting.
 *
 * Addons are stubbed through the real events: msOnInventoryResolveKey chooses
 * a SKU per order line and msOnInventoryAvailable reports SKU stock, so the
 * tests exercise the same code path as a production addon.
 */
final class VariantInventoryRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
        require_once dirname(__DIR__, 3) . '/RecordingMsOrder.php';
    }

    public function testKeyCanonicalizationMakesEquivalentSkusShareOneIdentity(): void
    {
        self::assertSame(
            (new InventoryKey(7, 'SKU-B,sku-A'))->identity(),
            (new InventoryKey(7, 'sku-a,sku-b'))->identity()
        );
        self::assertSame(
            (new InventoryKey(7, '{"size":"M","color":"red"}'))->identity(),
            (new InventoryKey(7, '{"color":"red","size":"M"}'))->identity()
        );
        self::assertSame(
            (new InventoryKey(7, 'color=RED;size=M'))->identity(),
            (new InventoryKey(7, 'size=m;color=red'))->identity()
        );
        self::assertSame(
            '7|red,sku',
            (new InventoryKey(7, 'SKU,RED'))->identity()
        );

        $longA = str_repeat('a', 130) . ',b';
        $longB = 'b,' . str_repeat('A', 130);
        self::assertSame(
            (new InventoryKey(7, $longA))->identity(),
            (new InventoryKey(7, $longB))->identity(),
            'canonicalization must happen before the long-key hash'
        );
        self::assertSame(32, strlen((new InventoryKey(7, $longA))->variantKey ?? ''));
    }

    public function testResolveEventFiresOncePerLineAcrossReserveEditAndStatusChanges(): void
    {
        $events = [];
        $modx = $this->modx([1 => 'sku-red', 2 => 'sku-red'], ['sku-red' => 20], $events);
        $inventory = new ProductStockInventory(
            $this->inMemoryStore(10),
            $this->ms3($modx, $events)
        );
        $coordinator = new OrderInventoryCoordinator($modx, $inventory);
        $order = $this->order([$this->line(1, 7, 2), $this->line(2, 7, 3)]);

        $coordinator->assertOrderAvailable($order);
        $coordinator->applyStatusChange($order, 2);

        $store = $this->storeOf($inventory);
        $row = $store->findReservationByKey(40, new InventoryKey(7, 'sku-red'));
        self::assertSame(5.0, $row['qty']);
        self::assertNull($store->findReservation(40, 7));
        self::assertSame(10.0, $inventory->getAvailable(new InventoryKey(7)));

        // Admin edit: build targets through the same resolver, then reconcile.
        $edited = [$this->line(1, 7, 4), $this->line(2, 7, 3)];
        $coordinator->reconcile($order, $coordinator->targetByKey($order, $edited));
        $row = $store->findReservationByKey(40, new InventoryKey(7, 'sku-red'));
        self::assertSame(7.0, $row['qty']);
        self::assertCount(1, $store->listReservations(40));
        self::assertSame(10.0, $inventory->getAvailable(new InventoryKey(7)), 'variants never touch core stock');

        $coordinator->applyStatusChange($order, 3);
        self::assertSame(
            InventoryReservationState::COMMITTED,
            $store->findReservationByKey(40, new InventoryKey(7, 'sku-red'))['state']
        );
        self::assertSame(
            2,
            count(array_filter($events, static fn(string $event): bool => $event === 'msOnInventoryResolveKey')),
            'key resolution must be cached per order line (#1001 review)'
        );
    }

    public function testVariantReserveChecksAddonAvailabilityAndFailsClosed(): void
    {
        $events = [];
        $modx = $this->modx([1 => 'sku-red'], ['sku-red' => 1], $events);
        $inventory = new ProductStockInventory($this->inMemoryStore(999), $this->ms3($modx, $events));
        $coordinator = new OrderInventoryCoordinator($modx, $inventory);

        try {
            $coordinator->applyStatusChange($this->order([$this->line(1, 7, 2)]), 2);
            self::fail('expected an insufficient-stock error for the SKU');
        } catch (InventoryException $exception) {
            self::assertSame('ms3_err_inventory_insufficient', $exception->getLexiconKey());
        }
        self::assertSame([], $this->storeOf($inventory)->listReservations(40));

        // No addon answer at all -> zero real SKU stock, never product stock.
        $noAnswer = new ProductStockInventory($this->inMemoryStore(999), $this->ms3($modx, $events));
        self::assertSame(0.0, $noAnswer->getAvailable(new InventoryKey(7, 'unanswered-sku')));
        $this->expectException(InventoryException::class);
        $noAnswer->assertAvailable(new InventoryKey(7, 'unanswered-sku'), 1);
    }

    public function testLegacyProductLevelReservationIsReKeyedOnFirstVariantUse(): void
    {
        $events = [];
        $store = $this->inMemoryStore(10);
        $store->saveReservation(40, new InventoryKey(7), 2, InventoryReservationState::RESERVED);
        $modx = $this->modx([1 => 'sku-red'], ['sku-red' => 10], $events);
        $inventory = new ProductStockInventory($store, $this->ms3($modx, $events));
        $coordinator = new OrderInventoryCoordinator($modx, $inventory);
        $order = $this->order([$this->line(1, 7, 2)]);

        $coordinator->applyStatusChange($order, 2);

        self::assertNull($store->findReservation(40, 7), 'legacy row must be adopted, not duplicated');
        self::assertSame(
            2.0,
            $store->findReservationByKey(40, new InventoryKey(7, 'sku-red'))['qty']
        );
        self::assertCount(1, $store->listReservations(40));

        // Release/commit must find the re-keyed row as well.
        $coordinator->applyStatusChange($order, 5);
        self::assertSame(
            InventoryReservationState::RELEASED,
            $store->findReservationByKey(40, new InventoryKey(7, 'sku-red'))['state']
        );
    }

    public function testVariantAfterEventsWaitForBatchSuccessAndRollbackNotifiesAddons(): void
    {
        $events = [];
        $modx = $this->modx([1 => 'sku-red', 2 => 'sku-blue'], ['sku-red' => 9, 'sku-blue' => 0], $events);
        $inventory = new ProductStockInventory($this->inMemoryStore(10), $this->ms3($modx, $events));
        $coordinator = new OrderInventoryCoordinator($modx, $inventory);
        $order = $this->order([$this->line(1, 7, 1), $this->line(2, 7, 1)]);

        try {
            $coordinator->applyStatusChange($order, 2);
            self::fail('expected the blue SKU to be out of stock');
        } catch (InventoryException) {
        }
        self::assertNotContains('msOnInventoryReserve', $events);
        self::assertContains('msOnBeforeInventoryRelease', $events);
        self::assertContains('msOnInventoryRelease', $events);
        self::assertSame([], $this->heldRows($this->storeOf($inventory)->listReservations(40)));

        // Successful batch: after-events only once every line held.
        $events = [];
        $modx = $this->modx([1 => 'sku-red', 2 => 'sku-blue'], ['sku-red' => 9, 'sku-blue' => 9], $events);
        $inventory = new ProductStockInventory($this->inMemoryStore(10), $this->ms3($modx, $events));
        $coordinator = new OrderInventoryCoordinator($modx, $inventory);
        $coordinator->applyStatusChange($order, 2);

        $firstAfter = array_search('msOnInventoryReserve', $events, true);
        $lastBefore = array_search('msOnBeforeInventoryReserve', $events, true);
        self::assertNotFalse($firstAfter);
        self::assertNotFalse($lastBefore);
        self::assertGreaterThan($lastBefore, $firstAfter);
        self::assertSame(
            2,
            count(array_filter($events, static fn(string $event): bool => $event === 'msOnInventoryReserve'))
        );
    }

    public function testPdoStoreKeepsVariantRowsUniqueAndReKeysLegacyRow(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, stock REAL NOT NULL)');
        $pdo->exec('CREATE TABLE reservations (
            order_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            variant_key TEXT NOT NULL DEFAULT \'\',
            qty REAL NOT NULL,
            state TEXT NOT NULL,
            createdon INTEGER,
            updatedon INTEGER,
            UNIQUE (order_id, product_id, variant_key)
        )');
        $pdo->exec('INSERT INTO products (id, stock) VALUES (7, 10)');
        $pdo->exec("INSERT INTO reservations (order_id, product_id, variant_key, qty, state)
            VALUES (40, 7, '', 2, 'reserved')");

        $store = new PdoInventoryStockStore($pdo, 'products', 'reservations');
        $events = [];
        $modx = $this->modx([1 => 'sku-red'], ['sku-red' => 10], $events);
        $coordinator = new OrderInventoryCoordinator($modx, new ProductStockInventory($store, $this->ms3($modx, $events)));
        $coordinator->applyStatusChange($this->order([$this->line(1, 7, 2)]), 2);

        $rows = $pdo->query("SELECT variant_key, qty FROM reservations WHERE order_id = 40")->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame([['variant_key' => 'sku-red', 'qty' => 2.0]], $rows);
        self::assertFalse(
            $store->insertReservationByKey(40, new InventoryKey(7, 'sku-red'), 1, InventoryReservationState::RESERVED),
            'a second row for the same SKU must be rejected'
        );
        self::assertTrue(
            $store->insertReservationByKey(40, new InventoryKey(7, 'sku-blue'), 1, InventoryReservationState::RESERVED)
        );
    }

    public function testThirdPartyAdjustableInventoryGetsProductLevelHeldByKeyDefault(): void
    {
        $inventory = new class implements
            \MiniShop3\Services\Inventory\InventoryServiceInterface,
            \MiniShop3\Services\Inventory\AdjustableInventoryInterface {
            use \MiniShop3\Services\Inventory\AdjustableInventoryKeyTrait;

            public function getAvailable(InventoryKey $key): float
            {
                return 10;
            }

            public function assertAvailable(InventoryKey $key, float $qty): void
            {
            }

            public function reserve(
                InventoryKey $key,
                float $qty,
                InventoryContext $ctx,
                bool $notify = true
            ): void {
            }

            public function release(
                InventoryKey $key,
                float $qty,
                InventoryContext $ctx,
                bool $notify = true
            ): void {
            }

            public function commit(InventoryKey $key, float $qty, InventoryContext $ctx): void
            {
            }

            public function adjust(
                InventoryKey $key,
                float $targetQty,
                InventoryContext $ctx,
                bool $force = false,
                bool $notify = true,
            ): void {
            }

            public function heldByProduct(int $orderId): array
            {
                return [7 => ['qty' => 2.0, 'state' => InventoryReservationState::RESERVED]];
            }
        };

        $events = [];
        $coordinator = new OrderInventoryCoordinator($this->modx([], [], $events), $inventory);
        self::assertSame(['7' => 2.0], $coordinator->heldByKeyMap(40));
    }

    public function testLegacyIntOnlyStoreStillWorksThroughAdapter(): void
    {
        $legacy = new LegacyIntInventoryStockStore();
        // Type check: an old int-only store remains a valid interface implementation.
        self::assertInstanceOf(\MiniShop3\Services\Inventory\InventoryStockStoreInterface::class, $legacy);

        $inventory = new ProductStockInventory($legacy);
        $inventory->reserve(new InventoryKey(7, 'sku-red'), 2, new InventoryContext(40));
        self::assertSame(2.0, $inventory->getAvailable(new InventoryKey(7)), 'legacy store degrades to product-level');
    }

    private function inMemoryStore(float $stock): InMemoryInventoryStockStore
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, $stock);

        return $store;
    }

    private function storeOf(ProductStockInventory $inventory): InMemoryInventoryStockStore
    {
        $property = new \ReflectionProperty(ProductStockInventory::class, 'store');

        return $property->getValue($inventory);
    }

    /**
     * @param list<array{state: string}> $rows
     * @return list<array{state: string}>
     */
    private function heldRows(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            static fn(array $row): bool => in_array(
                $row['state'],
                [InventoryReservationState::RESERVED, InventoryReservationState::COMMITTED],
                true
            )
        ));
    }

    /**
     * modX with the addon events and the status options used by the coordinator.
     *
     * @param array<int, string> $variantByLine
     * @param array<string, float> $availableByKey
     * @param list<string> $events
     */
    private function modx(array $variantByLine, array $availableByKey, array &$events): modX
    {
        return new class ($variantByLine, $availableByKey, $events) extends modX {
            /** @var object|null */
            public $event;

            /** @var list<string> */
            private $events;

            /**
             * @param array<int, string> $variantByLine
             * @param array<string, float> $availableByKey
             * @param list<string> $events
             */
            public function __construct(
                private array $variantByLine,
                private array $availableByKey,
                array &$events
            ) {
                parent::__construct();
                $this->events = &$events;
            }

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
                $positions = [1 => 0, 2 => 1, 3 => 2, 4 => 3, 5 => 4];
                if (!isset($positions[$id])) {
                    return null;
                }

                return new class ($positions[$id]) {
                    public function __construct(private int $position)
                    {
                    }

                    public function get($key)
                    {
                        return $key === 'position' ? $this->position : null;
                    }
                };
            }

            public function invokeEvent($eventName, array $params = [])
            {
                $this->events[] = $eventName;
                $values = [];
                if ($eventName === 'msOnInventoryResolveKey') {
                    $lineId = (int) $params['orderProduct']->get('id');
                    if (isset($this->variantByLine[$lineId])) {
                        $values['variantKey'] = $this->variantByLine[$lineId];
                    }
                } elseif ($eventName === 'msOnInventoryAvailable') {
                    $values['available'] = $this->availableByKey[(string) $params['variant_key']] ?? 0;
                }
                $returned = $values;
                $this->event = new class ($returned) {
                    public $returnedValues;

                    public function __construct(array $values)
                    {
                        $this->returnedValues = $values;
                    }
                };

                return [true];
            }
        };
    }

    /**
     * @param list<string> $events
     */
    private function ms3(modX $modx, array &$events): \MiniShop3\MiniShop3
    {
        $ms3 = $this->getMockBuilder(\MiniShop3\MiniShop3::class)
            ->disableOriginalConstructor()
            ->getMock();
        $ms3->modx = $modx;
        $ms3->utils = new class ($events) {
            /** @var list<string> */
            private $events;

            /** @param list<string> $events */
            public function __construct(array &$events)
            {
                $this->events = &$events;
            }

            public function invokeEvent(string $eventName, array $params = []): array
            {
                $this->events[] = $eventName;

                return ['success' => true, 'message' => '', 'data' => $params];
            }
        };

        return $ms3;
    }

    /**
     * @param list<object> $lines
     */
    private function order(array $lines): RecordingMsOrder
    {
        $order = new RecordingMsOrder(['id' => 40, 'status_id' => 2]);
        $order->products = $lines;

        return $order;
    }

    private function line(int $id, int $productId, float $qty, ?string $options = null): object
    {
        return new class ($id, $productId, $qty, $options) {
            public function __construct(
                private int $id,
                private int $productId,
                private float $qty,
                private ?string $options,
            ) {
            }

            public function get($key)
            {
                return match ($key) {
                    'id' => $this->id,
                    'product_id' => $this->productId,
                    'count' => $this->qty,
                    'options' => $this->options,
                    default => null,
                };
            }
        };
    }
}

/**
 * A store written against the pre-#1001 interface: int product ids only.
 */
final class LegacyIntInventoryStockStore implements \MiniShop3\Services\Inventory\InventoryStockStoreInterface
{
    /** @var array<int, float> */
    private array $stock = [7 => 4.0];

    public function getAvailable(int $productId): float
    {
        return $this->stock[$productId] ?? 0;
    }

    public function tryDecrement(int $productId, float $qty): bool
    {
        if ($this->stock[$productId] < $qty) {
            return false;
        }
        $this->stock[$productId] -= $qty;

        return true;
    }

    public function decrement(int $productId, float $qty): bool
    {
        return $this->tryDecrement($productId, $qty);
    }

    public function increment(int $productId, float $qty): void
    {
        $this->stock[$productId] += $qty;
    }

    public function listReservations(int $orderId): array
    {
        return [];
    }

    public function findReservation(int $orderId, int $productId): ?array
    {
        return null;
    }

    public function lockReservation(int $orderId, int $productId): ?array
    {
        return null;
    }

    public function saveReservation(int $orderId, int $productId, float $qty, string $state): void
    {
    }

    public function insertReservation(int $orderId, int $productId, float $qty, string $state): bool
    {
        return true;
    }

    public function transitionReservation(
        int $orderId,
        int $productId,
        string $fromState,
        string $toState,
        ?float $qty = null
    ): bool {
        return true;
    }

    public function deleteReservation(int $orderId, int $productId): void
    {
    }

    public function runInTransaction(callable $work): void
    {
        $work();
    }
}
