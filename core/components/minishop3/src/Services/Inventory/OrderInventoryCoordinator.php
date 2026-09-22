<?php

namespace MiniShop3\Services\Inventory;

use MiniShop3\Model\msOrder;
use MODX\Revolution\modX;

/**
 * Maps order status transitions onto inventory reserve / commit / release.
 *
 * No-ops when ms3_inventory_enabled is off so cart/order/payment stay unchanged.
 */
class OrderInventoryCoordinator
{
    public function __construct(
        private readonly modX $modx,
        private readonly InventoryServiceInterface $inventory,
    ) {
    }

    public static function isInventoryEnabled(modX $modx): bool
    {
        return filter_var(
            $modx->getOption('ms3_inventory_enabled', null, false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Soft/hard check before allocating an order number.
     *
     * @throws InventoryException
     */
    public function assertOrderAvailable(msOrder $order): void
    {
        if (!$this->enabled()) {
            return;
        }
        foreach ($this->qtyByProduct($order) as $productId => $qty) {
            $this->inventory->assertAvailable(new InventoryKey($productId), $qty);
        }
    }

    /**
     * @throws InventoryException
     */
    public function applyStatusChange(msOrder $order, int $newStatusId): void
    {
        if (!$this->enabled()) {
            return;
        }
        $ctx = new InventoryContext((int) $order->get('id'));
        match ($newStatusId) {
            (int) $this->modx->getOption('ms3_status_new', null, 2) => $this->reserveAll($order, $ctx),
            (int) $this->modx->getOption('ms3_status_paid', null, 3) => $this->commitAll($order, $ctx),
            (int) $this->modx->getOption('ms3_status_canceled', null, 5) => $this->releaseAll($order, $ctx),
            default => null,
        };
    }

    /**
     * Drop an uncommitted hold (payment send failure, failed status persist).
     *
     * @throws InventoryException
     */
    public function releaseOrder(msOrder $order): void
    {
        if (!$this->enabled()) {
            return;
        }
        $this->releaseAll($order, new InventoryContext((int) $order->get('id')));
    }

    private function reserveAll(msOrder $order, InventoryContext $ctx): void
    {
        $done = [];
        try {
            foreach ($this->qtyByProduct($order) as $productId => $qty) {
                $key = new InventoryKey($productId);
                $this->inventory->reserve($key, $qty, $ctx);
                $done[] = [$key, $qty];
            }
        } catch (InventoryException $exception) {
            // Silent release: the caller transaction rolls the SQL back, so release events
            // would describe a hold that never committed (#603 review).
            foreach (array_reverse($done) as [$key, $qty]) {
                $this->inventory->release($key, $qty, $ctx, false);
            }
            throw $exception;
        }
    }

    private function commitAll(msOrder $order, InventoryContext $ctx): void
    {
        foreach ($this->qtyByProduct($order) as $productId => $qty) {
            $this->inventory->commit(new InventoryKey($productId), $qty, $ctx);
        }
    }

    /**
     * Undo inventory written for a status change whose order row did not save.
     * Caller must restore the previous status_id on $order first: releaseAll()
     * treats a paid status as final and would otherwise leave a commit in place.
     *
     * @throws InventoryException
     */
    public function compensateUnpersistedChange(msOrder $order, int $attemptedStatusId): void
    {
        if (!$this->enabled()) {
            return;
        }
        $paidId = (int) $this->modx->getOption('ms3_status_paid', null, 3);
        $ctx = new InventoryContext((int) $order->get('id'));
        if ($attemptedStatusId === $paidId && $this->inventory instanceof ProductStockInventory) {
            foreach (array_keys($this->qtyByProduct($order)) as $productId) {
                $this->inventory->revertCommitToReserved(new InventoryKey($productId), $ctx);
            }

            return;
        }
        $this->releaseAll($order, $ctx);
    }

    private function releaseAll(msOrder $order, InventoryContext $ctx): void
    {
        $paidId = (int) $this->modx->getOption('ms3_status_paid', null, 3);
        // Fast exit before per-line release(). ProductStockInventory::release() also
        // ignores committed rows; this skips the lookup when the order is already paid.
        if ((int) $order->get('status_id') === $paidId) {
            return;
        }
        foreach ($this->qtyByProduct($order) as $productId => $qty) {
            $this->inventory->release(new InventoryKey($productId), $qty, $ctx);
        }
    }

    /**
     * @return array<int, float>
     */
    private function qtyByProduct(msOrder $order): array
    {
        $lines = $order->getMany('Products');
        if (!is_iterable($lines)) {
            return [];
        }
        $qtyByProduct = [];
        foreach ($lines as $line) {
            if (!is_object($line) || !method_exists($line, 'get')) {
                continue;
            }
            $productId = (int) $line->get('product_id');
            $qty = round((float) $line->get('count'), 3);
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }
            $qtyByProduct[$productId] = round(($qtyByProduct[$productId] ?? 0.0) + $qty, 3);
        }

        return $qtyByProduct;
    }

    private function enabled(): bool
    {
        return self::isInventoryEnabled($this->modx);
    }
}
