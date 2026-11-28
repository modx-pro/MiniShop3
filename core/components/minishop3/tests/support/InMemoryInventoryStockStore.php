<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Support;

use MiniShop3\Services\Inventory\InventoryKey;
use MiniShop3\Services\Inventory\InventoryReservationState;
use MiniShop3\Services\Inventory\KeyedInventoryStockStoreInterface;

/**
 * In-process store for unit tests and contract fakes.
 *
 * Implements both the legacy int-product methods and the keyed methods used by
 * the core (see InventoryStockStoreInterface BC notes).
 */
final class InMemoryInventoryStockStore implements KeyedInventoryStockStoreInterface
{
    /** @var array<int, float> */
    private array $stock = [];

    /**
     * @var array<string, array{order_id: int, product_id: int, variant_key: ?string, qty: float, state: string}>
     */
    private array $reservations = [];

    public function seedStock(int $productId, float $qty): void
    {
        $this->stock[$productId] = round($qty, 3);
    }

    // Legacy methods deliberately accept an InventoryKey too: older tests and
    // in-repo callers can use either form while the interface stays int-based.
    public function getAvailable(int|InventoryKey $productId): float
    {
        return $this->stock[$this->pid($productId)] ?? 0.0;
    }

    public function getAvailableByKey(InventoryKey $key): float
    {
        return $this->getAvailable($key->productId);
    }

    public function tryDecrement(int|InventoryKey $productId, float $qty): bool
    {
        $id = $this->pid($productId);
        $available = $this->getAvailable($id);
        if ($available < $qty) {
            return false;
        }
        $this->stock[$id] = round($available - $qty, 3);

        return true;
    }

    public function tryDecrementByKey(InventoryKey $key, float $qty): bool
    {
        return $this->tryDecrement($key->productId, $qty);
    }

    public function decrement(int|InventoryKey $productId, float $qty): bool
    {
        $id = $this->pid($productId);
        if (!array_key_exists($id, $this->stock)) {
            return false;
        }
        $this->stock[$id] = round($this->stock[$id] - $qty, 3);

        return true;
    }

    public function decrementByKey(InventoryKey $key, float $qty): bool
    {
        return $this->decrement($key->productId, $qty);
    }

    public function increment(int|InventoryKey $productId, float $qty): void
    {
        $id = $this->pid($productId);
        $this->stock[$id] = round($this->getAvailable($id) + $qty, 3);
    }

    public function incrementByKey(InventoryKey $key, float $qty): void
    {
        $this->increment($key->productId, $qty);
    }

    public function findReservation(int $orderId, int|InventoryKey $productId): ?array
    {
        return $this->findReservationByKey($orderId, $productId instanceof InventoryKey ? $productId : new InventoryKey($productId));
    }

    public function findReservationByKey(int $orderId, InventoryKey $key): ?array
    {
        return $this->reservations[$this->reservationKey($orderId, $key)] ?? null;
    }

    public function lockReservation(int $orderId, int|InventoryKey $productId): ?array
    {
        return $this->findReservation($orderId, $productId);
    }

    public function lockReservationByKey(int $orderId, InventoryKey $key): ?array
    {
        return $this->findReservationByKey($orderId, $key);
    }

    public function listReservations(int $orderId): array
    {
        $rows = [];
        foreach ($this->reservations as $row) {
            if ($row['order_id'] === $orderId) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Active holds keyed by identity, as expected from AdjustableInventoryInterface.
     *
     * @return array<string, array{qty: float, state: string, key: InventoryKey}>
     */
    public function heldByKey(int $orderId): array
    {
        $held = [];
        foreach ($this->listReservations($orderId) as $row) {
            $state = (string) ($row['state'] ?? '');
            if ($state !== InventoryReservationState::RESERVED && $state !== InventoryReservationState::COMMITTED) {
                continue;
            }
            $key = new InventoryKey((int) $row['product_id'], $row['variant_key'] ?? null);
            $held[$key->identity()] = [
                'qty' => round((float) $row['qty'], 3),
                'state' => $state,
                'key' => $key,
            ];
        }

        return $held;
    }

    public function saveReservation(int $orderId, int|InventoryKey $productId, float $qty, string $state): void
    {
        $this->saveReservationByKey(
            $orderId,
            $productId instanceof InventoryKey ? $productId : new InventoryKey($productId),
            $qty,
            $state
        );
    }

    public function saveReservationByKey(int $orderId, InventoryKey $key, float $qty, string $state): void
    {
        $this->reservations[$this->reservationKey($orderId, $key)] = [
            'order_id' => $orderId,
            'product_id' => $key->productId,
            'variant_key' => $key->variantKey,
            'qty' => round($qty, 3),
            'state' => $state,
        ];
    }

    public function insertReservation(int $orderId, int|InventoryKey $productId, float $qty, string $state): bool
    {
        return $this->insertReservationByKey(
            $orderId,
            $productId instanceof InventoryKey ? $productId : new InventoryKey($productId),
            $qty,
            $state
        );
    }

    public function insertReservationByKey(int $orderId, InventoryKey $key, float $qty, string $state): bool
    {
        $k = $this->reservationKey($orderId, $key);
        if (isset($this->reservations[$k])) {
            return false;
        }
        $this->saveReservationByKey($orderId, $key, $qty, $state);

        return true;
    }

    public function transitionReservation(
        int $orderId,
        int|InventoryKey $productId,
        string $fromState,
        string $toState,
        ?float $qty = null
    ): bool {
        return $this->transitionReservationByKey(
            $orderId,
            $productId instanceof InventoryKey ? $productId : new InventoryKey($productId),
            $fromState,
            $toState,
            $qty
        );
    }

    public function transitionReservationByKey(
        int $orderId,
        InventoryKey $key,
        string $fromState,
        string $toState,
        ?float $qty = null
    ): bool {
        $k = $this->reservationKey($orderId, $key);
        $row = $this->reservations[$k] ?? null;
        if ($row === null || $row['state'] !== $fromState) {
            return false;
        }
        $row['state'] = $toState;
        if ($qty !== null) {
            $row['qty'] = round($qty, 3);
        }
        $this->reservations[$k] = $row;

        return true;
    }

    public function deleteReservation(int $orderId, int|InventoryKey $productId): void
    {
        $this->deleteReservationByKey(
            $orderId,
            $productId instanceof InventoryKey ? $productId : new InventoryKey($productId)
        );
    }

    public function deleteReservationByKey(int $orderId, InventoryKey $key): void
    {
        unset($this->reservations[$this->reservationKey($orderId, $key)]);
    }

    public function rekeyReservation(int $orderId, InventoryKey $from, InventoryKey $to): bool
    {
        $legacyKey = $this->reservationKey($orderId, $from);
        $targetKey = $this->reservationKey($orderId, $to);
        $row = $this->reservations[$legacyKey] ?? null;
        if ($row === null || isset($this->reservations[$targetKey])) {
            return false;
        }
        unset($this->reservations[$legacyKey]);
        $row['variant_key'] = $to->variantKey;
        $this->reservations[$targetKey] = $row;

        return true;
    }

    public function runInTransaction(callable $work): void
    {
        $work();
    }

    private function pid(int|InventoryKey $productId): int
    {
        return $productId instanceof InventoryKey ? $productId->productId : $productId;
    }

    private function reservationKey(int $orderId, InventoryKey $key): string
    {
        return $orderId . ':' . $key->identity();
    }
}
