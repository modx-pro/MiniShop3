<?php

namespace MiniShop3\Services\Inventory;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderStatus;
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
     * Match the ledger to the order lines the caller is about to save.
     *
     * $qtyByProduct is the target sum per product, including the unsaved edit.
     * Products held in the ledger but absent from the map are released.
     * Draft and canceled orders are left alone. Returns lines written past
     * available stock when $force is set.
     *
     * @param array<int, float> $qtyByProduct
     * @return list<array{product_id: int, available: float, required: float}>
     * @throws InventoryException
     */
    public function reconcile(msOrder $order, array $qtyByProduct, bool $force = false, bool $notify = true): array
    {
        if (!$this->enabled()) {
            return [];
        }

        $orderId = (int) $order->get('id');
        $held = $this->inventory instanceof AdjustableInventoryInterface
            ? $this->inventory->heldByProduct($orderId)
            : [];
        $mode = $this->holdMode($order, $held);
        if ($mode === null) {
            return [];
        }
        if (!$this->inventory instanceof AdjustableInventoryInterface) {
            throw new InventoryException('ms3_err_inventory_adjust_unsupported', [
                'order_id' => $orderId,
            ]);
        }

        $targets = [];
        foreach ($qtyByProduct as $productId => $qty) {
            $productId = (int) $productId;
            $qty = round((float) $qty, 3);
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }
            $targets[$productId] = $qty;
        }
        $productIds = array_values(array_unique(array_merge(
            array_keys($targets),
            array_keys($held)
        )));

        $ctx = new InventoryContext($orderId, 'order', $mode);
        $applied = [];
        $overrides = [];
        try {
            foreach ($productIds as $productId) {
                $target = $targets[$productId] ?? 0.0;
                $oldQty = round((float) ($held[$productId]['qty'] ?? 0.0), 3);
                $delta = round($target - $oldQty, 3);
                $available = $this->inventory->getAvailable(new InventoryKey($productId));
                $override = null;
                if ($force && $delta > 0 && $available + 0.0001 < $delta) {
                    $override = [
                        'product_id' => $productId,
                        'available' => $available,
                        'required' => $target,
                    ];
                }
                $this->inventory->adjust(new InventoryKey($productId), $target, $ctx, $force, $notify);
                $applied[$productId] = $oldQty;
                if ($override !== null) {
                    $overrides[] = $override;
                }
            }
        } catch (\Throwable $exception) {
            $this->restoreApplied($applied, $ctx);
            throw $exception;
        }

        return $overrides;
    }

    /**
     * @param array<int, float> $applied product id => qty before this reconcile
     */
    private function restoreApplied(array $applied, InventoryContext $ctx): void
    {
        if (!$this->inventory instanceof AdjustableInventoryInterface) {
            return;
        }
        foreach (array_reverse($applied, true) as $productId => $oldQty) {
            try {
                $this->inventory->adjust(new InventoryKey((int) $productId), $oldQty, $ctx, true, false);
            } catch (\Throwable $exception) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[ms3] inventory reconcile rollback failed for product ' . $productId
                    . ': ' . $exception->getMessage()
                );
            }
        }
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

    /**
     * @param array<int, array{qty: float, state: string}> $held
     */
    private function holdMode(msOrder $order, array $held): ?string
    {
        $statusId = (int) $order->get('status_id');
        if ($statusId === $this->statusDraftId() || $statusId === $this->statusCanceledId()) {
            return null;
        }

        foreach ($held as $row) {
            if (($row['state'] ?? '') === InventoryReservationState::COMMITTED) {
                return InventoryReservationState::COMMITTED;
            }
        }
        if ($statusId === $this->statusPaidId() || $this->isPaidOrLater($statusId)) {
            return InventoryReservationState::COMMITTED;
        }

        return InventoryReservationState::RESERVED;
    }

    private function isPaidOrLater(int $statusId): bool
    {
        if ($statusId === $this->statusPaidId()) {
            return true;
        }
        $paidPosition = $this->statusPosition($this->statusPaidId());
        $position = $this->statusPosition($statusId);
        if ($paidPosition === null || $position === null) {
            return false;
        }

        return $position >= $paidPosition;
    }

    private function statusPosition(int $statusId): ?int
    {
        if ($statusId <= 0) {
            return null;
        }
        $status = $this->modx->getObject(msOrderStatus::class, $statusId);
        if (!is_object($status) || !method_exists($status, 'get')) {
            return null;
        }
        $position = $status->get('position');
        if ($position === null || $position === '') {
            return null;
        }

        return (int) $position;
    }

    private function statusDraftId(): int
    {
        return (int) $this->modx->getOption('ms3_status_draft', null, 1);
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
