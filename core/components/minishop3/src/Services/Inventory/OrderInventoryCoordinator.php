<?php

namespace MiniShop3\Services\Inventory;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Utils\EventGate;
use MODX\Revolution\modX;

/**
 * Maps order status transitions onto inventory reserve / commit / release.
 *
 * No-ops when ms3_inventory_enabled is off so cart/order/payment stay unchanged.
 */
class OrderInventoryCoordinator
{
    /**
     * Resolution cache per order line. `msOnInventoryResolveKey` must run once
     * per line per order: reserve, availability check and later edits all ask
     * for the same key, even when the admin UI sends a fresh object for the
     * edited line (#1001 review). Keyed by order/line id plus the line's SKU
     * options; new (unsaved) lines fall back to their object id.
     *
     * @var array<string, ?InventoryKey>
     */
    private array $resolvedKeys = [];

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
        foreach ($this->qtyByKey($order) as $entry) {
            $this->inventory->assertAvailable($entry['key'], $entry['qty']);
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
     * $qtyByKey is the target sum per stock identity (InventoryKey::identity())
     * including the unsaved edit. Plain integer product ids are still accepted
     * for backwards compatibility and mean a product-level target. Rows held
     * in the ledger but absent from the map are released. Draft and canceled
     * orders are left alone. Returns lines written past available stock when
     * $force is set.
     *
     * @param array<int|string, float> $qtyByKey
     * @return list<array{product_id: int, variant_key: ?string, available: float, required: float}>
     * @throws InventoryException
     */
    public function reconcile(msOrder $order, array $qtyByKey, bool $force = false, bool $notify = true): array
    {
        if (!$this->enabled()) {
            return [];
        }

        $orderId = (int) $order->get('id');
        $held = $this->heldForOrder($orderId);
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
        $keys = [];
        foreach ($qtyByKey as $rawKey => $qty) {
            $key = $this->coerceKey($rawKey);
            if ($key === null) {
                continue;
            }
            $identity = $key->identity();
            $keys[$identity] = $key;
            $qty = round((float) $qty, 3);
            if ($qty <= 0) {
                continue;
            }
            $targets[$identity] = round(($targets[$identity] ?? 0.0) + $qty, 3);
        }
        $identities = array_values(array_unique(array_merge(
            array_keys($targets),
            array_keys($held),
            array_keys($this->qtyByKey($order))
        )));

        $ctx = new InventoryContext($orderId, 'order', $mode);
        $applied = [];
        $overrides = [];
        try {
            foreach ($identities as $identity) {
                $key = $held[$identity]['key'] ?? $keys[$identity] ?? InventoryKey::fromIdentity((string) $identity);
                $target = $targets[$identity] ?? 0.0;
                $oldQty = round((float) ($held[$identity]['qty'] ?? 0.0), 3);
                $delta = round($target - $oldQty, 3);
                $available = $this->inventory->getAvailable($key);
                $override = null;
                if ($force && $delta > 0 && $available + 0.0001 < $delta) {
                    $override = [
                        'product_id' => $key->productId,
                        'variant_key' => $key->variantKey,
                        'available' => $available,
                        'required' => $target,
                    ];
                }
                $this->inventory->adjust($key, $target, $ctx, $force, $notify);
                $applied[$identity] = ['key' => $key, 'qty' => $oldQty];
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
     * @param array<string, array{key: InventoryKey, qty: float}> $applied identity => qty before reconcile
     */
    private function restoreApplied(array $applied, InventoryContext $ctx): void
    {
        foreach (array_reverse($applied, true) as $identity => $row) {
            try {
                $this->inventory->adjust($row['key'], $row['qty'], $ctx, true, false);
            } catch (\Throwable $exception) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[ms3] inventory reconcile rollback failed for ' . $identity
                    . ': ' . $exception->getMessage()
                );
            }
        }
    }

    private function coerceKey(mixed $rawKey): ?InventoryKey
    {
        if ($rawKey instanceof InventoryKey) {
            return $rawKey;
        }
        try {
            if (is_int($rawKey) || (is_string($rawKey) && ctype_digit($rawKey))) {
                return new InventoryKey((int) $rawKey);
            }
            if (is_string($rawKey) && $rawKey !== '') {
                return InventoryKey::fromIdentity($rawKey);
            }
        } catch (\InvalidArgumentException) {
            return null;
        }

        return null;
    }

    /**
     * Active holds keyed by identity, using heldByKey() when the backend has
     * variants and falling back to heldByProduct() for legacy stores.
     *
     * @return array<string, array{qty: float, state: string, key: InventoryKey}>
     */
    private function heldForOrder(int $orderId): array
    {
        if (!$this->inventory instanceof AdjustableInventoryInterface) {
            return [];
        }
        if (method_exists($this->inventory, 'heldByKey')) {
            return $this->inventory->heldByKey($orderId);
        }
        $held = [];
        foreach ($this->inventory->heldByProduct($orderId) as $productId => $row) {
            $key = new InventoryKey((int) $productId);
            $held[$key->identity()] = [
                'qty' => round((float) ($row['qty'] ?? 0), 3),
                'state' => (string) ($row['state'] ?? ''),
                'key' => $key,
            ];
        }

        return $held;
    }

    /**
     * Public snapshot for admin line edits: identity => held qty.
     * Used to compensate a failed order-product save (#829).
     *
     * @return array<string, float>
     */
    public function heldByKeyMap(int $orderId): array
    {
        $snapshot = [];
        foreach ($this->heldForOrder($orderId) as $identity => $row) {
            $snapshot[$identity] = round((float) ($row['qty'] ?? 0), 3);
        }

        return $snapshot;
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
        // One batch for every key: after-reserve events fire only when the
        // whole order stuck, and rollback informs variant addons (#1001 review).
        if ($this->inventory instanceof ProductStockInventory) {
            $entries = [];
            foreach ($this->qtyByKey($order) as $entry) {
                $entries[] = [$entry['key'], $entry['qty']];
            }
            $this->inventory->reserveKeys($entries, $ctx, $notify);

            return;
        }

        $done = [];
        try {
            foreach ($this->qtyByKey($order) as $entry) {
                $this->inventory->reserve($entry['key'], $entry['qty'], $ctx, $notify);
                $done[] = [$entry['key'], $entry['qty']];
            }
        } catch (\Throwable $exception) {
            foreach (array_reverse($done) as [$key, $qty]) {
                $this->inventory->release($key, $qty, $ctx, false);
            }
            throw $exception;
        }
    }

    private function commitAll(msOrder $order, InventoryContext $ctx): void
    {
        foreach ($this->qtyByKey($order) as $entry) {
            $this->inventory->commit($entry['key'], $entry['qty'], $ctx);
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
            foreach ($this->qtyByKey($order) as $entry) {
                $this->inventory->revertCommitToReserved($entry['key'], $ctx);
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
        foreach ($this->qtyByKey($order) as $entry) {
            $this->inventory->release($entry['key'], $entry['qty'], $ctx, $notify);
        }
    }

    /**
     * Targets for an admin edit: the same resolver as reserve/status paths,
     * so an unsaved line change lands on the same ledger row (#1001 review).
     *
     * @param iterable<object> $lines Order lines, with the pending edit applied
     * @return array<string, float> identity => qty
     */
    public function targetByKey(msOrder $order, iterable $lines): array
    {
        $targets = [];
        foreach ($this->qtyByLines($order, $lines) as $identity => $entry) {
            $targets[$identity] = $entry['qty'];
        }

        return $targets;
    }

    /**
     * Grouped qty by stock identity (product + variant).
     *
     * Two lines of the same product with different variant keys (set by an
     * addon via `msOnInventoryResolveKey`) stay separate ledger entries.
     * Without addons every key is product-level, behaviour unchanged.
     *
     * @return array<string, array{key: InventoryKey, qty: float}>
     */
    private function qtyByKey(msOrder $order): array
    {
        $lines = $order->getMany('Products');
        if (!is_iterable($lines)) {
            return [];
        }

        return $this->qtyByLines($order, $lines);
    }

    /**
     * @param iterable<object> $lines
     * @return array<string, array{key: InventoryKey, qty: float}>
     */
    public function qtyByLines(msOrder $order, iterable $lines): array
    {
        $byKey = [];
        foreach ($lines as $line) {
            if (!is_object($line) || !method_exists($line, 'get')) {
                continue;
            }
            $key = $this->resolveKey($line, $order);
            if ($key === null) {
                continue;
            }
            $identity = $key->identity();
            $qty = round((float) $line->get('count'), 3);
            if ($qty <= 0) {
                continue;
            }
            $byKey[$identity] = [
                'key' => $key,
                'qty' => round(($byKey[$identity]['qty'] ?? 0.0) + $qty, 3),
            ];
        }

        return $byKey;
    }

    /**
     * Resolve the stock identity for one order line.
     *
     * An addon sets the variant via event `msOnInventoryResolveKey`.
     *
     * @event msOnInventoryResolveKey
     * @param array $params {
     *     @var object $orderProduct Order line
     *     @var int $product_id Product ID
     *     @var array<string, mixed>|null $options Product options
     *     @var msOrder $order Order
     *     @var string|null $variantKey Current resolved key (null by default)
     * }
     * @param array $returnedValues {
     *     @var string $variantKey A stable, deterministic SKU string or options hash.
     *     The same orderProduct must always resolve to the same variantKey.
     *     Keys are canonicalized (case, comma-list and option order) by
     *     InventoryKey; keys longer than 128 characters are hashed.
     * }
     *
     * Without subscribers the key is product-level (variantKey = null). The
     * result is cached per line/order, so the addon fires once even when the
     * coordinator is asked again for availability or admin edit targets.
     */
    private function resolveKey(object $line, msOrder $order): ?InventoryKey
    {
        $orderId = (int) $order->get('id');
        $productId = (int) $line->get('product_id');
        $qty = round((float) $line->get('count'), 3);
        if ($productId <= 0 || $qty <= 0) {
            return null;
        }

        $rawOptions = $line->get('options');
        $options = is_array($rawOptions)
            ? $rawOptions
            : (is_string($rawOptions) ? json_decode($rawOptions, true) : null);
        $lineId = (int) $line->get('id');
        $cacheKey = $orderId . ':' . ($lineId > 0 ? 'line-' . $lineId : 'obj-' . spl_object_id($line))
            . ':' . $productId . ':' . md5(json_encode($options ?: [], JSON_UNESCAPED_UNICODE) ?: '');
        if (array_key_exists($cacheKey, $this->resolvedKeys)) {
            return $this->resolvedKeys[$cacheKey];
        }

        try {
            $event = EventGate::invokeRaw($this->modx, 'msOnInventoryResolveKey', [
                'orderProduct' => $line,
                'product_id' => $productId,
                'options' => $options,
                'order' => $order,
                'variantKey' => null,
            ]);
        } catch (\Throwable $e) {
            // Log the error; fallback to product-level key
            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_WARN,
                '[ms3] msOnInventoryResolveKey failed: ' . $e->getMessage()
            );
            $key = new InventoryKey($productId);
            $this->resolvedKeys[$cacheKey] = $key;

            return $key;
        }
        $variant = $event['returnedValues']['variantKey'] ?? null;
        $key = new InventoryKey($productId, is_string($variant) ? $variant : null);
        $this->resolvedKeys[$cacheKey] = $key;

        return $key;
    }

    private function enabled(): bool
    {
        return self::isInventoryEnabled($this->modx);
    }

    /**
     * @param array<string, array{qty: float, state: string, key: InventoryKey}> $held
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
