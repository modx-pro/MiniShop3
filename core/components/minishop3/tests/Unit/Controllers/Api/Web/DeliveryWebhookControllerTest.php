<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Controllers\Api\Web;

use MiniShop3\Controllers\Api\Web\DeliveryWebhookController;
use MiniShop3\Controllers\Delivery\DefaultDelivery;
use MiniShop3\Controllers\Delivery\DeliveryProviderInterface;
use MiniShop3\Controllers\Delivery\ShipmentProviderInterface;
use MiniShop3\Controllers\Delivery\ShipmentWebhookEvent;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;
use MiniShop3\Router\ApiErrorCode;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Services\Delivery\DeliveryService;
use MiniShop3\Services\Order\OrderStatusService;
use MiniShop3\Services\Shipment\ShipmentLifecycleService;
use MiniShop3\Services\Shipment\ShipmentStatus;
use MiniShop3\Tests\Stubs\StubMsDelivery;
use MiniShop3\Tests\Stubs\StubMsOrder;
use MiniShop3\Tests\Support\InMemoryShipmentStore;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;
use stdClass;

final class DeliveryWebhookControllerTest extends TestCase
{
    /** @var list<array{0: int, 1: int}> */
    private array $statusChanges = [];

    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 4) . '/stubs/ModxStub.php';
        }
        require_once dirname(__DIR__, 4) . '/stubs/StubMsDelivery.php';
        require_once dirname(__DIR__, 4) . '/stubs/StubMsOrder.php';
        require_once dirname(__DIR__, 4) . '/support/InMemoryShipmentStore.php';
        $this->statusChanges = [];
    }

    public function testDisabledReturnsNotFound(): void
    {
        $controller = new DeliveryWebhookController(new modX());
        $response = $controller->handle(['delivery_id' => 7]);
        $data = $response->getData();

        self::assertSame(HttpStatus::NOT_FOUND, $response->getStatusCode());
        self::assertIsArray($data);
        self::assertSame(ApiErrorCode::NOT_FOUND, $data['error_code'] ?? null);
    }

    public function testMissingDeliveryIdIsBadRequest(): void
    {
        $controller = new DeliveryWebhookController($this->enabledModx());
        $response = $controller->handle([]);
        $data = $response->getData();

        self::assertSame(HttpStatus::BAD_REQUEST, $response->getStatusCode());
        self::assertIsArray($data);
        self::assertSame(ApiErrorCode::BAD_REQUEST, $data['error_code'] ?? null);
    }

    public function testInvalidJsonIsBadRequest(): void
    {
        $controller = $this->controllerWithBody(
            $this->modxWithHandler($this->handler(true, null)),
            '{not-json'
        );
        $response = $controller->handle(['delivery_id' => 7]);
        $data = $response->getData();

        self::assertSame(HttpStatus::BAD_REQUEST, $response->getStatusCode());
        self::assertIsArray($data);
        self::assertSame(ApiErrorCode::BAD_REQUEST, $data['error_code'] ?? null);
    }

    public function testUnsupportedHandlerIsBadRequest(): void
    {
        $costOnly = $this->createMock(DeliveryProviderInterface::class);
        $controller = $this->controllerWithBody(
            $this->modxWithHandler($costOnly),
            '{"event":"shipped"}'
        );
        $response = $controller->handle(['delivery_id' => 7]);
        $data = $response->getData();

        self::assertSame(HttpStatus::BAD_REQUEST, $response->getStatusCode());
        self::assertIsArray($data);
        self::assertSame(ApiErrorCode::BAD_REQUEST, $data['error_code'] ?? null);
    }

    public function testBadSignatureIsUnauthorized(): void
    {
        $controller = $this->controllerWithBody(
            $this->modxWithHandler($this->handler(false, null)),
            '{"event":"shipped"}'
        );
        $response = $controller->handle(['delivery_id' => 7]);
        $data = $response->getData();

        self::assertSame(HttpStatus::UNAUTHORIZED, $response->getStatusCode());
        self::assertIsArray($data);
        self::assertSame(ApiErrorCode::UNAUTHORIZED, $data['error_code'] ?? null);
    }

    public function testInvalidPayloadIsBadRequest(): void
    {
        $controller = $this->controllerWithBody(
            $this->modxWithHandler($this->handler(true, null)),
            '{"event":"unknown"}'
        );
        $response = $controller->handle(['delivery_id' => 7]);
        $data = $response->getData();

        self::assertSame(HttpStatus::BAD_REQUEST, $response->getStatusCode());
        self::assertIsArray($data);
        self::assertSame(ApiErrorCode::BAD_REQUEST, $data['error_code'] ?? null);
    }

    public function testVerifyWebhookReceivesRawBody(): void
    {
        $seenRaw = new stdClass();
        $seenRaw->value = null;
        $body = '{"event":"shipped","order_id":10}';
        $handler = $this->handler(false, null, $seenRaw);
        $controller = $this->controllerWithBody($this->modxWithHandler($handler), $body);
        $controller->handle(['delivery_id' => 7]);

        self::assertSame($body, $seenRaw->value);
    }

    public function testShippedWebhookReturnsSuccessOnce(): void
    {
        $seenRaw = new stdClass();
        $seenRaw->value = null;
        $event = new ShipmentWebhookEvent(
            eventType: ShipmentStatus::SHIPPED,
            orderId: 10,
            trackingNumber: 'TRACK-9',
            providerEventId: 'hook-1',
        );
        $handler = $this->handler(true, $event, $seenRaw);
        $store = new InMemoryShipmentStore();
        $order = new StubMsOrder(['id' => 10, 'delivery_id' => 7, 'status_id' => 3]);
        $lifecycle = $this->lifecycle($store, $order);
        $body = '{"event":"shipped","order_id":10}';
        $controller = $this->controllerWithBody(
            $this->modxWithHandler($handler, $lifecycle, $order),
            $body
        );
        $first = $controller->handle(['delivery_id' => 7]);
        $second = $controller->handle(['delivery_id' => 7]);
        $data = $first->getData();

        self::assertSame(HttpStatus::OK, $first->getStatusCode());
        self::assertSame(HttpStatus::OK, $second->getStatusCode());
        self::assertIsArray($data);
        self::assertSame(ShipmentStatus::SHIPPED, $data['data']['status'] ?? null);
        self::assertSame('TRACK-9', $data['data']['tracking_number'] ?? null);
        self::assertSame($body, $seenRaw->value);
        self::assertSame([[10, 4]], $this->statusChanges);
    }

    public function testUnexpectedLifecycleErrorIsInternal(): void
    {
        $handler = $this->handler(
            true,
            new ShipmentWebhookEvent(eventType: ShipmentStatus::SHIPPED, orderId: 10)
        );
        $fakeLifecycle = new class {
            public function applyProviderEvent(ShipmentWebhookEvent $event, int $deliveryId, string $provider): array
            {
                throw new \RuntimeException('boom');
            }
        };
        $controller = $this->controllerWithBody(
            $this->modxWithHandler($handler, $fakeLifecycle),
            '{"event":"shipped"}'
        );
        $response = $controller->handle(['delivery_id' => 7]);
        $data = $response->getData();

        self::assertSame(HttpStatus::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertIsArray($data);
        self::assertSame(ApiErrorCode::INTERNAL_ERROR, $data['error_code'] ?? null);
    }

    public function testDefaultDeliveryDoesNotImplementShipmentContract(): void
    {
        self::assertFalse(
            is_subclass_of(DefaultDelivery::class, ShipmentProviderInterface::class)
        );
    }

    private function controllerWithBody(modX $modx, string $body): DeliveryWebhookController
    {
        return new class ($modx, $body) extends DeliveryWebhookController {
            public function __construct(modX $modx, private readonly string $raw)
            {
                parent::__construct($modx);
            }

            protected function readRawRequestBody(): string
            {
                return $this->raw;
            }
        };
    }

    private function lifecycle(InMemoryShipmentStore $store, msOrder $order): ShipmentLifecycleService
    {
        $orderStatus = $this->createMock(OrderStatusService::class);
        $orderStatus->method('change')->willReturnCallback(
            function (int $orderId, int $statusId): bool {
                $this->statusChanges[] = [$orderId, $statusId];

                return true;
            }
        );

        return new ShipmentLifecycleService($store, $this->enabledModx($order), $orderStatus);
    }

    private function handler(bool $ok, ?ShipmentWebhookEvent $event, ?stdClass $seenRaw = null): object
    {
        return new class ($ok, $event, $seenRaw) implements DeliveryProviderInterface, ShipmentProviderInterface {
            public function __construct(
                private bool $ok,
                private ?ShipmentWebhookEvent $event,
                private ?stdClass $seenRaw,
            ) {
            }

            public function getCost(msOrder $order, msDelivery $delivery, float $cost): float
            {
                return 0.0;
            }

            public function verifyWebhook(
                string $rawBody,
                array $payload,
                array $headers,
                msDelivery $method
            ): bool {
                if ($this->seenRaw !== null) {
                    $this->seenRaw->value = $rawBody;
                }

                return $this->ok;
            }

            public function parseWebhook(array $payload, array $headers): ?ShipmentWebhookEvent
            {
                return $this->event;
            }
        };
    }

    private function modxWithHandler(object $handler, ?object $lifecycle = null, ?msOrder $order = null): modX
    {
        $delivery = new StubMsDelivery(['id' => 7, 'class' => $handler::class, 'active' => 1]);
        $deliveryService = $this->createMock(DeliveryService::class);
        $deliveryService->method('loadDeliveryController')->willReturn($handler);

        return $this->enabledModx($order, [
            'ms3_delivery_service' => $deliveryService,
            'ms3_shipment_lifecycle' => $lifecycle,
        ], $delivery);
    }

    /**
     * @param array<string, mixed> $services
     */
    private function enabledModx(
        ?msOrder $order = null,
        array $services = [],
        ?msDelivery $delivery = null,
    ): modX {
        $delivery ??= new StubMsDelivery(['id' => 7, 'class' => 'Fake', 'active' => 1]);

        return new class ($order, $services, $delivery) extends modX {
            /**
             * @param array<string, mixed> $map
             */
            public function __construct(
                private ?msOrder $order,
                private array $map,
                private msDelivery $delivery,
            ) {
                parent::__construct();
                $this->services = new class ($this->map) {
                    /** @param array<string, mixed> $map */
                    public function __construct(private array $map)
                    {
                    }

                    public function has(string $key): bool
                    {
                        return array_key_exists($key, $this->map) || $key === 'ms3';
                    }

                    public function get(string $key): mixed
                    {
                        return $this->map[$key] ?? null;
                    }
                };
            }

            public function getOption(string $key, $options = null, $default = null)
            {
                return match ($key) {
                    'ms3_shipment_enabled' => true,
                    'ms3_status_sent' => 4,
                    'ms3_status_canceled' => 5,
                    default => $default,
                };
            }

            public function getObject($className, $criteria = null)
            {
                if ($className === msDelivery::class) {
                    return $this->delivery;
                }
                if ($className === msOrder::class && $this->order instanceof msOrder) {
                    if (is_array($criteria) && (int) ($criteria['id'] ?? 0) === (int) $this->order->get('id')) {
                        return $this->order;
                    }
                }

                return null;
            }
        };
    }
}
