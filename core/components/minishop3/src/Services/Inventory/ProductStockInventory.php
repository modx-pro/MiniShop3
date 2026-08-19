<?php

namespace MiniShop3\Services\Inventory;

use MiniShop3\MiniShop3;

/**
 * Default inventory: decrement ms3_products.stock on reserve, restore on release, commit is ledger-only.
 */
class ProductStockInventory implements InventoryServiceInterface
{
    public function __construct(
        private readonly InventoryStockStoreInterface $store,
        private readonly ?MiniShop3 $ms3 = null,
    ) {
    }

    public function getAvailable(InventoryKey $key): float
    {
        return $this->store->getAvailable($key->productId);
    }

    public function assertAvailable(InventoryKey $key, float $qty): void
    {
        $qty = $this->normalizeQty($qty);
        if ($this->getAvailable($key) < $qty) {
            throw $this->insufficient($key, $qty);
        }
    }

    public function reserve(InventoryKey $key, float $qty, InventoryContext $ctx): void
    {
        $qty = $this->normalizeQty($qty);
        $existing = $this->store->findReservation($ctx->orderId, $key->productId);
        $state = $existing['state'] ?? null;
        if ($state === InventoryReservationState::RESERVED
            || $state === InventoryReservationState::COMMITTED
        ) {
            return;
        }

        $this->fire('msOnBeforeInventoryReserve', $key, $qty, $ctx);
        if (!$this->store->tryDecrement($key->productId, $qty)) {
            throw $this->insufficient($key, $qty);
        }
        $this->store->saveReservation(
            $ctx->orderId,
            $key->productId,
            $qty,
            InventoryReservationState::RESERVED
        );
        $this->fire('msOnInventoryReserve', $key, $qty, $ctx);
    }

    public function release(InventoryKey $key, float $qty, InventoryContext $ctx): void
    {
        $this->normalizeQty($qty);
        $existing = $this->store->findReservation($ctx->orderId, $key->productId);
        if ($existing === null || $existing['state'] !== InventoryReservationState::RESERVED) {
            return;
        }

        $held = (float) $existing['qty'];
        $this->fire('msOnBeforeInventoryRelease', $key, $held, $ctx);
        $this->store->increment($key->productId, $held);
        $this->store->saveReservation(
            $ctx->orderId,
            $key->productId,
            $held,
            InventoryReservationState::RELEASED
        );
        $this->fire('msOnInventoryRelease', $key, $held, $ctx);
    }

    public function commit(InventoryKey $key, float $qty, InventoryContext $ctx): void
    {
        $qty = $this->normalizeQty($qty);
        $existing = $this->store->findReservation($ctx->orderId, $key->productId);
        if ($existing !== null && $existing['state'] === InventoryReservationState::COMMITTED) {
            return;
        }
        if ($existing === null || $existing['state'] !== InventoryReservationState::RESERVED) {
            $this->reserve($key, $qty, $ctx);
            $existing = $this->store->findReservation($ctx->orderId, $key->productId);
        }
        if ($existing === null || $existing['state'] !== InventoryReservationState::RESERVED) {
            throw new InventoryException(
                'ms3_err_inventory_not_reserved',
                ['id' => $key->productId, 'order_id' => $ctx->orderId]
            );
        }

        $held = (float) $existing['qty'];
        $this->fire('msOnBeforeInventoryCommit', $key, $held, $ctx);
        $this->store->saveReservation(
            $ctx->orderId,
            $key->productId,
            $held,
            InventoryReservationState::COMMITTED
        );
        $this->fire('msOnInventoryCommit', $key, $held, $ctx);
    }

    private function normalizeQty(float $qty): float
    {
        $qty = round($qty, 3);
        if ($qty <= 0) {
            throw new InventoryException('ms3_err_inventory_invalid_qty', ['qty' => $qty]);
        }

        return $qty;
    }

    private function insufficient(InventoryKey $key, float $qty): InventoryException
    {
        return new InventoryException(
            'ms3_err_inventory_insufficient',
            ['id' => $key->productId, 'qty' => $qty]
        );
    }

    private function fire(string $event, InventoryKey $key, float $qty, InventoryContext $ctx): void
    {
        if ($this->ms3 === null || !isset($this->ms3->utils)) {
            return;
        }
        $response = $this->ms3->utils->invokeEvent($event, [
            'key' => $key,
            'qty' => $qty,
            'ctx' => $ctx,
        ]);
        if (!empty($response['success'])) {
            return;
        }
        $message = is_string($response['message'] ?? null) ? $response['message'] : '';
        throw new InventoryException(
            'ms3_err_inventory_cancelled',
            ['event' => $event],
            $message
        );
    }
}
