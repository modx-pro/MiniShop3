<?php

declare(strict_types=1);

namespace MiniShop3\Services\Order;

use MiniShop3\Model\msOrder;

/**
 * In-TX domain ports invoked by {@see OrderStatusService} before status_id is persisted.
 *
 * Implementations land with inventory (#589 / #603), payment (#590), and shipment (#591).
 * Core ships {@see NullOrderLifecyclePorts} until those domains exist.
 *
 * A non-null return aborts the transition (transaction rollback); status_id is not saved.
 * Ports run inside the same DB transaction as the status write when the connection supports it.
 */
interface OrderLifecyclePortsInterface
{
    /**
     * Order is transitioning to the configured "paid" status (ms3_status_paid).
     *
     * @return string|null Lexicon/error message on failure; null on success
     */
    public function onOrderBecamePaid(msOrder $order, ?int $previousStatusId): ?string;

    /**
     * Order is transitioning to the configured canceled status (ms3_status_canceled).
     *
     * @return string|null Lexicon/error message on failure; null on success
     */
    public function onOrderCancelled(msOrder $order, ?int $previousStatusId): ?string;

    /**
     * Order is transitioning to the configured shipped/sent status (ms3_status_sent, default 4).
     *
     * @return string|null Lexicon/error message on failure; null on success
     */
    public function onOrderShipped(msOrder $order, ?int $previousStatusId): ?string;
}
