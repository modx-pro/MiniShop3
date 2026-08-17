<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Services\Order\NullOrderLifecyclePorts;
use MiniShop3\Services\Order\OrderLifecyclePortsInterface;
use MiniShop3\Services\Order\OrderLogService;
use MiniShop3\Services\Order\OrderStatusService;
use MiniShop3\Tests\RecordingMsOrder;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Level-2: OrderStatusService gate — idempotency, allow-list, after-event rollback, ports.
 */
final class OrderStatusServiceLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testIdempotentSameStatusReturnsTrueWithoutSideEffects(): void
    {
        $harness = $this->makeHarness(statusId: 2);
        $log = $this->recordingLog();
        $events = [];

        $service = $this->makeService($harness, $log, new NullOrderLifecyclePorts(), $events);
        $result = $service->change(10, 2, true, ['idempotent' => true]);

        self::assertTrue($result);
        self::assertSame([], $log->entries);
        self::assertSame([], $events);
    }

    public function testSameStatusWithoutIdempotentReturnsError(): void
    {
        $harness = $this->makeHarness(statusId: 2);
        $log = $this->recordingLog();
        $events = [];

        $service = $this->makeService($harness, $log, new NullOrderLifecyclePorts(), $events);
        $result = $service->change(10, 2);

        self::assertSame('ms3_err_status_same', $result);
        self::assertSame([], $log->entries);
    }

    public function testAllowListRejectsUnknownEdgeWithoutSave(): void
    {
        $harness = $this->makeHarness(statusId: 2, options: [
            'ms3_order_status_transitions' => '2:3',
        ]);
        $log = $this->recordingLog();
        $events = [];

        $service = $this->makeService($harness, $log, new NullOrderLifecyclePorts(), $events);
        $result = $service->change(10, 4);

        self::assertSame('ms3_err_status_transition', $result);
        self::assertSame(2, $harness['order']->get('status_id'));
        self::assertSame([], $log->entries);
        self::assertSame([], $events);
    }

    public function testInvalidAllowListConfigRejectedWithoutSave(): void
    {
        $harness = $this->makeHarness(statusId: 2, options: [
            'ms3_order_status_transitions' => '[broken',
        ]);
        $log = $this->recordingLog();
        $events = [];

        $service = $this->makeService($harness, $log, new NullOrderLifecyclePorts(), $events);
        $result = $service->change(10, 3);

        self::assertSame('ms3_err_status_transitions_invalid', $result);
        self::assertSame(2, $harness['order']->get('status_id'));
        self::assertSame([], $log->entries);
    }

    public function testCancelAndShippedPortsAreInvoked(): void
    {
        $ports = new class implements OrderLifecyclePortsInterface {
            public int $cancelCalls = 0;
            public int $shipCalls = 0;

            public function onOrderBecamePaid(msOrder $order, ?int $previousStatusId): ?string
            {
                return null;
            }

            public function onOrderCancelled(msOrder $order, ?int $previousStatusId): ?string
            {
                ++$this->cancelCalls;

                return null;
            }

            public function onOrderShipped(msOrder $order, ?int $previousStatusId): ?string
            {
                ++$this->shipCalls;

                return null;
            }
        };

        $cancelHarness = $this->makeHarness(statusId: 2);
        $cancelLog = $this->recordingLog();
        $cancelEvents = [];
        $cancelService = $this->makeService($cancelHarness, $cancelLog, $ports, $cancelEvents);
        self::assertTrue($cancelService->change(10, 5, true));
        self::assertSame(1, $ports->cancelCalls);

        $shipHarness = $this->makeHarness(statusId: 2);
        $shipLog = $this->recordingLog();
        $shipEvents = [];
        $shipService = $this->makeService($shipHarness, $shipLog, $ports, $shipEvents);
        self::assertTrue($shipService->change(10, 4, true));
        self::assertSame(1, $ports->shipCalls);
    }

    public function testAfterEventFailureRollsBackStatusAndSkipsLog(): void
    {
        $harness = $this->makeHarness(statusId: 2);
        $log = $this->recordingLog();
        $events = [];

        $service = $this->makeService(
            $harness,
            $log,
            new NullOrderLifecyclePorts(),
            $events,
            afterFail: true
        );
        $result = $service->change(10, 3, true);

        self::assertSame('after failed', $result);
        self::assertSame(2, $harness['order']->get('status_id'));
        self::assertSame([], $log->entries);
        self::assertSame(['msOnBeforeChangeOrderStatus', 'msOnChangeOrderStatus'], $events);
    }

    public function testPaidPortRunsAndFailureRollsBack(): void
    {
        $harness = $this->makeHarness(statusId: 2);
        $log = $this->recordingLog();
        $events = [];
        $ports = new class implements OrderLifecyclePortsInterface {
            public int $paidCalls = 0;

            public function onOrderBecamePaid(msOrder $order, ?int $previousStatusId): ?string
            {
                ++$this->paidCalls;

                return 'port failed';
            }

            public function onOrderCancelled(msOrder $order, ?int $previousStatusId): ?string
            {
                return null;
            }

            public function onOrderShipped(msOrder $order, ?int $previousStatusId): ?string
            {
                return null;
            }
        };

        $service = $this->makeService($harness, $log, $ports, $events);
        $result = $service->change(10, 3, true);

        self::assertSame('port failed', $result);
        self::assertSame(1, $ports->paidCalls);
        self::assertSame(2, $harness['order']->get('status_id'));
        self::assertSame([], $log->entries);
        self::assertSame(['msOnBeforeChangeOrderStatus'], $events);
    }

    public function testSuccessfulPaidTransitionLogsAndInvokesPort(): void
    {
        $harness = $this->makeHarness(statusId: 2);
        $log = $this->recordingLog();
        $events = [];
        $ports = new class implements OrderLifecyclePortsInterface {
            public int $paidCalls = 0;

            public function onOrderBecamePaid(msOrder $order, ?int $previousStatusId): ?string
            {
                ++$this->paidCalls;

                return null;
            }

            public function onOrderCancelled(msOrder $order, ?int $previousStatusId): ?string
            {
                return null;
            }

            public function onOrderShipped(msOrder $order, ?int $previousStatusId): ?string
            {
                return null;
            }
        };

        $service = $this->makeService($harness, $log, $ports, $events);
        $result = $service->change(10, 3, true);

        self::assertTrue($result);
        self::assertSame(1, $ports->paidCalls);
        self::assertSame(3, $harness['order']->get('status_id'));
        self::assertSame([[10, 3, 'status']], $log->entries);
        self::assertSame(['msOnBeforeChangeOrderStatus', 'msOnChangeOrderStatus'], $events);
    }

    /**
     * @param array<string, mixed> $options
     * @return array{order: RecordingMsOrder, statuses: array<int, object>, options: array<string, mixed>}
     */
    private function makeHarness(int $statusId, array $options = []): array
    {
        $order = new RecordingMsOrder([
            'id' => 10,
            'status_id' => $statusId,
            'context' => 'web',
        ]);

        $statuses = [
            2 => $this->makeStatus(2, final: false, fixed: false, position: 2),
            3 => $this->makeStatus(3, final: false, fixed: true, position: 3),
            4 => $this->makeStatus(4, final: true, fixed: true, position: 4),
            5 => $this->makeStatus(5, final: true, fixed: false, position: 5),
        ];

        return [
            'order' => $order,
            'statuses' => $statuses,
            'options' => array_merge([
                'ms3_status_paid' => 3,
                'ms3_status_canceled' => 5,
                'ms3_status_sent' => 4,
                'ms3_order_status_transitions' => '',
            ], $options),
        ];
    }

    private function makeStatus(int $id, bool $final, bool $fixed, int $position): object
    {
        return new class($id, $final, $fixed, $position) extends msOrderStatus {
            public function __construct(
                private int $statusId,
                private bool $isFinal,
                private bool $isFixed,
                private int $pos
            ) {
            }

            public function get($k, $format = null, $formatType = '')
            {
                return match ($k) {
                    'id' => $this->statusId,
                    'final' => $this->isFinal ? 1 : 0,
                    'fixed' => $this->isFixed ? 1 : 0,
                    'position' => $this->pos,
                    default => null,
                };
            }
        };
    }

    /**
     * @return OrderLogService&object{entries: list<array{0:int,1:int,2:string}>}
     */
    private function recordingLog(): OrderLogService
    {
        return new class extends OrderLogService {
            /** @var list<array{0:int,1:int,2:string}> */
            public array $entries = [];

            public function __construct()
            {
            }

            public function add(int $order_id, mixed $entry, string $action, bool $visible = true): bool
            {
                $this->entries[] = [$order_id, $entry, $action];

                return true;
            }
        };
    }

    /**
     * @param array{order: RecordingMsOrder, statuses: array<int, object>, options: array<string, mixed>} $harness
     * @param list<string> $events
     */
    private function makeService(
        array $harness,
        OrderLogService $log,
        OrderLifecyclePortsInterface $ports,
        array &$events,
        bool $afterFail = false
    ): OrderStatusService {
        $modx = new class($harness, $afterFail) extends modX {
            /** @var array{order: RecordingMsOrder, statuses: array<int, object>, options: array<string, mixed>} */
            private array $harness;
            private bool $afterFail;

            public function __construct(array $harness, bool $afterFail)
            {
                parent::__construct();
                $this->harness = $harness;
                $this->afterFail = $afterFail;
            }

            public function getOption(string $key, $options = null, $default = null)
            {
                return $this->harness['options'][$key] ?? $default;
            }

            public function switchContext($contextKey, $force = false)
            {
                return true;
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                if ($className === msOrder::class || $className === RecordingMsOrder::class) {
                    $id = is_array($criteria) ? (int) ($criteria['id'] ?? 0) : (int) $criteria;
                    return $id === (int) $this->harness['order']->get('id')
                        ? $this->harness['order']
                        : null;
                }

                if ($className === msOrderStatus::class || is_a($className, msOrderStatus::class, true)) {
                    $id = is_array($criteria) ? (int) ($criteria['id'] ?? 0) : (int) $criteria;
                    return $this->harness['statuses'][$id] ?? null;
                }

                return null;
            }
        };

        $ms3 = $this->createMock(MiniShop3::class);
        $ms3->method('initialize')->willReturn(true);

        $utils = new class($events, $afterFail) {
            /** @var list<string> */
            private array $events;
            private bool $afterFail;

            public function __construct(array &$events, bool $afterFail)
            {
                $this->events = &$events;
                $this->afterFail = $afterFail;
            }

            public function invokeEvent(string $eventName, array $params = [], $glue = '<br/>'): array
            {
                $this->events[] = $eventName;
                if ($eventName === 'msOnChangeOrderStatus' && $this->afterFail) {
                    return ['success' => false, 'message' => 'after failed', 'data' => []];
                }

                return ['success' => true, 'message' => '', 'data' => $params];
            }
        };
        $ms3->utils = $utils;

        return new OrderStatusService($modx, $ms3, $log, $ports);
    }
}
