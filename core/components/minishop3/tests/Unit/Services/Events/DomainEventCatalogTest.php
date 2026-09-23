<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Events;

use MiniShop3\Services\Events\DomainEventCatalog;
use PHPUnit\Framework\TestCase;

final class DomainEventCatalogTest extends TestCase
{
    public function testOrderStatusChangedBindingUsesRealModxEvent(): void
    {
        $bindings = DomainEventCatalog::bindings();

        self::assertSame(['msOnChangeOrderStatus'], $bindings[DomainEventCatalog::ORDER_STATUS_CHANGED]);
    }

    public function testBindingsOnlyReferenceKnownMsOnEvents(): void
    {
        $eventsPhp = require dirname(__DIR__, 4) . '/../../../_build/elements/events.php';
        self::assertIsArray($eventsPhp);

        $known = array_fill_keys($eventsPhp, true);

        foreach (DomainEventCatalog::bindings() as $eventType => $modxEvents) {
            self::assertIsString($eventType);
            self::assertNotSame('', $eventType);
            foreach ($modxEvents as $modxEvent) {
                self::assertArrayHasKey(
                    $modxEvent,
                    $known,
                    "Binding {$eventType} references unknown MODX event {$modxEvent}"
                );
            }
        }
    }

    public function testReservedTypesAreDocumentedInBindings(): void
    {
        $bindings = DomainEventCatalog::bindings();

        self::assertArrayHasKey(DomainEventCatalog::ORDER_CREATED, $bindings);
        self::assertArrayHasKey(DomainEventCatalog::CART_ITEM_ADDED, $bindings);
        self::assertArrayHasKey(DomainEventCatalog::SHIPMENT_STATUS_CHANGED, $bindings);
    }

    public function testSubmitOrderIsNotBoundAsASuccessEvent(): void
    {
        foreach (DomainEventCatalog::bindings() as $modxEvents) {
            self::assertNotContains('msOnSubmitOrder', $modxEvents);
        }
    }
}
