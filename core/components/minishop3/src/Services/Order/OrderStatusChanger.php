<?php

declare(strict_types=1);

namespace MiniShop3\Services\Order;

/**
 * Narrow seam for order status transitions. Payment lifecycle must not write status_id.
 */
interface OrderStatusChanger
{
    /**
     * @return bool|string True on success, lexicon/error message on failure
     */
    public function change(int $orderId, int $statusId, bool $skipNotifications = false): bool|string;
}
