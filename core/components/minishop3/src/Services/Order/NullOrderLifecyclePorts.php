<?php

declare(strict_types=1);

namespace MiniShop3\Services\Order;

use MiniShop3\Model\msOrder;

/**
 * No-op lifecycle ports until inventory / payment / shipment domains are wired (#589–#591).
 */
final class NullOrderLifecyclePorts implements OrderLifecyclePortsInterface
{
    public function onOrderBecamePaid(msOrder $order, ?int $previousStatusId): ?string
    {
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
}
