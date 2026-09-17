<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Support;

use MiniShop3\Model\msOrder;
use MiniShop3\Services\Order\OrderStatusChanger;

/**
 * Mirrors OrderStatusService: fixed current status fails in change() before same-status.
 */
final class FixedReplayOrderStatusChanger implements OrderStatusChanger
{
    /** @var list<array{0: int, 1: int}> */
    public array $changes = [];

    public function __construct(
        private readonly msOrder $order,
        private readonly int $fixedStatusId = 3,
    ) {
    }

    public function change(int $orderId, int $statusId, bool $skipNotifications = false): bool|string
    {
        $current = (int) $this->order->get('status_id');
        if ($current === $this->fixedStatusId) {
            return 'ms3_err_status_fixed';
        }
        if ($current === $statusId) {
            return 'ms3_err_status_same';
        }
        $this->changes[] = [$orderId, $statusId];
        $this->order->set('status_id', $statusId);

        return true;
    }

    public function ensure(int $orderId, int $statusId, bool $skipNotifications = false): bool|string
    {
        if ((int) $this->order->get('status_id') === $statusId) {
            return true;
        }

        return $this->change($orderId, $statusId, $skipNotifications);
    }
}
