<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Shipment;

use MiniShop3\Controllers\Delivery\ShipmentWebhookEvent;
use MiniShop3\Model\msOrder;
use MiniShop3\Services\Order\OrderStatusService;
use MiniShop3\Services\Shipment\ShipmentLifecycleException;
use MiniShop3\Services\Shipment\ShipmentLifecycleService;
use MiniShop3\Services\Shipment\ShipmentPublicDto;
use MiniShop3\Services\Shipment\ShipmentStatus;
use MiniShop3\Tests\Stubs\StubMsOrder;
use MiniShop3\Tests\Support\InMemoryShipmentStore;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class ShipmentLifecycleServiceTest extends TestCase
{
    /** @var list<array{0: int, 1: int}> */
    private array $statusChanges = [];

    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
        require_once dirname(__DIR__, 3) . '/stubs/StubMsOrder.php';
        require_once dirname(__DIR__, 3) . '/support/InMemoryShipmentStore.php';
        $this->statusChanges = [];
    }

    public function testCreateSnapshotsDeliveryIdAndIsIdempotent(): void
    {
        $store = new InMemoryShipmentStore();
        $service = $this->service($store);
        $first = $service->create(10);
        $second = $service->create(10);

        self::assertSame(1, $first['id']);
        self::assertSame(7, $first['delivery_id']);
        self::assertSame(ShipmentStatus::PREPARING, $first['status']);
        self::assertSame($first['id'], $second['id']);
        self::assertSame([], $this->statusChanges);
    }

    public function testSetTrackingDoesNotChangeOrderStatus(): void
    {
        $store = new InMemoryShipmentStore();
        $service = $this->service($store);
        $row = $service->create(10);
        $updated = $service->setTracking($row['id'], 'TRACK-1');

        self::assertSame('TRACK-1', $updated['tracking_number']);
        self::assertSame(ShipmentStatus::PREPARING, $updated['status']);
        self::assertSame([], $this->statusChanges);
    }

    public function testShippedMapsToSentWhenEnabled(): void
    {
        $store = new InMemoryShipmentStore();
        $service = $this->service($store, enabled: true);
        $row = $service->create(10);
        $updated = $service->transition($row['id'], ShipmentStatus::SHIPPED, 'evt-1');

        self::assertSame(ShipmentStatus::SHIPPED, $updated['status']);
        self::assertNotNull($updated['shipped_at']);
        self::assertSame([[10, 4]], $this->statusChanges);

        $again = $service->transition($row['id'], ShipmentStatus::SHIPPED, 'evt-1');
        self::assertSame([[10, 4]], $this->statusChanges);
        self::assertSame($updated['id'], $again['id']);
    }

    public function testDeliveredDoesNotMapOrderStatusByDefault(): void
    {
        $store = new InMemoryShipmentStore();
        $service = $this->service($store, enabled: true);
        $row = $service->create(10);
        $service->transition($row['id'], ShipmentStatus::SHIPPED, 'evt-s');
        $this->statusChanges = [];
        $delivered = $service->transition($row['id'], ShipmentStatus::DELIVERED, 'evt-d');

        self::assertSame(ShipmentStatus::DELIVERED, $delivered['status']);
        self::assertSame([], $this->statusChanges);
    }

    public function testIllegalTransitionConflicts(): void
    {
        $store = new InMemoryShipmentStore();
        $service = $this->service($store);
        $row = $service->create(10);
        $this->expectException(ShipmentLifecycleException::class);
        $service->transition($row['id'], ShipmentStatus::DELIVERED);
    }

    public function testCancelledMapsToCanceledWhenEnabled(): void
    {
        $store = new InMemoryShipmentStore();
        $service = $this->service($store, enabled: true);
        $row = $service->create(10);
        $updated = $service->transition($row['id'], ShipmentStatus::CANCELLED, 'evt-c');

        self::assertSame(ShipmentStatus::CANCELLED, $updated['status']);
        self::assertSame([[10, 5]], $this->statusChanges);
    }

    public function testFailedMapsToCanceledWhenEnabled(): void
    {
        $store = new InMemoryShipmentStore();
        $service = $this->service($store, enabled: true);
        $row = $service->create(10);
        $updated = $service->transition($row['id'], ShipmentStatus::FAILED, 'evt-f');

        self::assertSame(ShipmentStatus::FAILED, $updated['status']);
        self::assertSame([[10, 5]], $this->statusChanges);
    }

    public function testOrderStatusFailureDoesNotRollBackShipment(): void
    {
        $store = new InMemoryShipmentStore();
        $order = new StubMsOrder(['id' => 10, 'delivery_id' => 7, 'status_id' => 3]);
        $modx = $this->modx($order, true);
        $orderStatus = $this->createMock(OrderStatusService::class);
        $orderStatus->method('change')->willReturn('ms3_err_status_final');
        $service = new ShipmentLifecycleService($store, $modx, $orderStatus);
        $row = $service->create(10);
        $updated = $service->transition($row['id'], ShipmentStatus::SHIPPED, 'evt-1');

        self::assertSame(ShipmentStatus::SHIPPED, $updated['status']);
        self::assertNotNull($updated['shipped_at']);
    }

    public function testInTransitDoesNotMapOrderStatusByDefault(): void
    {
        $store = new InMemoryShipmentStore();
        $service = $this->service($store, enabled: true);
        $row = $service->create(10);
        $service->transition($row['id'], ShipmentStatus::SHIPPED, 'evt-s');
        $this->statusChanges = [];
        $inTransit = $service->transition($row['id'], ShipmentStatus::IN_TRANSIT, 'evt-t');

        self::assertSame(ShipmentStatus::IN_TRANSIT, $inTransit['status']);
        self::assertSame([], $this->statusChanges);
    }

    public function testDisabledSkipOrderStatusSync(): void
    {
        $store = new InMemoryShipmentStore();
        $service = $this->service($store, enabled: false);
        $row = $service->create(10);
        $service->transition($row['id'], ShipmentStatus::SHIPPED, 'evt-1');

        self::assertSame([], $this->statusChanges);
    }

    public function testPublicDtoOmitsSecrets(): void
    {
        $dto = ShipmentPublicDto::fromRow([
            'id' => 1,
            'order_id' => 10,
            'delivery_id' => 7,
            'status' => ShipmentStatus::SHIPPED,
            'tracking_number' => 'T-1',
            'external_id' => 'ext',
            'provider' => 'Secret\\Class',
            'carrier' => 'Boxberry',
            'shipped_at' => 1,
            'delivered_at' => null,
            'last_event_id' => 'evt',
            'meta' => ['secret' => 'x', 'properties' => ['k' => 'v']],
            'createdon' => 1,
            'updatedon' => 1,
        ]);
        self::assertSame('T-1', $dto['tracking_number']);
        self::assertArrayNotHasKey('meta', $dto);
        self::assertArrayNotHasKey('provider', $dto);
        self::assertArrayNotHasKey('external_id', $dto);
    }

    public function testApplyProviderEventIsIdempotentAndStripsMetaSecrets(): void
    {
        $store = new InMemoryShipmentStore();
        $service = $this->service($store, enabled: true);
        $service->create(10);
        $event = new ShipmentWebhookEvent(
            eventType: ShipmentStatus::SHIPPED,
            orderId: 10,
            externalId: 'cdek-1',
            trackingNumber: 'TRACK-9',
            providerEventId: 'hook-1',
            payload: ['secret' => 'nope', 'city' => 'MSK'],
        );
        $first = $service->applyProviderEvent($event, 7, 'Cdek');
        $second = $service->applyProviderEvent($event, 7, 'Cdek');

        self::assertSame('TRACK-9', $first['tracking_number']);
        self::assertSame('cdek-1', $first['external_id']);
        self::assertArrayNotHasKey('secret', $first['meta']);
        self::assertSame('MSK', $first['meta']['city']);
        self::assertSame([[10, 4]], $this->statusChanges);
        self::assertSame($first['id'], $second['id']);
        self::assertSame([[10, 4]], $this->statusChanges);
    }

    public function testIllegalProviderEventDoesNotPersistTracking(): void
    {
        $store = new InMemoryShipmentStore();
        $service = $this->service($store, enabled: true);
        $service->create(10);
        try {
            $service->applyProviderEvent(new ShipmentWebhookEvent(
                eventType: ShipmentStatus::DELIVERED,
                orderId: 10,
                trackingNumber: 'TRACK-X',
                providerEventId: 'hook-bad',
                payload: ['city' => 'MSK'],
            ), 7, 'Cdek');
            self::fail('expected conflict');
        } catch (ShipmentLifecycleException $exception) {
            self::assertSame(ShipmentLifecycleException::KIND_CONFLICT, $exception->getKind());
        }
        $row = $store->findByOrderId(10);
        self::assertNotNull($row);
        self::assertSame(ShipmentStatus::PREPARING, $row['status']);
        self::assertNull($row['tracking_number']);
        self::assertSame([], $row['meta']);
        self::assertSame([], $this->statusChanges);
    }

    public function testIllegalFirstWebhookDoesNotCreateShipment(): void
    {
        $store = new InMemoryShipmentStore();
        $service = $this->service($store, enabled: true);
        try {
            $service->applyProviderEvent(new ShipmentWebhookEvent(
                eventType: ShipmentStatus::DELIVERED,
                orderId: 10,
                trackingNumber: 'TRACK-X',
            ), 7, 'Cdek');
            self::fail('expected conflict');
        } catch (ShipmentLifecycleException $exception) {
            self::assertSame(ShipmentLifecycleException::KIND_CONFLICT, $exception->getKind());
        }
        self::assertNull($store->findByOrderId(10));
        self::assertSame([], $this->statusChanges);
    }

    public function testWebhookDeliveryMismatchDoesNotCreateShipment(): void
    {
        $store = new InMemoryShipmentStore();
        $order = new StubMsOrder(['id' => 10, 'delivery_id' => 5, 'status_id' => 3]);
        $orderStatus = $this->createMock(OrderStatusService::class);
        $orderStatus->method('change')->willReturnCallback(
            function (int $orderId, int $statusId): bool {
                $this->statusChanges[] = [$orderId, $statusId];

                return true;
            }
        );
        $service = new ShipmentLifecycleService($store, $this->modx($order, true), $orderStatus);
        try {
            $service->applyProviderEvent(new ShipmentWebhookEvent(
                eventType: ShipmentStatus::SHIPPED,
                orderId: 10,
                trackingNumber: 'TRACK-X',
                providerEventId: 'hook-x',
            ), 7, 'Cdek');
            self::fail('expected conflict');
        } catch (ShipmentLifecycleException $exception) {
            self::assertSame(ShipmentLifecycleException::KIND_CONFLICT, $exception->getKind());
        }
        self::assertNull($store->findByOrderId(10));
        self::assertSame([], $this->statusChanges);
    }

    private function service(InMemoryShipmentStore $store, bool $enabled = false): ShipmentLifecycleService
    {
        $order = new StubMsOrder(['id' => 10, 'delivery_id' => 7, 'status_id' => 3]);
        $orderStatus = $this->createMock(OrderStatusService::class);
        $orderStatus->method('change')->willReturnCallback(
            function (int $orderId, int $statusId): bool {
                $this->statusChanges[] = [$orderId, $statusId];

                return true;
            }
        );

        return new ShipmentLifecycleService($store, $this->modx($order, $enabled), $orderStatus);
    }

    private function modx(msOrder $order, bool $enabled): modX
    {
        return new class ($order, $enabled) extends modX {
            public function __construct(private msOrder $order, private bool $enabled)
            {
                parent::__construct();
            }

            public function getOption(string $key, $options = null, $default = null)
            {
                return match ($key) {
                    'ms3_shipment_enabled' => $this->enabled,
                    'ms3_status_sent' => 4,
                    'ms3_status_canceled' => 5,
                    default => $default,
                };
            }

            public function getObject($className, $criteria = null)
            {
                if ($className !== msOrder::class) {
                    return null;
                }
                if (is_array($criteria) && (int) ($criteria['id'] ?? 0) === (int) $this->order->get('id')) {
                    return $this->order;
                }

                return null;
            }
        };
    }
}
