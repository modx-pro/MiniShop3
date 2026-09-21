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
        $didReserve = false;
        $this->store->runInTransaction(function () use ($key, $qty, $ctx, &$didReserve): void {
            $didReserve = $this->claimReserve($key, $qty, $ctx);
        });
        if ($didReserve) {
            $this->fire('msOnInventoryReserve', $key, $qty, $ctx);
        }
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
        $didRelease = false;
        $this->store->runInTransaction(function () use ($key, $ctx, $held, &$didRelease): void {
            if (!$this->store->transitionReservation(
                $ctx->orderId,
                $key->productId,
                InventoryReservationState::RESERVED,
                InventoryReservationState::RELEASED
            )) {
                return;
            }
            $this->store->increment($key->productId, $held);
            $didRelease = true;
        });
        if ($didRelease) {
            $this->fire('msOnInventoryRelease', $key, $held, $ctx);
        }
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
        $didCommit = false;
        $this->store->runInTransaction(function () use ($key, $ctx, &$didCommit): void {
            $didCommit = $this->store->transitionReservation(
                $ctx->orderId,
                $key->productId,
                InventoryReservationState::RESERVED,
                InventoryReservationState::COMMITTED
            );
        });
        if (!$didCommit) {
            $again = $this->store->findReservation($ctx->orderId, $key->productId);
            if ($again !== null && $again['state'] === InventoryReservationState::COMMITTED) {
                return;
            }
            throw new InventoryException(
                'ms3_err_inventory_not_reserved',
                ['id' => $key->productId, 'order_id' => $ctx->orderId]
            );
        }
        $this->fire('msOnInventoryCommit', $key, $held, $ctx);
    }

    /**
     * Claim the ledger row, then decrement stock only if this call won the claim.
     *
     * @throws InventoryException
     */
    private function claimReserve(InventoryKey $key, float $qty, InventoryContext $ctx): bool
    {
        $row = $this->store->findReservation($ctx->orderId, $key->productId);
        $state = $row['state'] ?? null;
        if ($state === InventoryReservationState::RESERVED
            || $state === InventoryReservationState::COMMITTED
        ) {
            return false;
        }

        $inserted = false;
        if ($row === null) {
            $inserted = $this->store->insertReservation(
                $ctx->orderId,
                $key->productId,
                $qty,
                InventoryReservationState::RESERVED
            );
            if (!$inserted) {
                $row = $this->store->findReservation($ctx->orderId, $key->productId);
                $state = $row['state'] ?? null;
                if ($state === InventoryReservationState::RESERVED
                    || $state === InventoryReservationState::COMMITTED
                    || $state !== InventoryReservationState::RELEASED
                ) {
                    return false;
                }
            }
        }

        if (!$inserted && !$this->store->transitionReservation(
            $ctx->orderId,
            $key->productId,
            InventoryReservationState::RELEASED,
            InventoryReservationState::RESERVED,
            $qty
        )) {
            return false;
        }

        if (!$this->store->tryDecrement($key->productId, $qty)) {
            if ($inserted) {
                $this->store->deleteReservation($ctx->orderId, $key->productId);
            } else {
                $this->store->transitionReservation(
                    $ctx->orderId,
                    $key->productId,
                    InventoryReservationState::RESERVED,
                    InventoryReservationState::RELEASED
                );
            }
            throw $this->insufficient($key, $qty);
        }

        return true;
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
        throw new InventoryException(
            'ms3_err_inventory_cancelled',
            ['event' => $event],
            $response['message']
        );
    }
}
