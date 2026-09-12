<?php

namespace MiniShop3\Services\Inventory;

/**
 * Stock reservation contract. Replace via DI key ms3_inventory (ERP / warehouse).
 *
 * Default implementation: ProductStockInventory on ms3_products.stock.
 *
 * Lifecycle when ms3_inventory_enabled is on:
 * - assertAvailable: OrderSubmitHandler before allocating an order number
 * - reserve: OrderStatusService on ms3_status_new (and before commit if jumping to paid)
 * - commit: OrderStatusService on ms3_status_paid (stock already decremented on reserve)
 * - release: OrderStatusService on ms3_status_canceled before commit; payment send() failure
 *
 * Turn enforcement off with ms3_inventory_enabled=0 (default): cart/order/payment unchanged.
 *
 * Events (default implementation): msOnBeforeInventoryReserve / msOnInventoryReserve,
 * same pair for Commit and Release. Cancellation uses the EventGate contract (#219).
 */
interface InventoryServiceInterface
{
    public function getAvailable(InventoryKey $key): float;

    /**
     * @throws InventoryException when qty is not available
     */
    public function assertAvailable(InventoryKey $key, float $qty): void;

    /**
     * Hold qty for an order. Idempotent for the same order_id + product.
     *
     * @throws InventoryException
     */
    public function reserve(InventoryKey $key, float $qty, InventoryContext $ctx): void;

    /**
     * Return qty held by reserve. No-op if already released or already committed.
     *
     * @throws InventoryException
     */
    public function release(InventoryKey $key, float $qty, InventoryContext $ctx): void;

    /**
     * Finalize a reservation (sale). Does not decrement stock again after reserve.
     *
     * @throws InventoryException
     */
    public function commit(InventoryKey $key, float $qty, InventoryContext $ctx): void;
}
