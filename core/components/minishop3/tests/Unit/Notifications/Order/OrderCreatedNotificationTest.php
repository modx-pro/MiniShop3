<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Notifications\Order;

use MiniShop3\Model\msOrderStatus;
use MiniShop3\Notifications\Order\OrderCreatedNotification;
use MiniShop3\Notifications\Order\StatusChangedNotification;
use MiniShop3\Services\Order\OrderLogService;
use MiniShop3\Services\Order\OrderStatusService;
use MiniShop3\MiniShop3;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Regression #811: событие `order_created` должно иметь свой Notification-класс
 * и срабатывать при выходе заказа из черновика.
 */
final class OrderCreatedNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testEventConstantAndInheritance(): void
    {
        self::assertSame('order_created', OrderCreatedNotification::EVENT);
        self::assertSame('order_status_changed', StatusChangedNotification::EVENT);
        self::assertTrue(is_subclass_of(OrderCreatedNotification::class, StatusChangedNotification::class));
    }

    public function testIsCreatedFromDraftOnlyForDraftStatus(): void
    {
        $modx = new modX();
        $modx->optionOverrides['ms3_status_draft'] = 1;

        $ms3 = $this->createMock(MiniShop3::class);
        $log = $this->createMock(OrderLogService::class);

        $service = new class($modx, $ms3, $log) extends OrderStatusService {
            public function exposeIsCreatedFromDraft(?msOrderStatus $oldStatus): bool
            {
                return $this->isCreatedFromDraft($oldStatus);
            }
        };

        self::assertFalse($service->exposeIsCreatedFromDraft(null));

        $draft = $this->createMock(msOrderStatus::class);
        $draft->method('get')->with('id')->willReturn(1);
        self::assertTrue($service->exposeIsCreatedFromDraft($draft));

        $other = $this->createMock(msOrderStatus::class);
        $other->method('get')->with('id')->willReturn(2);
        self::assertFalse($service->exposeIsCreatedFromDraft($other));
    }
}
