<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Services\Events\DomainEvent;
use MiniShop3\Services\Events\DomainEventBridge;
use MiniShop3\Services\Events\DomainEventCatalog;
use MiniShop3\Services\Events\DomainEventListenerInterface;
use MiniShop3\Services\Events\NullWebhookDispatcher;
use MiniShop3\Services\Events\WebhookDispatcherInterface;
use MiniShop3\Services\Order\NullOrderLifecyclePorts;
use MiniShop3\Services\Order\OrderLifecyclePortsInterface;
use MiniShop3\Services\Order\OrderLogService;
use MiniShop3\Services\Order\OrderStatusService;
use MiniShop3\Tests\RecordingMsOrder;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Level-2: OrderStatusService gate — idempotency, allow-list, after-event, in-TX ports.
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

        $domainEvents = $this->recordingBridge(new modX());
        $service = $this->makeService($harness, $log, new NullOrderLifecyclePorts(), $events, domainEvents: $domainEvents->bridge);
        $result = $service->change(10, 2, true, ['idempotent' => true]);

        self::assertTrue($result);
        self::assertSame([], $log->entries);
        self::assertSame([], $events);
        self::assertSame([], $domainEvents->recorded);
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

    public function testAfterEventFailureKeepsCommittedStatusAndStillLogs(): void
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
        self::assertSame(3, $harness['order']->get('status_id'));
        self::assertSame([[10, 3, 'status']], $log->entries);
        self::assertSame(
            ['msOnBeforeChangeOrderStatus', 'msOnChangeOrderStatus'],
            $events
        );
    }

    public function testEnsureUsesIdempotentChangeForRaceSafeSameStatus(): void
    {
        $harness = $this->makeHarness(statusId: 2);
        $log = $this->recordingLog();
        $events = [];

        $service = $this->makeService($harness, $log, new NullOrderLifecyclePorts(), $events);
        $result = $service->ensure(10, 2);

        self::assertTrue($result);
        self::assertSame([], $log->entries);
        self::assertSame([], $events);
    }

    public function testAfterEventFailureStillAttemptsNotificationsWhenNotSkipped(): void
    {
        $harness = $this->makeHarness(statusId: 2);
        $log = $this->recordingLog();
        $events = [];

        $service = $this->makeService(
            $harness,
            $log,
            new NullOrderLifecyclePorts(),
            $events,
            afterFail: true,
            trackNotifications: true
        );
        $result = $service->change(10, 3, false);

        self::assertSame('after failed', $result);
        self::assertSame(3, $harness['order']->get('status_id'));
        self::assertContains('notification-sent', $events);
    }

    public function testEnsureSucceedsWhenChangeCommittedButAfterEventFailed(): void
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
        $result = $service->ensure(10, 3, true);

        self::assertTrue($result);
        self::assertSame(3, $harness['order']->get('status_id'));
        self::assertSame([[10, 3, 'status']], $log->entries);
    }

    public function testJoinsOuterTransactionWithoutBeginOrCommit(): void
    {
        $harness = $this->makeHarness(statusId: 2);
        $harness['tx'] = (object) [
            'outer' => true,
            'open' => false,
            'begins' => 0,
            'commits' => 0,
            'rollbacks' => 0,
        ];
        $log = $this->recordingLog();
        $events = [];
        $service = $this->makeService($harness, $log, new NullOrderLifecyclePorts(), $events);

        self::assertTrue($service->change(10, 3, true));
        self::assertSame(3, $harness['order']->get('status_id'));
        self::assertSame(0, $harness['tx']->begins);
        self::assertSame(0, $harness['tx']->commits);
        self::assertSame(0, $harness['tx']->rollbacks);
    }

    public function testOwnedTransactionRollsBackWhenPortDenies(): void
    {
        $harness = $this->makeHarness(statusId: 2);
        $harness['tx'] = (object) [
            'outer' => false,
            'open' => false,
            'begins' => 0,
            'commits' => 0,
            'rollbacks' => 0,
        ];
        $log = $this->recordingLog();
        $events = [];
        $ports = new class implements OrderLifecyclePortsInterface {
            public function onOrderBecamePaid(msOrder $order, ?int $previousStatusId): ?string
            {
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

        self::assertSame('port failed', $service->change(10, 3, true));
        self::assertSame(2, $harness['order']->get('status_id'));
        self::assertSame(1, $harness['tx']->begins);
        self::assertSame(0, $harness['tx']->commits);
        self::assertSame(1, $harness['tx']->rollbacks);
    }

    public function testPaidPortFailureAbortsBeforeSave(): void
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

    public function testSuccessfulStatusChangeEmitsOrderStatusChangedWithAllowlistPayload(): void
    {
        $harness = $this->makeHarness(statusId: 2);
        $log = $this->recordingLog();
        $events = [];
        $modx = new modX();
        $domainEvents = $this->recordingBridge($modx);

        $service = $this->makeService(
            $harness,
            $log,
            new NullOrderLifecyclePorts(),
            $events,
            domainEvents: $domainEvents->bridge
        );
        $result = $service->change(10, 3, true);

        self::assertTrue($result);
        self::assertCount(1, $domainEvents->recorded);
        $event = $domainEvents->recorded[0];
        self::assertSame(DomainEventCatalog::ORDER_STATUS_CHANGED, $event->eventType());
        self::assertSame([
            'order_id' => 10,
            'order_uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'old_status_id' => 2,
            'new_status_id' => 3,
            'cost' => 100.0,
            'cart_cost' => 80.0,
            'delivery_cost' => 20.0,
        ], $event->data());
        self::assertArrayNotHasKey('token', $event->data());
        self::assertArrayNotHasKey('properties', $event->data());
        self::assertArrayNotHasKey('idempotency_key', $event->data());
    }

    public function testEnsureSameStatusEmitsNothing(): void
    {
        $harness = $this->makeHarness(statusId: 2);
        $log = $this->recordingLog();
        $events = [];
        $domainEvents = $this->recordingBridge(new modX());

        $service = $this->makeService(
            $harness,
            $log,
            new NullOrderLifecyclePorts(),
            $events,
            domainEvents: $domainEvents->bridge
        );

        self::assertTrue($service->ensure(10, 2));
        self::assertSame([], $domainEvents->recorded);
    }

    public function testBeforeEventVetoEmitsNothing(): void
    {
        $harness = $this->makeHarness(statusId: 2);
        $log = $this->recordingLog();
        $events = [];
        $domainEvents = $this->recordingBridge(new modX());

        $service = $this->makeService(
            $harness,
            $log,
            new NullOrderLifecyclePorts(),
            $events,
            beforeFail: true,
            domainEvents: $domainEvents->bridge
        );

        self::assertSame('before failed', $service->change(10, 3, true));
        self::assertSame([], $domainEvents->recorded);
    }

    public function testPersistFailureEmitsNothing(): void
    {
        $harness = $this->makeHarness(statusId: 2);
        $log = $this->recordingLog();
        $events = [];
        $domainEvents = $this->recordingBridge(new modX());
        $ports = new class implements OrderLifecyclePortsInterface {
            public function onOrderBecamePaid(msOrder $order, ?int $previousStatusId): ?string
            {
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

        $service = $this->makeService($harness, $log, $ports, $events, domainEvents: $domainEvents->bridge);
        self::assertSame('port failed', $service->change(10, 3, true));
        self::assertSame([], $domainEvents->recorded);
    }

    public function testAfterEventFailureStillEmitsDomainEvent(): void
    {
        $harness = $this->makeHarness(statusId: 2);
        $log = $this->recordingLog();
        $events = [];
        $domainEvents = $this->recordingBridge(new modX());

        $service = $this->makeService(
            $harness,
            $log,
            new NullOrderLifecyclePorts(),
            $events,
            afterFail: true,
            domainEvents: $domainEvents->bridge
        );

        self::assertSame('after failed', $service->change(10, 3, true));
        self::assertCount(1, $domainEvents->recorded);
        self::assertSame(DomainEventCatalog::ORDER_STATUS_CHANGED, $domainEvents->recorded[0]->eventType());
    }

    public function testDispatcherFailureDoesNotChangeChangeReturnValue(): void
    {
        $harness = $this->makeHarness(statusId: 2);
        $log = $this->recordingLog();
        $events = [];
        $modx = new modX();
        $dispatcher = new class implements WebhookDispatcherInterface {
            public function dispatch(DomainEvent $event): void
            {
                throw new \RuntimeException('dispatch failed');
            }
        };
        $domainEvents = new DomainEventBridge($modx, $dispatcher, []);

        $service = $this->makeService(
            $harness,
            $log,
            new NullOrderLifecyclePorts(),
            $events,
            domainEvents: $domainEvents
        );

        self::assertTrue($service->change(10, 3, true));
    }

    /**
     * @param array<string, mixed> $options
     * @return array{order: RecordingMsOrder, statuses: array<int, object>, options: array<string, mixed>}
     */
    private function makeHarness(int $statusId, array $options = []): array
    {
        $order = new RecordingMsOrder([
            'id' => 10,
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'status_id' => $statusId,
            'context' => 'web',
            'cost' => 100.0,
            'cart_cost' => 80.0,
            'delivery_cost' => 20.0,
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
     * @return object{bridge: DomainEventBridge, recorded: list<DomainEvent>}
     */
    private function recordingBridge(modX $modx): object
    {
        /** @var list<DomainEvent> */
        $recorded = [];
        $listener = new class($recorded) implements DomainEventListenerInterface {
            /**
             * @param list<DomainEvent> $recorded
             */
            public function __construct(private array &$recorded)
            {
            }

            public function handle(DomainEvent $event): void
            {
                $this->recorded[] = $event;
            }
        };

        return (object) [
            'bridge' => new DomainEventBridge($modx, new NullWebhookDispatcher(), [$listener]),
            'recorded' => &$recorded,
        ];
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
        bool $afterFail = false,
        bool $beforeFail = false,
        bool $trackNotifications = false,
        ?DomainEventBridge $domainEvents = null,
    ): OrderStatusService {
        $modx = new class($harness, $afterFail, $beforeFail) extends modX {
            /** @var array{order: RecordingMsOrder, statuses: array<int, object>, options: array<string, mixed>, tx?: object} */
            private array $harness;
            private bool $afterFail;
            private bool $beforeFail;

            public function __construct(array $harness, bool $afterFail, bool $beforeFail)
            {
                parent::__construct();
                $this->harness = $harness;
                $this->afterFail = $afterFail;
                $this->beforeFail = $beforeFail;
                if (!isset($this->harness['tx'])) {
                    $this->harness['tx'] = (object) [
                        'outer' => false,
                        'open' => false,
                        'begins' => 0,
                        'commits' => 0,
                        'rollbacks' => 0,
                    ];
                }
            }

            public function inTransaction(): bool
            {
                return $this->harness['tx']->outer || $this->harness['tx']->open;
            }

            public function beginTransaction()
            {
                if ($this->inTransaction()) {
                    throw new \PDOException('There is already an active transaction');
                }
                $this->harness['tx']->begins++;
                $this->harness['tx']->open = true;

                return true;
            }

            public function commit()
            {
                $this->harness['tx']->commits++;
                $this->harness['tx']->open = false;

                return true;
            }

            public function rollback()
            {
                $this->harness['tx']->rollbacks++;
                $this->harness['tx']->open = false;

                return true;
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

        $utils = new class($events, $afterFail, $beforeFail) {
            /** @var list<string> */
            private array $events;
            private bool $afterFail;
            private bool $beforeFail;

            public function __construct(array &$events, bool $afterFail, bool $beforeFail)
            {
                $this->events = &$events;
                $this->afterFail = $afterFail;
                $this->beforeFail = $beforeFail;
            }

            public function invokeEvent(string $eventName, array $params = [], $glue = '<br/>'): array
            {
                $this->events[] = $eventName;
                if ($eventName === 'msOnBeforeChangeOrderStatus' && $this->beforeFail) {
                    return ['success' => false, 'message' => 'before failed', 'data' => []];
                }
                if ($eventName === 'msOnChangeOrderStatus' && $this->afterFail) {
                    return ['success' => false, 'message' => 'after failed', 'data' => []];
                }

                return ['success' => true, 'message' => '', 'data' => $params];
            }
        };
        $ms3->utils = $utils;

        $service = new class($modx, $ms3, $log, $ports, $events, $trackNotifications, $domainEvents) extends OrderStatusService {
            /** @var list<string> */
            private array $events;

            public function __construct(
                modX $modx,
                MiniShop3 $ms3,
                OrderLogService $orderLog,
                OrderLifecyclePortsInterface $lifecyclePorts,
                array &$events,
                private readonly bool $trackNotifications,
                ?DomainEventBridge $domainEvents,
            ) {
                parent::__construct($modx, $ms3, $orderLog, $lifecyclePorts, null, $domainEvents);
                $this->events = &$events;
            }

            protected function sendNotifications(
                msOrder $msOrder,
                msOrderStatus $newStatus,
                ?msOrderStatus $oldStatus
            ): void {
                if ($this->trackNotifications) {
                    $this->events[] = 'notification-sent';
                }
            }
        };

        return $service;
    }
}
