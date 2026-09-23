<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Events;

use MiniShop3\Services\Events\DomainEvent;
use MiniShop3\Services\Events\DomainEventCatalog;
use PHPUnit\Framework\TestCase;

final class DomainEventTest extends TestCase
{
    public function testCreateBuildsEnvelopeWithUuidAndUtcTimestamp(): void
    {
        $event = DomainEvent::create(DomainEventCatalog::ORDER_STATUS_CHANGED, [
            'order_id' => 10,
        ]);

        self::assertSame(DomainEventCatalog::ORDER_STATUS_CHANGED, $event->eventType());
        self::assertNotSame('', $event->eventId());
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $event->createdAt()
        );
        self::assertSame(['order_id' => 10], $event->data());
    }

    public function testToArrayAndJsonSerializeMatchEnvelopeShape(): void
    {
        $event = DomainEvent::create('order.status_changed', ['order_id' => 1]);
        $expected = [
            'event_id' => $event->eventId(),
            'event_type' => 'order.status_changed',
            'created_at' => $event->createdAt(),
            'data' => ['order_id' => 1],
        ];

        self::assertSame($expected, $event->toArray());
        self::assertSame($expected, $event->jsonSerialize());
    }

    public function testOrderStatusChangedUsesAllowlistKeysOnly(): void
    {
        $event = DomainEvent::orderStatusChanged(10, 'uuid-1', 2, 3, 100.0, 80.0, 20.0);

        self::assertSame(DomainEventCatalog::ORDER_STATUS_CHANGED, $event->eventType());
        self::assertSame([
            'order_id',
            'order_uuid',
            'old_status_id',
            'new_status_id',
            'cost',
            'cart_cost',
            'delivery_cost',
        ], array_keys($event->data()));
    }
}
