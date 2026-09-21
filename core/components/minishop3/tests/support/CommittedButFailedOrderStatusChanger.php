<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Support;

use MiniShop3\Model\msOrder;
use MiniShop3\Services\Order\OrderStatusChanger;

/**
 * Simulates status_id persisted while msOnChangeOrderStatus returns failure (#754).
 */
final class CommittedButFailedOrderStatusChanger implements OrderStatusChanger
{
    public function __construct(private readonly msOrder $order)
    {
    }

    public function change(int $orderId, int $statusId, bool $skipNotifications = false): bool|string
    {
        return $this->ensure($orderId, $statusId, $skipNotifications);
    }

    public function ensure(int $orderId, int $statusId, bool $skipNotifications = false): bool|string
    {
        if ((int) $this->order->get('status_id') === $statusId) {
            return true;
        }
        // Persist then succeed: mirrors OrderStatusService::ensure after committed-but-failed after-event.
        $this->order->set('status_id', $statusId);

        return true;
    }
}
