<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msPayment;
use MiniShop3\Services\Delivery\DeliveryService;
use MiniShop3\Services\Order\OrderFinalizeService;
use MiniShop3\Services\Order\OrderNumberGenerator;
use MiniShop3\Services\Order\OrderService;
use MiniShop3\Tests\RecordingMsOrder;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;
use xPDO\Om\xPDOSimpleObject;

/**
 * Level-2: OrderFinalizeService::finalize with Fake modX / utils / status service.
 */
final class OrderFinalizeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }
    }

    public function testFinalizeReturnsNotFound(): void
    {
        $service = $this->makeService(orders: []);
        $result = $service->finalize(99);

        self::assertFalse($result['success']);
        self::assertSame('ms3_order_err_nf', $result['message']);
    }

    public function testFinalizeRejectsNonDraft(): void
    {
        $order = new RecordingMsOrder(['id' => 5, 'status_id' => 2]);
        $service = $this->makeService(orders: [5 => $order]);
        $result = $service->finalize(5);

        self::assertFalse($result['success']);
        self::assertSame('ms3_order_err_already_finalized', $result['message']);
    }

    public function testFinalizeValidationFailsWithoutProducts(): void
    {
        $order = new RecordingMsOrder([
            'id' => 5,
            'status_id' => 1,
            'delivery_id' => 0,
            'payment_id' => 0,
        ]);
        $service = $this->makeService(orders: [5 => $order], productCount: 0);
        $result = $service->finalize(5);

        self::assertFalse($result['success']);
        self::assertSame('ms3_order_err_validation', $result['message']);
        self::assertContains('products', $result['data']);
        self::assertContains('delivery_id', $result['data']);
        self::assertContains('payment_id', $result['data']);
    }


    public function testFinalizeFailsOnMissingDeliveryRequiredFields(): void
    {
        $order = new RecordingMsOrder([
            'id' => 5,
            'status_id' => 1,
            'delivery_id' => 3,
            'payment_id' => 4,
        ]);
        $products = [
            new class {
                public function get(string $field): float|int
                {
                    return match ($field) {
                        'cost' => 10.0,
                        'weight' => 1.0,
                        'count' => 1,
                        default => 0,
                    };
                }
            },
        ];
        $delivery = $this->makeDeliveryStub([
            'price' => 0.0,
            'weight_price' => 0.0,
            'active' => 1,
            'class' => '',
            'free_delivery_amount' => 0,
            'validation_rules' => json_encode([
                'email' => 'required|email',
                'phone' => 'required',
                'city' => 'max:100',
            ]),
        ]);
        $address = new class extends xPDOSimpleObject {
            public function toArray($keyPrefix = '', $rawValues = false, $excludeLazy = false, $includeRelated = false)
            {
                return [
                    'order_id' => 5,
                    'email' => '',
                    'phone' => '',
                    'city' => 'Moscow',
                ];
            }
        };

        $service = $this->makeService(
            orders: [5 => $order],
            productCount: 1,
            products: $products,
            delivery: $delivery,
            address: $address,
        );
        $result = $service->finalize(5);

        self::assertFalse($result['success']);
        self::assertSame('ms3_order_err_validation', $result['message']);
        self::assertContains('email', $result['data']);
        self::assertContains('phone', $result['data']);
        self::assertNotContains('city', $result['data']);
    }

    public function testFinalizeHappyPathWithoutSkipValidation(): void
    {
        $order = new RecordingMsOrder([
            'id' => 5,
            'status_id' => 1,
            'delivery_id' => 3,
            'payment_id' => 4,
            'num' => '2501/7',
            'customer_id' => 0,
            'cost' => 0,
            'cart_cost' => 0,
            'delivery_cost' => 0,
            'weight' => 0,
        ]);
        $products = [
            new class {
                public function get(string $field): float|int
                {
                    return match ($field) {
                        'cost' => 40.0,
                        'weight' => 0.5,
                        'count' => 2,
                        default => 0,
                    };
                }
            },
        ];
        $delivery = $this->makeDeliveryStub([
            'price' => 20.0,
            'weight_price' => 0.0,
            'active' => 1,
            'class' => '',
            'free_delivery_amount' => 0,
            'validation_rules' => json_encode([
                'email' => 'required|email',
                'receiver' => 'required',
            ]),
        ]);
        $address = new class extends xPDOSimpleObject {
            public function toArray($keyPrefix = '', $rawValues = false, $excludeLazy = false, $includeRelated = false)
            {
                return [
                    'order_id' => 5,
                    'email' => 'buyer@example.com',
                    'receiver' => 'Ivan',
                ];
            }
        };

        $statusCalls = [];
        $service = $this->makeService(
            orders: [5 => $order],
            productCount: 1,
            products: $products,
            delivery: $delivery,
            address: $address,
            onStatusChange: static function (int $id, int $status, bool $skip) use (&$statusCalls, $order): bool {
                $statusCalls[] = compact('id', 'status', 'skip');
                $order->set('status_id', $status);

                return true;
            }
        );

        $result = $service->finalize(5, ['skip_notifications' => true]);

        self::assertTrue($result['success'], $result['message'] ?? '');
        self::assertSame('ms3_order_finalized', $result['message']);
        self::assertSame(5, $result['data']['order_id']);
        self::assertSame('2501/7', $result['data']['order_num']);
        self::assertSame(2, $result['data']['status_id']);
        self::assertSame(40.0, $order->get('cart_cost'));
        self::assertSame(20.0, $order->get('delivery_cost'));
        self::assertSame(60.0, $order->get('cost'));
        self::assertSame(1.0, $order->get('weight'));
        self::assertCount(1, $statusCalls);
        self::assertTrue($statusCalls[0]['skip']);
    }

    public function testFinalizeHappyPathWithSkipValidation(): void
    {
        $order = new RecordingMsOrder([
            'id' => 5,
            'status_id' => 1,
            'delivery_id' => 3,
            'payment_id' => 4,
            'num' => '2501/1',
            'customer_id' => 0,
            'cost' => 0,
            'cart_cost' => 0,
            'delivery_cost' => 0,
            'weight' => 0,
        ]);

        $products = [
            new class {
                public function get(string $field): float|int
                {
                    return match ($field) {
                        'cost' => 100.0,
                        'weight' => 1.0,
                        'count' => 2,
                        default => 0,
                    };
                }
            },
        ];

        $delivery = $this->makeDeliveryStub([
            'price' => 50.0,
            'weight_price' => 10.0,
            'active' => 1,
            'class' => '',
            'free_delivery_amount' => 0,
        ]);

        $statusCalls = [];
        $service = $this->makeService(
            orders: [5 => $order],
            products: $products,
            delivery: $delivery,
            onStatusChange: static function (int $id, int $status, bool $skip) use (&$statusCalls, $order): bool {
                $statusCalls[] = compact('id', 'status', 'skip');
                $order->set('status_id', $status);

                return true;
            }
        );

        $result = $service->finalize(5, [
            'skip_validation' => true,
            'skip_notifications' => true,
        ]);

        self::assertTrue($result['success']);
        self::assertSame('ms3_order_finalized', $result['message']);
        self::assertSame(5, $result['data']['order_id']);
        self::assertSame('2501/1', $result['data']['order_num']);
        self::assertSame(2, $result['data']['status_id']);
        // aggregateProductsTotals sums line `cost` (already extended), weight × count.
        self::assertSame(100.0, $order->get('cart_cost'));
        self::assertSame(70.0, $order->get('delivery_cost')); // 50 + 2*1.0*10
        self::assertSame(170.0, $order->get('cost'));
        self::assertSame(2.0, $order->get('weight'));
        self::assertCount(1, $statusCalls);
        self::assertSame(2, $statusCalls[0]['status']);
        self::assertTrue($statusCalls[0]['skip']);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function makeDeliveryStub(array $fields): msDelivery
    {
        $delivery = $this->createStub(msDelivery::class);
        $delivery->method('get')->willReturnCallback(static function (string $k) use ($fields) {
            return $fields[$k] ?? null;
        });

        return $delivery;
    }

    /**
     * @param array<int, RecordingMsOrder> $orders
     * @param list<object> $products
     */
    private function makeService(
        array $orders,
        int $productCount = 0,
        array $products = [],
        ?object $delivery = null,
        ?object $address = null,
        ?callable $onStatusChange = null,
    ): OrderFinalizeService {
        $orderService = new OrderService(new modX());
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

        $ms3 = $this->createStub(MiniShop3::class);
        $ms3->utils = $utils;

        $statusService = new class ($onStatusChange) {
            public function __construct(private mixed $onStatusChange)
            {
            }

            public function change(int $orderId, int $statusId, bool $writeLog = true): true|string
            {
                if (is_callable($this->onStatusChange)) {
                    ($this->onStatusChange)($orderId, $statusId, $writeLog);

                    return true;
                }

                return true;
            }
        };

        $payment = $this->createStub(msPayment::class);
        $payment->method('get')->willReturnCallback(static function (string $k) {
            return match ($k) {
                'active' => 1,
                'class' => '',
                'price' => 0,
                default => null,
            };
        });

        $deliveryService = $this->createStub(DeliveryService::class);
        $deliveryService->method('getDeliveryPaymentPairError')->willReturn(null);

        $modx = new class (
            $orders,
            $productCount,
            $products,
            $delivery,
            $payment,
            $address,
            $orderService,
            $statusService,
            $deliveryService,
        ) extends modX {

            /**
             * @param array<int, RecordingMsOrder> $orders
             * @param list<object> $products
             */
            public function __construct(
                private array $orders,
                private int $productCount,
                private array $products,
                private ?object $delivery,
                private object $payment,
                private ?object $address,
                OrderService $orderService,
                object $statusService,
                DeliveryService $deliveryService,
            ) {
                parent::__construct();
                $this->services = new class ($orderService, $statusService, $deliveryService) {
                    public function __construct(
                        private OrderService $orderService,
                        private object $statusService,
                        private DeliveryService $deliveryService,
                    ) {
                    }

                    public function has(string $key): bool
                    {
                        return in_array($key, [
                            'ms3_order_service',
                            'ms3_order_status',
                            'ms3_delivery_service',
                        ], true);
                    }

                    public function get(string $key): mixed
                    {
                        return match ($key) {
                            'ms3_order_service' => $this->orderService,
                            'ms3_order_status' => $this->statusService,
                            'ms3_delivery_service' => $this->deliveryService,
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

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                if ($className === msOrder::class) {
                    $id = is_array($criteria) ? (int) ($criteria['id'] ?? 0) : (int) $criteria;
                    if ($id === 0 && is_numeric($criteria)) {
                        $id = (int) $criteria;
                    }
                    if (!is_array($criteria) && is_numeric($criteria)) {
                        $id = (int) $criteria;
                    }

                    return $this->orders[$id] ?? null;
                }

                if ($className === msDelivery::class) {
                    return $this->delivery;
                }

                if ($className === msPayment::class) {
                    return $this->payment;
                }

                if ($className === msOrderAddress::class) {
                    return $this->address;
                }

                return null;
            }

            public function getCount($className, $criteria = null)
            {
                if ($className === msOrderProduct::class) {
                    return $this->productCount;
                }

                return 0;
            }

            public function getIterator($className, $criteria = null, $cacheFlag = true): \ArrayIterator
            {
                if ($className === msOrderProduct::class) {
                    return new \ArrayIterator($this->products);
                }

                return new \ArrayIterator([]);
            }

            public function lexicon(string $key, array $params = []): string
            {
                return (string) $key;
            }
        };

        $numberGenerator = $this->createStub(OrderNumberGenerator::class);
        $numberGenerator->method('runWithNextNumber')->willReturnCallback(
            static function (callable $persist): string {
                $persist('2501/99');

                return '2501/99';
            }
        );

        return new OrderFinalizeService($modx, $ms3, $numberGenerator);
    }
}
