<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\Cart;

use MiniShop3\Controllers\Cart\Cart;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msProduct;
use MiniShop3\Services\Cart\CartItemManager;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Services\Order\OrderLogService;
use MiniShop3\Services\Order\OrderService;
use MiniShop3\Tests\RecordingMsOrder;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Level-2: Cart facade add/change/remove against in-memory draft product store.
 */
final class CartFacadeDraftStoreTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }
    }

    public function testAddChangeRemoveAgainstDraftStore(): void
    {
        $store = new DraftProductStore();
        $draft = new RecordingMsOrder(['id' => 42, 'cart_cost' => 0, 'delivery_cost' => 0, 'cost' => 0, 'weight' => 0]);
        $product = new LightweightCartProduct([
            'id' => 11,
            'pagetitle' => 'Shirt',
            'price' => 25.0,
            'old_price' => 30.0,
            'weight' => 0.5,
            'class_key' => msProduct::class,
            'deleted' => 0,
            'published' => 1,
        ]);

        $cart = $this->makeCart($store, $draft, $product);
        self::assertTrue($cart->initialize('web', 'tok-1'));

        $added = $cart->add(11, 2, ['color' => 'red']);
        self::assertTrue($added['success'], $added['message'] ?? '');
        self::assertArrayHasKey('last_key', $added['data']);
        $key = $added['data']['last_key'];
        self::assertSame(2, $added['data']['cart'][$key]['count']);
        self::assertSame(50.0, $added['data']['cart'][$key]['cost']);

        $changed = $cart->change($key, 3);
        self::assertTrue($changed['success'], $changed['message'] ?? '');
        self::assertSame(3, $changed['data']['cart'][$key]['count']);
        self::assertSame(75.0, $changed['data']['cart'][$key]['cost']);

        $removed = $cart->remove($key);
        self::assertTrue($removed['success'], $removed['message'] ?? '');
        self::assertArrayNotHasKey($key, $removed['data']['cart']);
        self::assertSame([], $store->items);
    }

    public function testAddRejectsInvalidCount(): void
    {
        $store = new DraftProductStore();
        $draft = new RecordingMsOrder(['id' => 42]);
        $product = new LightweightCartProduct([
            'id' => 11,
            'pagetitle' => 'Shirt',
            'price' => 10.0,
            'old_price' => 0,
            'weight' => 1.0,
            'class_key' => msProduct::class,
            'deleted' => 0,
            'published' => 1,
        ]);
        $cart = $this->makeCart($store, $draft, $product);
        $cart->initialize('web', 'tok-1');

        $result = $cart->add(11, 0);
        self::assertFalse($result['success']);
        self::assertSame('ms3_cart_add_err_count', $result['message']);
    }

    private function makeCart(DraftProductStore $store, RecordingMsOrder $draft, LightweightCartProduct $product): Cart
    {
        $utils = new class {
            public function success(string $message = '', array $data = [], array $placeholders = []): array
            {
                return ['success' => true, 'message' => $message, 'data' => $data];
            }

            public function error(string $message = '', array $data = [], array $placeholders = []): array
            {
                return ['success' => false, 'message' => $message, 'data' => $data];
            }

            public function invokeEvent(string $eventName, array $params = []): array
            {
                return ['success' => true, 'message' => '', 'data' => $params];
            }
        };

        $orderService = new OrderService(new modX());
        $modx = new class ($store, $product, $orderService) extends modX {
            public object $services;
            public object $lexicon;

            public function __construct(
                private DraftProductStore $store,
                private LightweightCartProduct $product,
                OrderService $orderService,
            ) {
                parent::__construct();
                $this->lexicon = new class {
                    public function load($topic): void
                    {
                    }
                };
                $this->services = new class ($orderService) {
                    public function __construct(private OrderService $orderService)
                    {
                    }

                    public function has(string $key): bool
                    {
                        return $key === 'ms3_order_service';
                    }

                    public function get(string $key): mixed
                    {
                        return $key === 'ms3_order_service' ? $this->orderService : null;
                    }
                };
            }

            public function getOption($key, $options = null, $default = null, $skipEmpty = false)
            {
                return match ((string) $key) {
                    'ms3_cart_max_count' => 1000,
                    'ms3_cart_product_key_fields' => 'id,options',
                    'ms3_cart_context' => '0',
                    default => $default,
                };
            }

            public function lexicon($key, $params = [])
            {
                return (string) $key;
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                if ($className === msProduct::class && is_array($criteria)) {
                    if ((int) ($criteria['id'] ?? 0) !== (int) $this->product->get('id')) {
                        return null;
                    }

                    return $this->product;
                }

                if ($className === msOrderProduct::class && is_array($criteria)) {
                    $key = (string) ($criteria['product_key'] ?? '');

                    return $this->store->items[$key] ?? null;
                }

                return null;
            }

            public function newObject($className, $fields = [])
            {
                if ($className === msOrderProduct::class) {
                    $item = new RecordingOrderProduct();
                    if (is_array($fields) && $fields !== []) {
                        $item->fromArray($fields);
                    }
                    $this->store->track($item);

                    return $item;
                }

                return null;
            }

            public function getIterator($className, $criteria = null, $cacheFlag = true): \ArrayIterator
            {
                if ($className === msOrderProduct::class) {
                    return new \ArrayIterator(array_values($this->store->items));
                }

                return new \ArrayIterator([]);
            }
        };

        $ms3 = $this->createStub(MiniShop3::class);
        $ms3->modx = $modx;
        $ms3->utils = $utils;

        $itemManager = new CartItemManager($modx, $ms3);
        $draftManager = $this->createStub(OrderDraftManager::class);
        $draftManager->method('getDraft')->willReturn($draft);
        $draftManager->method('getOrCreateDraft')->willReturn($draft);
        $draftManager->method('recalculate')->willReturnCallback(
            static function (msOrder $order) use ($orderService, $itemManager): void {
                $items = $itemManager->loadItems($order);
                $products = [];
                foreach ($items as $row) {
                    $products[] = new class ($row) {
                        public function __construct(private array $row)
                        {
                        }

                        public function get(string $field): mixed
                        {
                            return $this->row[$field] ?? 0;
                        }
                    };
                }
                $totals = OrderService::aggregateProductsTotals($products);
                $order->set('cart_cost', $totals['cart_cost']);
                $order->set('weight', $totals['weight']);
                $order->set(
                    'cost',
                    $orderService->clampComputedTotal(
                        $order,
                        (float) $totals['cart_cost'],
                        (float) ($order->get('delivery_cost') ?? 0),
                        0.0
                    )
                );
                $order->save();
            }
        );

        return new HarnessCart($ms3, $itemManager, $draftManager);
    }
}

final class DraftProductStore
{
    /** @var array<string, RecordingOrderProduct> */
    public array $items = [];

    public function track(RecordingOrderProduct $item): void
    {
        $item->bindStore($this);
    }

    public function sync(RecordingOrderProduct $item): void
    {
        $key = (string) $item->get('product_key');
        if ($key === '' || $item->removed) {
            foreach ($this->items as $k => $existing) {
                if ($existing === $item) {
                    unset($this->items[$k]);
                }
            }

            return;
        }
        $this->items[$key] = $item;
    }
}

final class RecordingOrderProduct extends msOrderProduct
{
    /** @var array<string, mixed> */
    private array $fields = [];

    private ?DraftProductStore $store = null;

    public bool $removed = false;

    public function bindStore(DraftProductStore $store): void
    {
        $this->store = $store;
    }

    public function fromArray($fields, $keyPrefix = '', $setPrimaryKeys = false, $rawValues = false, $adhoc = false)
    {
        foreach ($fields as $key => $value) {
            $this->fields[$key] = $value;
        }
        $this->store?->sync($this);

        return true;
    }

    public function get($k, $format = null, $formatTemplate = null)
    {
        return $this->fields[$k] ?? null;
    }

    public function set($key, $value)
    {
        $this->fields[$key] = $value;
        $this->store?->sync($this);

        return true;
    }

    public function toArray($keyPrefix = '', $rawValues = false, $excludeLazy = false, $includeRelated = false)
    {
        return $this->fields;
    }

    public function save($cacheFlag = null)
    {
        $this->store?->sync($this);

        return true;
    }

    public function remove(array $ancestors = [])
    {
        $this->removed = true;
        $this->store?->sync($this);

        return true;
    }
}

final class LightweightCartProduct extends msProduct
{
    /** @var array<string, mixed> */
    public $_fieldMeta = [];

    /** @var list<string> */
    protected $dataRelated = [];

    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(private array $fields)
    {
    }

    public function get($k, $format = null, $formatTemplate = null)
    {
        return $this->fields[$k] ?? null;
    }

    public function toArray($keyPrefix = '', $rawValues = false, $excludeLazy = false, $includeRelated = false)
    {
        return $this->fields;
    }

    public function getPrice($data = [])
    {
        return (float) ($this->fields['price'] ?? 0);
    }

    public function getWeight($data = [])
    {
        return (float) ($this->fields['weight'] ?? 0);
    }
}

final class HarnessCart extends Cart
{
    public function __construct(
        MiniShop3 $ms3,
        CartItemManager $itemManager,
        OrderDraftManager $draftManager
    ) {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;
        $this->itemManager = $itemManager;
        $this->draftManager = $draftManager;
    }

    protected function getOrderLog(): OrderLogService
    {
        $log = new class extends OrderLogService {
            public function __construct()
            {
            }

            public function addEntry(int $orderId, string $action, array $data, bool $visible = true): bool
            {
                return true;
            }
        };

        return $log;
    }

    protected function getCustomerId(): ?int
    {
        return null;
    }
}
