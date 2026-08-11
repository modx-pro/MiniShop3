<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Services\Order\OrderFinalizeService;
use MiniShop3\Services\Order\OrderNumberGenerator;
use MiniShop3\Services\Order\OrderService;
use MiniShop3\Services\Order\ProgrammaticOrderService;
use MiniShop3\Tests\RecordingMsOrder;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;
/**
 * Level-2: sessionless ProgrammaticOrderService (#507).
 *
 * CI has no MySQL; this suite covers idempotency, origin events, cleanup, and empty session.
 */
final class ProgrammaticOrderServiceTest extends TestCase
{
    /** @var list<array{name: string, params: array}> */
    private array $events = [];

    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }
        $this->events = [];
        $_SESSION = [];
    }

    public function testCreateRequiresIdempotencyKey(): void
    {
        $service = $this->makeService();
        $result = $service->create([
            'products' => [['name' => 'Widget', 'price' => 10, 'count' => 1]],
        ]);

        self::assertFalse($result['success']);
        self::assertSame('ms3_order_err_idempotency_key_required', $result['message']);
    }

    public function testCreateRequiresProducts(): void
    {
        $service = $this->makeService();
        $result = $service->create([
            'idempotency_key' => 'cycle-1',
            'products' => [],
        ]);

        self::assertFalse($result['success']);
        self::assertSame('ms3_order_err_products_required', $result['message']);
    }

    public function testIdempotentCreateReturnsSameOrderWithoutDuplicatingProducts(): void
    {
        $world = $this->makeWorld();
        $service = $this->makeService($world);

        $input = [
            'idempotency_key' => 'cycle-42',
            'origin' => 'integration',
            'customer_id' => 7,
            'delivery_id' => 3,
            'payment_id' => 4,
            'address' => ['email' => 'a@example.com'],
            'products' => [
                ['name' => 'Widget', 'price' => 50.0, 'count' => 2, 'weight' => 1.0],
            ],
            'skip_validation' => true,
            'skip_notifications' => true,
        ];

        $first = $service->create($input);
        self::assertTrue($first['success'], (string) ($first['message'] ?? ''));
        self::assertSame(1, $first['data']['order_id']);
        self::assertSame(2, $first['data']['status_id']);
        self::assertCount(1, $world['products']);

        $second = $service->create($input);
        self::assertTrue($second['success']);
        self::assertSame('ms3_order_programmatic_idempotent', $second['message']);
        self::assertSame(1, $second['data']['order_id']);
        self::assertCount(1, $world['products'], 'idempotent retry must not add products again');
    }

    public function testCreateWorksWithEmptySessionAndSetsIntegrationOrigin(): void
    {
        $_SESSION = [];
        $world = $this->makeWorld();
        $service = $this->makeService($world);

        $result = $service->create([
            'idempotency_key' => 'no-session',
            'delivery_id' => 3,
            'payment_id' => 4,
            'products' => [
                ['name' => 'Widget', 'price' => 10.0, 'count' => 1],
            ],
            'skip_validation' => true,
            'skip_notifications' => true,
        ]);

        self::assertTrue($result['success'], (string) ($result['message'] ?? ''));
        self::assertSame([], $_SESSION);

        $createEvents = array_values(array_filter(
            $this->events,
            static fn(array $e): bool => in_array($e['name'], ['msOnBeforeCreateOrder', 'msOnCreateOrder'], true)
        ));
        self::assertNotEmpty($createEvents);
        foreach ($createEvents as $event) {
            self::assertSame('integration', $event['params']['origin'] ?? null);
            self::assertFalse($event['params']['from_manager'] ?? true);
        }

        $mgrEvents = array_filter(
            $this->events,
            static fn(array $e): bool => str_contains($e['name'], 'MgrCreateOrder')
        );
        self::assertCount(0, $mgrEvents, 'integration must not fire manager-only events');
    }

    public function testIdempotentRetryResumesUnfinishedDraft(): void
    {
        $world = $this->makeWorld();
        $service = $this->makeService($world);

        $order = new RecordingMsOrder([
            'id' => 9,
            'status_id' => 1,
            'delivery_id' => 3,
            'payment_id' => 4,
            'num' => null,
            'uuid' => 'u-9',
            'idempotency_key' => 'resume-me',
            'properties' => ['idempotency_key' => 'resume-me'],
            'cart_cost' => 10,
            'delivery_cost' => 0,
            'cost' => 10,
            'weight' => 0,
        ]);
        $order->products = [
            new class {
                public function get(string $field): float|int
                {
                    return match ($field) {
                        'cost' => 10.0,
                        'weight' => 0.0,
                        'count' => 1,
                        default => 0,
                    };
                }
            },
        ];
        $world['orders'][9] = $order;
        $world['products'][] = ['order_id' => 9, 'name' => 'Widget', 'cost' => 10];
        $world['next_order_id'] = 10;

        $result = $service->create([
            'idempotency_key' => 'resume-me',
            'products' => [['name' => 'ignored', 'price' => 1, 'count' => 1]],
            'skip_validation' => true,
            'skip_notifications' => true,
        ]);

        self::assertTrue($result['success'], (string) ($result['message'] ?? ''));
        self::assertSame('ms3_order_programmatic_created', $result['message']);
        self::assertSame(9, $result['data']['order_id']);
        self::assertSame(2, $result['data']['status_id']);
        self::assertCount(1, $world['orders'], 'resume must not create a second order');
    }

    public function testProductFailureCleansPartialDraft(): void
    {
        $world = $this->makeWorld();
        $world['fail_product_save'] = true;
        $service = $this->makeService($world);

        $result = $service->create([
            'idempotency_key' => 'boom',
            'delivery_id' => 3,
            'payment_id' => 4,
            'products' => [
                ['name' => 'Widget', 'price' => 10.0, 'count' => 1],
            ],
            'skip_validation' => true,
        ]);

        self::assertFalse($result['success']);
        self::assertSame([], $world['orders'], 'failed create must not leave draft order');
        self::assertSame([], $world['products']);
        self::assertSame([], $world['addresses']);
    }

    /**
     * @param array<string, mixed>|null $world
     */
    private function makeService(?array &$world = null): ProgrammaticOrderService
    {
        $world ??= $this->makeWorld();
        $events = &$this->events;

        $utils = new class ($events) {
            /** @param list<array{name: string, params: array}> $events */
            public function __construct(private array &$events)
            {
            }

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
                $this->events[] = ['name' => $eventName, 'params' => $params];

                return ['success' => true, 'message' => '', 'data' => $params];
            }
        };

        $ms3 = $this->createStub(MiniShop3::class);
        $ms3->utils = $utils;

        $orderService = new OrderService(new modX());
        $statusCalls = [];
        $statusService = new class ($statusCalls, $world) {
            public function __construct(private array &$statusCalls, private array &$world)
            {
            }

            public function change(int $orderId, int $statusId, bool $writeLog = true): true|string
            {
                $this->statusCalls[] = compact('orderId', 'statusId', 'writeLog');
                if (isset($this->world['orders'][$orderId])) {
                    $this->world['orders'][$orderId]->set('status_id', $statusId);
                }

                return true;
            }
        };

        $delivery = $this->createStub(\MiniShop3\Model\msDelivery::class);
        $delivery->method('get')->willReturnCallback(static function (string $k) {
            return match ($k) {
                'price' => 0.0,
                'weight_price' => 0.0,
                'active' => 1,
                'validation_rules' => null,
                default => null,
            };
        });
        $payment = $this->createStub(\MiniShop3\Model\msPayment::class);
        $payment->method('get')->willReturnCallback(static fn(string $k) => $k === 'active' ? 1 : null);

        $modx = new class ($world, $orderService, $statusService, $delivery, $payment) extends modX {
            public function __construct(
                public array &$world,
                OrderService $orderService,
                object $statusService,
                private object $delivery,
                private object $payment,
            ) {
                parent::__construct();
                $this->services = new class ($orderService, $statusService) {
                    public function __construct(
                        private OrderService $orderService,
                        private object $statusService,
                    ) {
                    }

                    public function has(string $key): bool
                    {
                        return in_array($key, ['ms3_order_service', 'ms3_order_status', 'ms3_delivery_service'], true);
                    }

                    public function get(string $key): mixed
                    {
                        return match ($key) {
                            'ms3_order_service' => $this->orderService,
                            'ms3_order_status' => $this->statusService,
                            'ms3_delivery_service' => new class {
                                public function getDeliveryPaymentPairError(int $d, int $p): ?string
                                {
                                    return null;
                                }
                            },
                            default => null,
                        };
                    }
                };
            }

            public function getOption($key, $options = null, $default = null, $skipEmpty = false)
            {
                return match ((string) $key) {
                    'ms3_status_draft' => 1,
                    'ms3_status_new' => 2,
                    default => $default,
                };
            }

            public function getLoginUserID($context = '')
            {
                return 0;
            }

            public function lexicon(string $key, array $params = []): string
            {
                return (string) $key;
            }

            public function log($level, $message): void
            {
            }

            public function newObject($className, $fields = [])
            {
                if ($className === msOrder::class) {
                    $id = $this->world['next_order_id']++;
                    $order = new RecordingMsOrder(array_merge([
                        'id' => $id,
                        'status_id' => 1,
                        'properties' => [],
                        'num' => null,
                        'uuid' => null,
                        'cart_cost' => 0,
                        'delivery_cost' => 0,
                        'cost' => 0,
                        'weight' => 0,
                    ], is_array($fields) ? $fields : []));
                    $order->set('id', $id);
                    $this->world['orders'][$id] = $order;

                    return $order;
                }

                if ($className === msOrderAddress::class) {
                    $address = new class ($this->world) {
                        /** @var array<string, mixed> */
                        private array $fields = [];

                        public function __construct(private array &$world)
                        {
                        }

                        public function set($k, $v)
                        {
                            $this->fields[$k] = $v;

                            return true;
                        }

                        public function get($k)
                        {
                            return $this->fields[$k] ?? null;
                        }

                        public function save($cacheFlag = null): bool
                        {
                            $this->world['addresses'][] = $this->fields;

                            return true;
                        }

                        public function remove(): bool
                        {
                            return true;
                        }
                    };

                    return $address;
                }

                if ($className === msOrderProduct::class) {
                    $product = new class ($this->world) {
                        /** @var array<string, mixed> */
                        private array $fields = [];

                        public function __construct(private array &$world)
                        {
                        }

                        public function set($k, $v)
                        {
                            $this->fields[$k] = $v;

                            return true;
                        }

                        public function get($k)
                        {
                            return $this->fields[$k] ?? null;
                        }

                        public function save($cacheFlag = null): bool
                        {
                            if (!empty($this->world['fail_product_save'])) {
                                return false;
                            }
                            // Assign id on first persist so RecordingMsOrder::save() skips re-saves.
                            if (empty($this->fields['id'])) {
                                $this->fields['id'] = $this->world['next_product_id']++;
                                $this->world['products'][] = $this->fields;
                                $orderId = (int) ($this->fields['order_id'] ?? 0);
                                if (isset($this->world['orders'][$orderId])) {
                                    $attached = $this->world['orders'][$orderId]->products;
                                    if (!in_array($this, $attached, true)) {
                                        $this->world['orders'][$orderId]->products[] = $this;
                                    }
                                }
                            }

                            return true;
                        }

                        public function remove(): bool
                        {
                            $this->world['products'] = array_values(array_filter(
                                $this->world['products'],
                                fn(array $row): bool => ($row['order_id'] ?? null) !== ($this->fields['order_id'] ?? null)
                                    || ($row['name'] ?? null) !== ($this->fields['name'] ?? null)
                            ));

                            return true;
                        }
                    };

                    return $product;
                }

                return parent::newObject($className, $fields);
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                if ($className === msOrder::class) {
                    if (is_array($criteria) && isset($criteria['idempotency_key'])) {
                        $key = (string) $criteria['idempotency_key'];
                        foreach ($this->world['orders'] as $order) {
                            if ((string) $order->get('idempotency_key') === $key) {
                                return $order;
                            }
                        }

                        return null;
                    }

                    $id = is_array($criteria) ? (int) ($criteria['id'] ?? 0) : (int) $criteria;

                    return $this->world['orders'][$id] ?? null;
                }

                if ($className === \MiniShop3\Model\msDelivery::class) {
                    return $this->delivery;
                }
                if ($className === \MiniShop3\Model\msPayment::class) {
                    return $this->payment;
                }

                return null;
            }

            public function getCollection($className, $criteria = null, $cacheFlag = true)
            {
                if ($className === msOrder::class) {
                    return $this->world['orders'];
                }

                return [];
            }

            public function getCount($className, $criteria = null)
            {
                if ($className === msOrderProduct::class) {
                    $orderId = is_array($criteria) ? (int) ($criteria['order_id'] ?? 0) : 0;

                    return count(array_filter(
                        $this->world['products'],
                        static fn(array $row): bool => (int) ($row['order_id'] ?? 0) === $orderId
                    ));
                }

                return 0;
            }

            public function getIterator($className, $criteria = null, $cacheFlag = true): \ArrayIterator
            {
                if ($className === msOrderProduct::class) {
                    $orderId = is_array($criteria) ? (int) ($criteria['order_id'] ?? 0) : 0;
                    $rows = array_filter(
                        $this->world['products'],
                        static fn(array $row): bool => $orderId === 0 || (int) ($row['order_id'] ?? 0) === $orderId
                    );

                    return new \ArrayIterator(array_map(static function (array $row) {
                        return new class ($row) {
                            public function __construct(private array $row)
                            {
                            }

                            public function get(string $field): mixed
                            {
                                return $this->row[$field] ?? null;
                            }
                        };
                    }, $rows));
                }

                return new \ArrayIterator([]);
            }

            public function newQuery($className, $criteria = null, $cacheFlag = true)
            {
                return new class {
                    public function where($criteria, $conjunction = 'AND'): self
                    {
                        return $this;
                    }

                    public function sortby($column, $direction = 'ASC'): self
                    {
                        return $this;
                    }

                    public function limit($limit, $offset = 0): self
                    {
                        return $this;
                    }
                };
            }
        };

        $numberGenerator = $this->createStub(OrderNumberGenerator::class);
        $numberGenerator->method('runWithNextNumber')->willReturnCallback(
            static function (callable $persist): string {
                $persist('2501/7');

                return '2501/7';
            }
        );

        $finalize = new OrderFinalizeService($modx, $ms3, $numberGenerator);

        $draftManager = new class ($modx, $ms3, $world) extends OrderDraftManager {
            public function __construct(
                modX $modx,
                MiniShop3 $ms3,
                private array &$world,
            ) {
                parent::__construct($modx, $ms3);
            }

            public function recalculate(msOrder $draft): void
            {
                $totals = OrderService::aggregateProductsTotals($draft->getMany('Products') ?? []);
                $draft->set('cart_cost', $totals['cart_cost']);
                $draft->set('weight', $totals['weight']);
                $draft->set(
                    'cost',
                    (float) $totals['cart_cost'] + (float) $draft->get('delivery_cost')
                );
                $draft->save();
            }

            public function deleteDraft(msOrder $draft): bool
            {
                $id = (int) $draft->get('id');
                unset($this->world['orders'][$id]);
                $this->world['products'] = array_values(array_filter(
                    $this->world['products'],
                    static fn(array $row): bool => (int) ($row['order_id'] ?? 0) !== $id
                ));
                $this->world['addresses'] = array_values(array_filter(
                    $this->world['addresses'],
                    static fn(array $row): bool => (int) ($row['order_id'] ?? 0) !== $id
                ));

                return true;
            }
        };

        return new ProgrammaticOrderService($modx, $ms3, $finalize, $draftManager);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeWorld(): array
    {
        return [
            'next_order_id' => 1,
            'next_product_id' => 1,
            'orders' => [],
            'products' => [],
            'addresses' => [],
            'fail_product_save' => false,
        ];
    }
}
