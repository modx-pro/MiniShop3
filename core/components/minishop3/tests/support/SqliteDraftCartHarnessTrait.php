<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Support;

use MiniShop3\Controllers\Cart\Cart;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msProduct;
use MiniShop3\Services\Cart\CartItemManager;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Services\Order\OrderService;
use MiniShop3\Tests\RecordingMsOrder;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Shared SQLite draft cart factory for CartFacade + WebApi Phase B tests.
 *
 * @mixin TestCase
 */
trait SqliteDraftCartHarnessTrait
{
    protected function makeSqliteDraftCart(
        OrderProductSqliteStore $store,
        RecordingMsOrder $draft,
        SqliteDraftCartProduct $product
    ): Cart {
        $utils = new class {
            public function success(string $message = '', array $data = [], array $placeholders = []): array
            {
                unset($placeholders);

                return ['success' => true, 'message' => $message, 'data' => $data];
            }

            public function error(string $message = '', array $data = [], array $placeholders = []): array
            {
                unset($placeholders);

                return ['success' => false, 'message' => $message, 'data' => $data];
            }

            public function invokeEvent(string $eventName, array $params = []): array
            {
                unset($eventName);

                return ['success' => true, 'message' => '', 'data' => $params];
            }
        };

        $orderService = new OrderService(new modX());
        $modx = new class ($store, $product, $orderService) extends modX {
            public function __construct(
                private OrderProductSqliteStore $store,
                private SqliteDraftCartProduct $product,
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

            public function lexicon(string $key, array $params = []): string
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
                    $row = $this->store->findOne($criteria);
                    if ($row === null) {
                        return null;
                    }
                    $item = new SqliteOrderProduct();
                    $item->bindStore($this->store);
                    $item->hydrate($row);

                    return $item;
                }

                return null;
            }

            public function newObject($className, $fields = [])
            {
                if ($className === msOrderProduct::class) {
                    $item = new SqliteOrderProduct();
                    $item->bindStore($this->store);
                    if (is_array($fields) && $fields !== []) {
                        $item->fromArray($fields);
                    }

                    return $item;
                }

                return null;
            }

            public function getIterator($className, $criteria = null, $cacheFlag = true): \ArrayIterator
            {
                if ($className === msOrderProduct::class) {
                    $rows = $this->store->findAll(is_array($criteria) ? $criteria : []);
                    $items = [];
                    foreach ($rows as $row) {
                        $item = new SqliteOrderProduct();
                        $item->bindStore($this->store);
                        $item->hydrate($row);
                        $items[] = $item;
                    }

                    return new \ArrayIterator($items);
                }

                return new \ArrayIterator([]);
            }

            public function getCount($className, $criteria = null)
            {
                if ($className === msOrderProduct::class) {
                    return count($this->store->findAll(is_array($criteria) ? $criteria : []));
                }

                return 0;
            }
        };

        /** @var MiniShop3 $ms3 */
        $ms3 = $this->createStub(MiniShop3::class);
        $ms3->modx = $modx;
        $ms3->utils = $utils;

        $itemManager = new CartItemManager($modx, $ms3);
        /** @var OrderDraftManager $draftManager */
        $draftManager = $this->createStub(OrderDraftManager::class);
        $draftManager->method('getDraft')->willReturn($draft);
        $draftManager->method('getOrCreateDraft')->willReturn($draft);
        $draftManager->method('isEmpty')->willReturnCallback(
            static function (msOrder $order) use ($store): bool {
                return $store->findAll(['order_id' => (int) $order->get('id')]) === [];
            }
        );
        $draftManager->method('deleteDraft')->willReturn(true);
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

        return new SqliteHarnessCart($ms3, $itemManager, $draftManager);
    }
}
