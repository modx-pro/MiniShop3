<?php

namespace MiniShop3\Services\Inventory;

use MiniShop3\MiniShop3;
use MODX\Revolution\modX;

/**
 * Default inventory: decrement ms3_products.stock on reserve, restore on release, commit is ledger-only.
 */
class ProductStockInventory implements InventoryServiceInterface, AdjustableInventoryInterface
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

    public function reserve(InventoryKey $key, float $qty, InventoryContext $ctx, bool $notify = true): void
    {
        $this->reserveBatch([$key->productId => $qty], $ctx, $notify);
    }

    /**
     * Reserve every line, then emit after-events only if the whole batch stuck.
     * Before-events still run per line (plugins may cancel). On failure, prior
     * lines are released with notify=false so subscribers never see a hold that
     * the surrounding transaction will roll back (#762).
     *
     * @param array<int, float> $qtyByProduct
     * @throws InventoryException
     */
    public function reserveBatch(array $qtyByProduct, InventoryContext $ctx, bool $notify = true): void
    {
        $done = [];
        try {
            foreach ($qtyByProduct as $productId => $qty) {
                $reserved = $this->reserveLine(new InventoryKey((int) $productId), (float) $qty, $ctx, $notify);
                if ($reserved !== null) {
                    $done[] = $reserved;
                }
            }
        } catch (InventoryException $exception) {
            $this->silentReleaseBatch($done, $ctx);
            throw $exception;
        }

        if ($notify) {
            $this->fireAfterReserveBatch($done, $ctx);
        }
    }

    public function release(InventoryKey $key, float $qty, InventoryContext $ctx, bool $notify = true): void
    {
        $this->normalizeQty($qty);
        $existing = $this->store->findReservation($ctx->orderId, $key->productId);
        if ($existing === null || $existing['state'] !== InventoryReservationState::RESERVED) {
            return;
        }

        $held = (float) $existing['qty'];
        if ($notify) {
            $this->fire('msOnBeforeInventoryRelease', $key, $held, $ctx);
        }
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
        if ($didRelease && $notify) {
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
     * Undo a commit that was not persisted on the order (failed status save inside a foreign transaction).
     * Stock stays decremented; the ledger returns to reserved. No events.
     */
    public function heldByProduct(int $orderId): array
    {
        $held = [];
        foreach ($this->store->listReservations($orderId) as $row) {
            $state = (string) ($row['state'] ?? '');
            if (!$this->isAlreadyHeld($state)) {
                continue;
            }
            $held[(int) $row['product_id']] = [
                'qty' => round((float) $row['qty'], 3),
                'state' => $state,
            ];
        }

        return $held;
    }

    public function adjust(
        InventoryKey $key,
        float $targetQty,
        InventoryContext $ctx,
        bool $force = false,
        bool $notify = true,
    ): void {
        $targetQty = round($targetQty, 3);
        if ($targetQty < 0) {
            throw new InventoryException('ms3_err_inventory_invalid_qty', ['qty' => $targetQty]);
        }

        $preview = $this->store->findReservation($ctx->orderId, $key->productId);
        $previewState = is_array($preview) ? (string) $preview['state'] : null;
        $previewHeld = $this->isAlreadyHeld($previewState) ? round((float) $preview['qty'], 3) : 0.0;
        if ($this->adjustmentIsNoop($targetQty, $previewHeld, $previewState, $this->desiredHoldState($ctx, $previewState))) {
            return;
        }

        if ($notify) {
            $this->fireAdjust('msOnBeforeInventoryAdjust', $key, $ctx, $previewHeld, $targetQty, $force, true);
        }

        $written = null;
        $this->store->runInTransaction(function () use ($key, $ctx, $targetQty, $force, &$written): void {
            $written = $this->applyLockedAdjust($key, $ctx, $targetQty, $force);
        });

        if ($notify && is_array($written)) {
            $this->fireAdjust(
                'msOnInventoryAdjust',
                $key,
                $ctx,
                $written['old'],
                $written['new'],
                $force,
                false
            );
        }
    }

    public function revertCommitToReserved(InventoryKey $key, InventoryContext $ctx): void
    {
        $this->store->runInTransaction(function () use ($key, $ctx): void {
            $this->store->transitionReservation(
                $ctx->orderId,
                $key->productId,
                InventoryReservationState::COMMITTED,
                InventoryReservationState::RESERVED
            );
        });
    }

    /**
     * Claim the ledger row, then decrement stock only if this call won the claim.
     *
     * @throws InventoryException
     */
    private function claimReserve(InventoryKey $key, float $qty, InventoryContext $ctx): bool
    {
        $row = $this->store->findReservation($ctx->orderId, $key->productId);
        if ($this->isAlreadyHeld($row['state'] ?? null)) {
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
                if ($this->isAlreadyHeld($state) || $state !== InventoryReservationState::RELEASED) {
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

    /**
     * @return array{0: InventoryKey, 1: float}|null reserved key+qty, or null when idempotent skip
     * @throws InventoryException
     */
    private function reserveLine(
        InventoryKey $key,
        float $qty,
        InventoryContext $ctx,
        bool $notify,
    ): ?array {
        $qty = $this->normalizeQty($qty);
        $existing = $this->store->findReservation($ctx->orderId, $key->productId);
        if ($this->isAlreadyHeld($existing['state'] ?? null)) {
            return null;
        }

        if ($notify) {
            $this->fire('msOnBeforeInventoryReserve', $key, $qty, $ctx);
        }
        $didReserve = false;
        $this->store->runInTransaction(function () use ($key, $qty, $ctx, &$didReserve): void {
            $didReserve = $this->claimReserve($key, $qty, $ctx);
        });

        return $didReserve ? [$key, $qty] : null;
    }

    private function isAlreadyHeld(?string $state): bool
    {
        return $state === InventoryReservationState::RESERVED
            || $state === InventoryReservationState::COMMITTED;
    }

    /**
     * @param list<array{0: InventoryKey, 1: float}> $done
     */
    private function silentReleaseBatch(array $done, InventoryContext $ctx): void
    {
        foreach (array_reverse($done) as [$key, $qty]) {
            $this->release($key, $qty, $ctx, false);
        }
    }

    /**
     * @param list<array{0: InventoryKey, 1: float}> $done
     */
    private function fireAfterReserveBatch(array $done, InventoryContext $ctx): void
    {
        foreach ($done as [$key, $qty]) {
            $this->fire('msOnInventoryReserve', $key, $qty, $ctx);
        }
    }

    private function normalizeQty(float $qty): float
    {
        $qty = round($qty, 3);
        if ($qty <= 0) {
            throw new InventoryException('ms3_err_inventory_invalid_qty', ['qty' => $qty]);
        }

        return $qty;
    }

    private function desiredHoldState(InventoryContext $ctx, ?string $currentState): string
    {
        if ($ctx->holdState === InventoryReservationState::RESERVED
            || $ctx->holdState === InventoryReservationState::COMMITTED
        ) {
            return $ctx->holdState;
        }
        if ($this->isAlreadyHeld($currentState)) {
            return (string) $currentState;
        }

        return InventoryReservationState::RESERVED;
    }

    /**
     * @param array{order_id: int, product_id: int, qty: float, state: string}|null $existing
     */
    private function writeAdjustedRow(
        InventoryKey $key,
        InventoryContext $ctx,
        ?array $existing,
        ?string $currentState,
        string $desired,
        float $targetQty,
    ): void {
        if ($targetQty <= 0.0) {
            if ($this->isAlreadyHeld($currentState)) {
                $this->store->transitionReservation(
                    $ctx->orderId,
                    $key->productId,
                    (string) $currentState,
                    InventoryReservationState::RELEASED
                );
            }

            return;
        }

        if ($existing === null) {
            if ($this->store->insertReservation($ctx->orderId, $key->productId, $targetQty, $desired)) {
                return;
            }
        } elseif ($this->store->transitionReservation(
            $ctx->orderId,
            $key->productId,
            (string) $currentState,
            $desired,
            $targetQty
        )) {
            return;
        }

        $this->store->saveReservation($ctx->orderId, $key->productId, $targetQty, $desired);
    }

    /**
     * Re-read the ledger under the transaction lock and apply only that delta.
     *
     * @return array{old: float, new: float}|null
     */
    private function applyLockedAdjust(
        InventoryKey $key,
        InventoryContext $ctx,
        float $targetQty,
        bool $force,
    ): ?array {
        $existing = $this->store->lockReservation($ctx->orderId, $key->productId);
        $currentState = is_array($existing) ? (string) $existing['state'] : null;
        $held = $this->isAlreadyHeld($currentState) ? round((float) $existing['qty'], 3) : 0.0;
        $desired = $targetQty === 0.0
            ? InventoryReservationState::RELEASED
            : $this->desiredHoldState($ctx, $currentState);
        if ($this->adjustmentIsNoop($targetQty, $held, $currentState, $desired)) {
            return null;
        }

        $delta = round($targetQty - $held, 3);
        if ($delta > 0) {
            $written = $force
                ? $this->store->decrement($key->productId, $delta)
                : $this->store->tryDecrement($key->productId, $delta);
            if (!$written) {
                throw new InventoryException(
                    'ms3_err_inventory_short',
                    [
                        'id' => $key->productId,
                        'product_id' => $key->productId,
                        'qty' => $delta,
                        'available' => $this->store->getAvailable($key->productId),
                        'required' => $targetQty,
                    ]
                );
            }
        } elseif ($delta < 0) {
            $this->store->increment($key->productId, abs($delta));
        }

        $this->writeAdjustedRow($key, $ctx, $existing, $currentState, $desired, $targetQty);

        return ['old' => $held, 'new' => $targetQty];
    }

    private function adjustmentIsNoop(float $targetQty, float $held, ?string $currentState, string $desired): bool
    {
        if ($targetQty === 0.0) {
            return $held <= 0.0;
        }

        return abs($targetQty - $held) < 0.0005 && $currentState === $desired;
    }

    private function fireAdjust(
        string $event,
        InventoryKey $key,
        InventoryContext $ctx,
        float $oldQty,
        float $newQty,
        bool $forced,
        bool $cancelable,
    ): void {
        if ($this->ms3 === null || !isset($this->ms3->utils)) {
            return;
        }
        $response = $this->ms3->utils->invokeEvent($event, [
            'product_id' => $key->productId,
            'order_id' => $ctx->orderId,
            'old_qty' => $oldQty,
            'new_qty' => $newQty,
            'forced' => $forced,
        ]);
        if (!empty($response['success'])) {
            return;
        }
        if (!$cancelable) {
            if (isset($this->ms3->modx) && is_object($this->ms3->modx)) {
                $this->ms3->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[ms3] ' . $event . ' reported an error after the stock write was committed'
                );
            }

            return;
        }
        throw new InventoryException(
            'ms3_err_inventory_cancelled',
            ['event' => $event],
            $response['message']
        );
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
