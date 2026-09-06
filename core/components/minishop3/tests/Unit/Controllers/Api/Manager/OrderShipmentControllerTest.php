<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Controllers\Api\Manager;

use MiniShop3\Controllers\Api\Manager\OrderShipmentController;
use MiniShop3\Model\msOrder;
use MiniShop3\Router\ApiErrorCode;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Services\Order\OrderStatusService;
use MiniShop3\Services\Shipment\ShipmentLifecycleService;
use MiniShop3\Services\Shipment\ShipmentStatus;
use MiniShop3\Tests\Stubs\StubMsOrder;
use MiniShop3\Tests\Support\InMemoryShipmentStore;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class OrderShipmentControllerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 4) . '/stubs/ModxStub.php';
        }
        require_once dirname(__DIR__, 4) . '/stubs/StubMsOrder.php';
        require_once dirname(__DIR__, 4) . '/support/InMemoryShipmentStore.php';
    }

    public function testGetMissingOrderIdIsBadRequest(): void
    {
        $data = (new OrderShipmentController($this->modx()))->get([]);

        self::assertFalse($data['success'] ?? true);
        self::assertSame(HttpStatus::BAD_REQUEST, $data['code'] ?? null);
        self::assertSame(ApiErrorCode::BAD_REQUEST, $data['error_code'] ?? null);
    }

    public function testGetMissingOrderIsNotFound(): void
    {
        $data = (new OrderShipmentController($this->modx()))->get(['id' => 99]);

        self::assertFalse($data['success'] ?? true);
        self::assertSame(HttpStatus::NOT_FOUND, $data['code'] ?? null);
    }

    public function testGetReturnsNullShipmentWhenNoneExists(): void
    {
        $data = (new OrderShipmentController($this->modxWithLifecycle()))->get(['id' => 10]);

        self::assertTrue($data['success'] ?? false);
        self::assertArrayHasKey('data', $data);
        self::assertIsArray($data['data']);
        self::assertArrayHasKey('shipment', $data['data']);
        self::assertNull($data['data']['shipment']);
        self::assertSame(ShipmentStatus::all(), $data['data']['statuses'] ?? null);
    }

    public function testSaveCreatesShipmentAndSetsTracking(): void
    {
        $data = (new OrderShipmentController($this->modxWithLifecycle()))->save([
            'id' => 10,
            'tracking_number' => 'TRACK-MGR',
            'status' => ShipmentStatus::PREPARING,
        ]);

        self::assertTrue($data['success'] ?? false);
        $shipment = $data['data']['shipment'] ?? [];
        self::assertSame('TRACK-MGR', $shipment['tracking_number'] ?? null);
        self::assertSame(ShipmentStatus::PREPARING, $shipment['status'] ?? null);
        self::assertArrayNotHasKey('meta', $shipment);
        self::assertArrayNotHasKey('provider', $shipment);
        self::assertArrayNotHasKey('external_id', $shipment);
    }

    public function testSaveConflictDoesNotLeakSecrets(): void
    {
        $modx = $this->modxWithLifecycle();
        $controller = new OrderShipmentController($modx);
        $controller->save(['id' => 10, 'status' => ShipmentStatus::SHIPPED]);
        $data = $controller->save(['id' => 10, 'status' => ShipmentStatus::PREPARING]);

        self::assertFalse($data['success'] ?? true);
        self::assertSame(HttpStatus::CONFLICT, $data['code'] ?? null);
        $payload = $data['data'] ?? null;
        if (is_array($payload)) {
            self::assertArrayNotHasKey('meta', $payload);
            self::assertArrayNotHasKey('provider', $payload);
        }
    }

    /**
     * @param array<string, mixed> $services
     */
    private function modx(?msOrder $order = null, array $services = []): modX
    {
        $order ??= new StubMsOrder(['id' => 10, 'delivery_id' => 7, 'status_id' => 3]);

        return new class ($order, $services) extends modX {
            /**
             * @param array<string, mixed> $map
             */
            public function __construct(private msOrder $order, private array $map)
            {
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

            public function getObject($className, $criteria = null)
            {
                if ($className !== msOrder::class) {
                    return null;
                }
                $id = is_array($criteria) ? (int) ($criteria['id'] ?? 0) : (int) $criteria;
                if ($id === (int) $this->order->get('id')) {
                    return $this->order;
                }

                return null;
            }
        };
    }

    private function modxWithLifecycle(): modX
    {
        $store = new InMemoryShipmentStore();
        $order = new StubMsOrder(['id' => 10, 'delivery_id' => 7, 'status_id' => 3]);
        $orderStatus = $this->createMock(OrderStatusService::class);
        $orderStatus->method('change')->willReturn(true);
        $lifecycle = new ShipmentLifecycleService($store, $this->modx($order), $orderStatus);

        return $this->modx($order, ['ms3_shipment_lifecycle' => $lifecycle]);
    }
}
