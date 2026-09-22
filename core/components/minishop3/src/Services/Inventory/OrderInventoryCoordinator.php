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
            $this->statusNewId() => $this->reserveAll($order, $ctx),
            $this->statusPaidId() => $this->commitAll($order, $ctx),
            $this->statusCanceledId() => $this->releaseAll($order, $ctx),
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

    private function reserveAll(msOrder $order, InventoryContext $ctx, bool $notify = true): void
    {
        $qtyByProduct = $this->qtyByProduct($order);
        if ($this->inventory instanceof ProductStockInventory) {
            $this->inventory->reserveBatch($qtyByProduct, $ctx, $notify);

            return;
        }

        $done = [];
        try {
            foreach ($qtyByProduct as $productId => $qty) {
                $key = new InventoryKey($productId);
                $this->inventory->reserve($key, $qty, $ctx, $notify);
                $done[] = [$key, $qty];
            }
        } catch (InventoryException $exception) {
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
     * Events are suppressed ($notify=false): this runs inside an open foreign
     * transaction that will roll the SQL back (#762).
     *
     * Attempted cancel: applyStatusChange already released; if previous status
     * was New, re-reserve silently so the hold matches the restored status_id.
     *
     * @throws InventoryException
     */
    public function compensateUnpersistedChange(msOrder $order, int $attemptedStatusId): void
    {
        if (!$this->enabled()) {
            return;
        }
        $ctx = new InventoryContext((int) $order->get('id'));

        if ($attemptedStatusId === $this->statusPaidId() && $this->inventory instanceof ProductStockInventory) {
            foreach (array_keys($this->qtyByProduct($order)) as $productId) {
                $this->inventory->revertCommitToReserved(new InventoryKey($productId), $ctx);
            }

            return;
        }

        if ($attemptedStatusId === $this->statusCanceledId()) {
            if ((int) $order->get('status_id') === $this->statusNewId()) {
                $this->reserveAll($order, $ctx, false);
            }

            return;
        }

        $this->releaseAll($order, $ctx, false);
    }

    private function releaseAll(msOrder $order, InventoryContext $ctx, bool $notify = true): void
    {
        // Paid orders keep committed stock; release() would no-op per line anyway.
        if ((int) $order->get('status_id') === $this->statusPaidId()) {
            return;
        }
        foreach ($this->qtyByProduct($order) as $productId => $qty) {
            $this->inventory->release(new InventoryKey($productId), $qty, $ctx, $notify);
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

    private function statusNewId(): int
    {
        return (int) $this->modx->getOption('ms3_status_new', null, 2);
    }

    private function statusPaidId(): int
    {
        return (int) $this->modx->getOption('ms3_status_paid', null, 3);
    }

    private function statusCanceledId(): int
    {
        return (int) $this->modx->getOption('ms3_status_canceled', null, 5);
    }
}
