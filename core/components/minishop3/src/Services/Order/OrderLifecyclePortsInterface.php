<?php

declare(strict_types=1);

namespace MiniShop3\Services\Order;

use MiniShop3\Model\msOrder;

/**
 * Domain ports invoked by order status lifecycle after a successful transition gate.
 *
 * Implementations land with inventory (#589), payment (#590), and shipment (#591).
 * Core ships {@see NullOrderLifecyclePorts} until those domains exist.
 *
 * Ports must be safe to retry (idempotent) because after-event failure rolls back
 * status while a port may already have run.
 */
interface OrderLifecyclePortsInterface
{
    /**
     * Order reached the configured "paid" status (ms3_status_paid).
     *
     * @return string|null Lexicon/error message on failure; null on success
     */
    public function onOrderBecamePaid(msOrder $order, ?int $previousStatusId): ?string;

    /**
     * Order reached the configured canceled status (ms3_status_canceled).
     *
     * @return string|null Lexicon/error message on failure; null on success
     */
    public function onOrderCancelled(msOrder $order, ?int $previousStatusId): ?string;

    /**
     * Order reached the configured shipped/sent status (ms3_status_sent, default 4).
     *
     * @return string|null Lexicon/error message on failure; null on success
     */
    public function onOrderShipped(msOrder $order, ?int $previousStatusId): ?string;
}
