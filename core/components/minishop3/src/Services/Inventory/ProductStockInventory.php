<?php

namespace MiniShop3\Services\Inventory;

use MiniShop3\MiniShop3;
use MiniShop3\Utils\EventGate;
use MODX\Revolution\modX;

/**
 * Default inventory: decrement ms3_products.stock on reserve, restore on release, commit is ledger-only.
 *
 * Variant keys (#1001): core stock is NOT touched. Available stock comes from
 * an addon via `msOnInventoryAvailable` (returnedValues['available']);
 * the addon itself decrements its SKU table on the reserve/commit/release
 * events below. No addon answer means zero real SKU stock (fail closed):
 * ms3_products.stock is product-level and is never decremented for a variant,
 * so falling back to it would allow an undetectable oversell.
 */
class ProductStockInventory implements InventoryServiceInterface, AdjustableInventoryInterface
{
    private readonly KeyedInventoryStockStoreInterface $store;

    /**
     * True for pre-#1001 int-only stores: they have no SKU ledger and their
     * stock is product-level, so variant keys must degrade to product rows.
     */
    private readonly bool $legacyStore;

    public function __construct(
        InventoryStockStoreInterface $store,
        private readonly ?MiniShop3 $ms3 = null,
    ) {
        // Third-party stores keep their original int-productId contract;
        // adapt them instead of forcing a hard interface break (#1001 review).
        $this->legacyStore = !$store instanceof KeyedInventoryStockStoreInterface;
        $this->store = $this->legacyStore
            ? new InventoryStockStoreKeyAdapter($store)
            : $store;
    }

    public function getAvailable(InventoryKey $key): float
    {
        // Variant stock never lives in ms3_products.stock for keyed stores.
        // Legacy int-only stores intentionally degrade to product-level and do
        // decrement it, so they keep using their own availability logic.
        if ($key->isVariant() && !$this->legacyStore) {
            return round($this->variantAvailable($key) ?? 0.0, 3);
        }

        return $this->store->getAvailableByKey($key);
    }

    /**
     * Ask addons for variant stock via `msOnInventoryAvailable`.
     * Returns null when no addon answers — the caller then treats SKU stock
     * as zero rather than borrowing ms3_products.stock (#1001 review).
     */
    private function variantAvailable(InventoryKey $key): ?float
    {
        $modx = $this->modx();
        if ($modx === null) {
            return null;
        }

        try {
            $event = EventGate::invokeRaw($modx, 'msOnInventoryAvailable', [
                'key' => $key,
                'product_id' => $key->productId,
                'variant_key' => $key->variantKey,
            ]);
        } catch (\Throwable) {
            return null;
        }
        if ($event['cancelled']) {
            return null;
        }
        $available = $event['returnedValues']['available'] ?? null;
        if ($available === null || !is_numeric($available)) {
            return null;
        }

        return (float) $available;
    }

    private function modx(): ?modX
    {
        if ($this->ms3 !== null && isset($this->ms3->modx) && $this->ms3->modx instanceof modX) {
            return $this->ms3->modx;
        }

        return null;
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
        $this->reserveKeys([[$key, $qty]], $ctx, $notify);
    }

    /**
     * Backwards-compatible product-level batch entry point.
     *
     * @param array<int, float> $qtyByProduct
     * @throws InventoryException
     */
    public function reserveBatch(array $qtyByProduct, InventoryContext $ctx, bool $notify = true): void
    {
        $entries = [];
        foreach ($qtyByProduct as $productId => $qty) {
            $entries[] = [new InventoryKey((int) $productId), (float) $qty];
        }
        $this->reserveKeys($entries, $ctx, $notify);
    }

    /**
     * Reserve all keys, then emit `msOnInventoryReserve` only if the whole
     * batch stuck. Before-events still run per line (plugins may cancel).
     * Variant stock is owned by addons that act on events, so releasing a
     * partially reserved batch must also tell them via `msOnInventoryRelease`;
     * silent SQL rollback alone would leak their SKU stock (#1001 review).
     *
     * @param list<array{0: InventoryKey, 1: float}> $entries
     * @throws InventoryException
     */
    public function reserveKeys(array $entries, InventoryContext $ctx, bool $notify = true): void
    {
        $done = [];
        try {
            foreach ($entries as [$key, $qty]) {
                $reserved = $this->reserveLine($key, (float) $qty, $ctx, $notify);
                if ($reserved !== null) {
                    $done[] = $reserved;
                }
            }
        } catch (\Throwable $exception) {
            $this->rollbackReservedBatch($done, $ctx, $notify);
            throw $exception;
        }

        if ($notify) {
            $this->fireAfterReserveBatch($done, $ctx);
        }
    }

    public function release(InventoryKey $key, float $qty, InventoryContext $ctx, bool $notify = true): void
    {
        $this->normalizeQty($qty);
        $existing = $this->store->findReservationByKey($ctx->orderId, $key);
        if ($existing === null || $existing['state'] !== InventoryReservationState::RESERVED) {
            return;
        }

        $held = (float) $existing['qty'];
        if ($notify) {
            $this->fire('msOnBeforeInventoryRelease', $key, $held, $ctx);
        }
        $didRelease = false;
        $this->store->runInTransaction(function () use ($key, $ctx, $held, &$didRelease): void {
            if (!$this->store->transitionReservationByKey(
                $ctx->orderId,
                $key,
                InventoryReservationState::RESERVED,
                InventoryReservationState::RELEASED
            )) {
                return;
            }
            // Variant stock lives in the addon; core product stock is untouched.
            if (!$key->isVariant() || $this->legacyStore) {
                $this->store->incrementByKey($key, $held);
            }
            $didRelease = true;
        });
        if ($didRelease && $notify) {
            $this->fire('msOnInventoryRelease', $key, $held, $ctx);
        }
    }

    public function commit(InventoryKey $key, float $qty, InventoryContext $ctx): void
    {
        $qty = $this->normalizeQty($qty);
        $existing = $this->store->findReservationByKey($ctx->orderId, $key);
        if ($existing !== null && $existing['state'] === InventoryReservationState::COMMITTED) {
            return;
        }
        if ($existing === null || $existing['state'] !== InventoryReservationState::RESERVED) {
            $this->reserve($key, $qty, $ctx);
            $existing = $this->store->findReservationByKey($ctx->orderId, $key);
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
            $didCommit = $this->store->transitionReservationByKey(
                $ctx->orderId,
                $key,
                InventoryReservationState::RESERVED,
                InventoryReservationState::COMMITTED
            );
        });
        if (!$didCommit) {
            $again = $this->store->findReservationByKey($ctx->orderId, $key);
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
        return $this->heldMap($orderId, false);
    }

    /**
     * Active holds keyed by {@see InventoryKey::identity()} ("123" or "123|variant").
     *
     * @return array<string, array{qty: float, state: string, key: InventoryKey}>
     */
    public function heldByKey(int $orderId): array
    {
        return $this->heldMap($orderId, true);
    }

    private function heldMap(int $orderId, bool $byKey): array
    {
        $held = [];
        foreach ($this->store->listReservations($orderId) as $row) {
            $state = (string) ($row['state'] ?? '');
            if (!$this->isAlreadyHeld($state)) {
                continue;
            }
            $key = new InventoryKey(
                (int) $row['product_id'],
                isset($row['variant_key']) ? (string) $row['variant_key'] : null,
            );
            if ($byKey) {
                $held[$key->identity()] = [
                    'qty' => round((float) $row['qty'], 3),
                    'state' => $state,
                    'key' => $key,
                ];
            } else {
                $held[(int) $row['product_id']] = [
                    'qty' => round((float) $row['qty'], 3),
                    'state' => $state,
                ];
            }
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

        $preview = $this->store->findReservationByKey($ctx->orderId, $key);
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
            $this->store->transitionReservationByKey(
                $ctx->orderId,
                $key,
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
        $row = $this->store->findReservationByKey($ctx->orderId, $key);
        if ($row === null && $key->isVariant()) {
            // An order reserved before the addon resolved SKUs has a legacy
            // product-level row. Adopt it instead of inserting a second hold.
            $this->adoptLegacyReservation($key, $ctx);
            $row = $this->store->findReservationByKey($ctx->orderId, $key);
        }
        if ($this->isAlreadyHeld($row['state'] ?? null)) {
            return false;
        }

        $inserted = false;
        if ($row === null) {
            $inserted = $this->store->insertReservationByKey(
                $ctx->orderId,
                $key,
                $qty,
                InventoryReservationState::RESERVED
            );
            if (!$inserted) {
                $row = $this->store->findReservationByKey($ctx->orderId, $key);
                $state = $row['state'] ?? null;
                if ($this->isAlreadyHeld($state) || $state !== InventoryReservationState::RELEASED) {
                    return false;
                }
            }
        }

        if (!$inserted && !$this->store->transitionReservationByKey(
            $ctx->orderId,
            $key,
            InventoryReservationState::RELEASED,
            InventoryReservationState::RESERVED,
            $qty
        )) {
            return false;
        }

        // Variant stock is owned by the addon (synced via reserve events);
        // the core must not decrement ms3_products.stock for a SKU sale.
        // Legacy int-only stores have no SKU ledger and stay product-level.
        if ($key->isVariant() && !$this->legacyStore) {
            return true;
        }

        if (!$this->store->tryDecrementByKey($key, $qty)) {
            if ($inserted) {
                $this->store->deleteReservationByKey($ctx->orderId, $key);
            } else {
                $this->store->transitionReservationByKey(
                    $ctx->orderId,
                    $key,
                    InventoryReservationState::RESERVED,
                    InventoryReservationState::RELEASED
                );
            }
            throw $this->insufficient($key, $qty);
        }

        return true;
    }

    /**
     * Re-key a legacy (variant_key = '') hold on first SKU use. Stores without
     * variant support simply return false and keep product-level accounting.
     */
    private function adoptLegacyReservation(InventoryKey $key, InventoryContext $ctx): void
    {
        $legacy = new InventoryKey($key->productId);
        $row = $this->store->findReservationByKey($ctx->orderId, $legacy);
        if ($row === null || !$this->isAlreadyHeld($row['state'] ?? null)) {
            return;
        }
        $this->store->rekeyReservation($ctx->orderId, $legacy, $key);
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
        $existing = $this->store->findReservationByKey($ctx->orderId, $key);
        if ($this->isAlreadyHeld($existing['state'] ?? null)) {
            return null;
        }
        // Variant stock lives in the addon's SKU table; core stock is never
        // decremented for it, so the atomic tryDecrement in claimReserve() does
        // not protect variants. Check availability explicitly (#1001 review).
        if ($key->isVariant()) {
            $this->assertAvailable($key, $qty);
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
     * SQL release is always silent (the surrounding transaction may roll back).
     * Variant addons that already acted on `msOnBeforeInventoryReserve` still
     * need an explicit release notification so their SKU stock is restored.
     * Buffered `msOnInventoryReserve` events were never emitted on this path.
     *
     * @param list<array{0: InventoryKey, 1: float}> $done
     */
    private function rollbackReservedBatch(array $done, InventoryContext $ctx, bool $notify): void
    {
        foreach (array_reverse($done) as [$key, $qty]) {
            $this->release($key, $qty, $ctx, false);
            if ($notify && $key->isVariant()) {
                try {
                    $this->fire('msOnBeforeInventoryRelease', $key, $qty, $ctx);
                    $this->fire('msOnInventoryRelease', $key, $qty, $ctx);
                } catch (\Throwable $exception) {
                    $this->logEventFailure('rollback release event', $exception);
                }
            }
        }
    }

    private function logEventFailure(string $what, \Throwable $exception): void
    {
        $modx = $this->modx();
        if ($modx !== null) {
            $modx->log(
                modX::LOG_LEVEL_ERROR,
                '[ms3] inventory ' . $what . ' failed: ' . $exception->getMessage()
            );
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
                $this->store->transitionReservationByKey(
                    $ctx->orderId,
                    $key,
                    (string) $currentState,
                    InventoryReservationState::RELEASED
                );
            }

            return;
        }

        if ($existing === null) {
            if ($this->store->insertReservationByKey($ctx->orderId, $key, $targetQty, $desired)) {
                return;
            }
        } elseif ($this->store->transitionReservationByKey(
            $ctx->orderId,
            $key,
            (string) $currentState,
            $desired,
            $targetQty
        )) {
            return;
        }

        $this->store->saveReservation($ctx->orderId, $key, $targetQty, $desired);
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
        $existing = $this->store->lockReservationByKey($ctx->orderId, $key);
        $currentState = is_array($existing) ? (string) $existing['state'] : null;
        $held = $this->isAlreadyHeld($currentState) ? round((float) $existing['qty'], 3) : 0.0;
        $desired = $targetQty === 0.0
            ? InventoryReservationState::RELEASED
            : $this->desiredHoldState($ctx, $currentState);
        if ($this->adjustmentIsNoop($targetQty, $held, $currentState, $desired)) {
            return null;
        }

        $delta = round($targetQty - $held, 3);
        if ($delta > 0 && $key->isVariant() && !$this->legacyStore && !$force) {
            $this->assertAvailable($key, $delta);
        }
        if ($delta > 0 && (!$key->isVariant() || $this->legacyStore)) {
            $written = $force
                ? $this->store->decrementByKey($key, $delta)
                : $this->store->tryDecrementByKey($key, $delta);
            if (!$written) {
                throw new InventoryException(
                    'ms3_err_inventory_short',
                    [
                        'id' => $key->productId,
                        'product_id' => $key->productId,
                        'qty' => $delta,
                        'available' => $this->store->getAvailableByKey($key),
                        'required' => $targetQty,
                    ]
                );
            }
        } elseif ($delta < 0 && (!$key->isVariant() || $this->legacyStore)) {
            $this->store->incrementByKey($key, abs($delta));
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
