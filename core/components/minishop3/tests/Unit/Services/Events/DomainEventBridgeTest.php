<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Events;

use MiniShop3\Services\Events\DomainEvent;
use MiniShop3\Services\Events\DomainEventBridge;
use MiniShop3\Services\Events\DomainEventListenerInterface;
use MiniShop3\Services\Events\NullWebhookDispatcher;
use MiniShop3\Services\Events\WebhookDispatcherInterface;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class DomainEventBridgeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testEmitDispatchesToListenerThenWebhookDispatcher(): void
    {
        $modx = new modX();
        $calls = [];
        $dispatcher = new class($calls) implements WebhookDispatcherInterface {
            /** @param list<string> $calls */
            public function __construct(private array &$calls)
            {
            }

            public function dispatch(DomainEvent $event): void
            {
                $this->calls[] = 'dispatcher:' . $event->eventType();
            }
        };
        $listener = new class($calls) implements DomainEventListenerInterface {
            /** @param list<string> $calls */
            public function __construct(private array &$calls)
            {
            }

            public function handle(DomainEvent $event): void
            {
                $this->calls[] = 'listener:' . $event->eventType();
            }
        };

        $bridge = new DomainEventBridge($modx, $dispatcher, [$listener]);
        $bridge->emit(DomainEvent::create('order.status_changed', ['order_id' => 1]));

        self::assertSame(
            ['listener:order.status_changed', 'dispatcher:order.status_changed'],
            $calls
        );
    }

    public function testListenerFailureIsLoggedAndDispatcherStillRuns(): void
    {
        $modx = $this->modxRecordingLogs();
        $dispatcherCalls = 0;
        $dispatcher = new class($dispatcherCalls) implements WebhookDispatcherInterface {
            public function __construct(private int &$dispatcherCalls)
            {
            }

            public function dispatch(DomainEvent $event): void
            {
                ++$this->dispatcherCalls;
            }
        };
        $listener = new class implements DomainEventListenerInterface {
            public function handle(DomainEvent $event): void
            {
                throw new \RuntimeException('listener boom');
            }
        };

        $bridge = new DomainEventBridge($modx, $dispatcher, [$listener]);
        $bridge->emit(DomainEvent::create('order.status_changed', []));

        self::assertSame(1, $dispatcherCalls);
        self::assertNotSame([], $modx->logs);
        self::assertStringContainsString('[DomainEventBridge] listener failed', $modx->logs[0]);
    }

    public function testDispatcherFailureIsLoggedWithoutRethrow(): void
    {
        $modx = $this->modxRecordingLogs();
        $dispatcher = new class implements WebhookDispatcherInterface {
            public function dispatch(DomainEvent $event): void
            {
                throw new \RuntimeException('dispatcher boom');
            }
        };

        $bridge = new DomainEventBridge($modx, $dispatcher, []);
        $bridge->emit(DomainEvent::create('order.status_changed', []));

        self::assertNotSame([], $modx->logs);
        self::assertStringContainsString('[DomainEventBridge] dispatcher failed', $modx->logs[0]);
    }

    public function testNullDispatcherIsNoOp(): void
    {
        $this->expectNotToPerformAssertions();

        $bridge = new DomainEventBridge(new modX(), new NullWebhookDispatcher(), []);
        $bridge->emit(DomainEvent::create('order.status_changed', []));
    }

    public function testAddListenerRegistersForSubsequentEmit(): void
    {
        $modx = new modX();
        $calls = [];
        $listener = new class($calls) implements DomainEventListenerInterface {
            /** @param list<string> $calls */
            public function __construct(private array &$calls)
            {
            }

            public function handle(DomainEvent $event): void
            {
                $this->calls[] = $event->eventType();
            }
        };

        $bridge = new DomainEventBridge($modx, new NullWebhookDispatcher(), []);
        $bridge->emit(DomainEvent::create('order.status_changed', []));
        self::assertSame([], $calls);

        $bridge->addListener($listener);
        $bridge->emit(DomainEvent::create('order.status_changed', []));

        self::assertSame(['order.status_changed'], $calls);
    }

    private function modxRecordingLogs(): modX
    {
        return new class extends modX {
            /** @var list<string> */
            public array $logs = [];

            public function log($level, $msg, $target = '', $def = '', $file = '', $line = '', $fields = []): void
            {
                $this->logs[] = (string) $msg;
            }
        };
    }
}
