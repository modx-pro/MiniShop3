<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Support;

use MiniShop3\Services\Order\OrderStatusChanger;

final class CallbackOrderStatusChanger implements OrderStatusChanger
{
    /**
     * @param \Closure(int, int): (bool|string) $callback
     */
    public function __construct(private readonly \Closure $callback)
    {
    }

    public function change(int $orderId, int $statusId, bool $skipNotifications = false): bool|string
    {
        return ($this->callback)($orderId, $statusId);
    }
}
