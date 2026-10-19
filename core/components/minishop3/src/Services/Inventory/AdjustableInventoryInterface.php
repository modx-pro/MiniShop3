<?php

namespace MiniShop3\Services\Inventory;

/**
 * Optional contract for inventory backends that can change a hold in place.
 *
 * Not part of InventoryServiceInterface: a replaced ms3_inventory may only
 * implement reserve/commit/release. Order line edits fail closed when this
 * interface is missing and the order already holds stock (#829).
 */
interface AdjustableInventoryInterface
{
    /**
     * Set the ledger qty for (order, product) and move stock by the same delta.
     *
     * targetQty 0 releases the hold. Existing reserved/committed state is kept.
     * A new row uses InventoryContext::$holdState (reserved when it is empty).
     * $force writes stock below zero instead of refusing.
     *
     * @throws InventoryException
     */
    public function adjust(
        InventoryKey $key,
        float $targetQty,
        InventoryContext $ctx,
        bool $force = false,
        bool $notify = true,
    ): void;

    /**
     * Active holds for an order, keyed by product id.
     *
     * @return array<int, array{qty: float, state: string}>
     */
    public function heldByProduct(int $orderId): array;
}
