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

    /**
     * @throws InventoryException when enforcement is on but ms3_inventory is missing
     */
    public static function fromModx(modX $modx): ?self
    {
        if (!self::isInventoryEnabled($modx)) {
            return null;
        }
        $inventory = $modx->services->has('ms3_inventory')
            ? $modx->services->get('ms3_inventory')
            : null;
        if (!$inventory instanceof InventoryServiceInterface) {
            throw new InventoryException('ms3_err_inventory_unavailable');
        }

        return new self($modx, $inventory);
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
        if (!self::isInventoryEnabled($this->modx)) {
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
        if (!self::isInventoryEnabled($this->modx)) {
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
        if (!self::isInventoryEnabled($this->modx)) {
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
            foreach (array_reverse($done) as [$key, $qty]) {
                $this->inventory->release($key, $qty, $ctx);
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

    private function releaseAll(msOrder $order, InventoryContext $ctx): void
    {
        $paidId = (int) $this->modx->getOption('ms3_status_paid', null, 3);
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
}
