<?php

namespace MiniShop3\Services\Inventory;

use InvalidArgumentException;
use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Atomic SQL store: decrement stock with WHERE stock >= qty, ledger in ms3_inventory_reservations.
 *
 * $db must be PDO ($modx->pdo). xPDO/modX exposes beginTransaction() without inTransaction(),
 * so a nested begin throws "There is already an active transaction".
 * Table names are identifier-quoted; values are bound.
 *
 * Stock table is product-level (ms3_products.stock); variantKey is ignored
 * for stock — variant stock is owned by addons (see ProductStockInventory).
 * Ledger rows carry variant_key ('' = product-level) per #1001.
 */
final class PdoInventoryStockStore implements InventoryStockStoreInterface
{
    private string $productsTable;

    private string $reservationsTable;

    private bool $hasVariantKeyColumn = true;

    public function __construct(
        private readonly PDO $db,
        string $productsTable,
        string $reservationsTable,
    ) {
        $this->productsTable = $this->quoteTable($productsTable);
        $this->reservationsTable = $this->quoteTable($reservationsTable);
        // Tolerate installs where the variant_key migration has not run yet.
        try {
            $stmt = $this->db->prepare("SELECT variant_key FROM {$this->reservationsTable} LIMIT 1");
            $this->hasVariantKeyColumn = $stmt !== false;
            if ($stmt) {
                $stmt->execute();
            }
        } catch (\Throwable) {
            $this->hasVariantKeyColumn = false;
        }
    }

    public function getAvailable(InventoryKey $key): float
    {
        $sql = "SELECT stock FROM {$this->productsTable} WHERE id = :id";
        $stmt = $this->prepare($sql);
        $stmt->execute(['id' => $key->productId]);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null) {
            return 0.0;
        }

        return round((float) $value, 3);
    }

    public function tryDecrement(InventoryKey $key, float $qty): bool
    {
        $sql = "UPDATE {$this->productsTable}
            SET stock = stock - :qty
            WHERE id = :id AND stock >= :qty_check";
        $stmt = $this->prepare($sql);
        $stmt->execute([
            'qty' => $qty,
            'id' => $key->productId,
            'qty_check' => $qty,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function decrement(InventoryKey $key, float $qty): bool
    {
        $sql = "UPDATE {$this->productsTable}
            SET stock = stock - :qty
            WHERE id = :id";
        $stmt = $this->prepare($sql);
        $stmt->execute([
            'qty' => $qty,
            'id' => $key->productId,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function increment(InventoryKey $key, float $qty): void
    {
        $sql = "UPDATE {$this->productsTable} SET stock = stock + :qty WHERE id = :id";
        $stmt = $this->prepare($sql);
        $stmt->execute([
            'qty' => $qty,
            'id' => $key->productId,
        ]);
    }

    public function findReservation(int $orderId, InventoryKey $key): ?array
    {
        [$where, $params] = $this->keyWhere($orderId, $key);
        $columns = $this->hasVariantKeyColumn
            ? 'order_id, product_id, variant_key, qty, state'
            : 'order_id, product_id, qty, state';
        $sql = "SELECT {$columns}
            FROM {$this->reservationsTable}
            WHERE {$where}";
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return $this->mapReservationRow($row);
    }

    public function lockReservation(int $orderId, InventoryKey $key): ?array
    {
        [$where, $params] = $this->keyWhere($orderId, $key);
        $columns = $this->hasVariantKeyColumn
            ? 'order_id, product_id, variant_key, qty, state'
            : 'order_id, product_id, qty, state';
        $sql = "SELECT {$columns}
            FROM {$this->reservationsTable}
            WHERE {$where}
            FOR UPDATE";
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->mapReservationRow($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{order_id: int, product_id: int, variant_key: ?string, qty: float, state: string}
     */
    private function mapReservationRow(array $row): array
    {
        $variant = array_key_exists('variant_key', $row) ? (string) ($row['variant_key'] ?? '') : '';
        return [
            'order_id' => (int) $row['order_id'],
            'product_id' => (int) $row['product_id'],
            'variant_key' => $variant === '' ? null : $variant,
            'qty' => round((float) $row['qty'], 3),
            'state' => (string) $row['state'],
        ];
    }

    public function listReservations(int $orderId): array
    {
        $sql = "SELECT order_id, product_id, variant_key, qty, state
            FROM {$this->reservationsTable}
            WHERE order_id = :order_id";
        if (!$this->hasVariantKeyColumn) {
            $sql = "SELECT order_id, product_id, qty, state
                FROM {$this->reservationsTable}
                WHERE order_id = :order_id";
        }
        $stmt = $this->prepare($sql);
        $stmt->execute(['order_id' => $orderId]);
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = $this->mapReservationRow($row);
        }

        return $rows;
    }

    public function saveReservation(int $orderId, InventoryKey $key, float $qty, string $state): void
    {
        $now = time();
        if ($this->hasVariantKeyColumn) {
            $sql = "INSERT INTO {$this->reservationsTable}
                (order_id, product_id, variant_key, qty, state, createdon, updatedon)
                VALUES (:order_id, :product_id, :variant_key, :qty, :state, :createdon, :updatedon)
                ON DUPLICATE KEY UPDATE
                    qty = VALUES(qty),
                    state = VALUES(state),
                    updatedon = VALUES(updatedon)";
            $stmt = $this->prepare($sql);
            $stmt->execute([
                'order_id' => $orderId,
                'product_id' => $key->productId,
                'variant_key' => $key->ledgerVariantKey(),
                'qty' => $qty,
                'state' => $state,
                'createdon' => $now,
                'updatedon' => $now,
            ]);

            return;
        }
        $sql = "INSERT INTO {$this->reservationsTable}
            (order_id, product_id, qty, state, createdon, updatedon)
            VALUES (:order_id, :product_id, :qty, :state, :createdon, :updatedon)
            ON DUPLICATE KEY UPDATE
                qty = VALUES(qty),
                state = VALUES(state),
                updatedon = VALUES(updatedon)";
        $stmt = $this->prepare($sql);
        $stmt->execute([
            'order_id' => $orderId,
            'product_id' => $key->productId,
            'qty' => $qty,
            'state' => $state,
            'createdon' => $now,
            'updatedon' => $now,
        ]);
    }

    public function insertReservation(int $orderId, InventoryKey $key, float $qty, string $state): bool
    {
        $now = time();
        if ($this->hasVariantKeyColumn) {
            $sql = "INSERT INTO {$this->reservationsTable}
                (order_id, product_id, variant_key, qty, state, createdon, updatedon)
                VALUES (:order_id, :product_id, :variant_key, :qty, :state, :createdon, :updatedon)";
            $stmt = $this->prepare($sql);
            try {
                $stmt->execute([
                    'order_id' => $orderId,
                    'product_id' => $key->productId,
                    'variant_key' => $key->ledgerVariantKey(),
                    'qty' => $qty,
                    'state' => $state,
                    'createdon' => $now,
                    'updatedon' => $now,
                ]);
            } catch (\PDOException $exception) {
                if ($this->isDuplicateKey($exception)) {
                    return false;
                }
                throw $exception;
            }

            return $stmt->rowCount() === 1;
        }
        $sql = "INSERT INTO {$this->reservationsTable}
            (order_id, product_id, qty, state, createdon, updatedon)
            VALUES (:order_id, :product_id, :qty, :state, :createdon, :updatedon)";
        $stmt = $this->prepare($sql);
        try {
            $stmt->execute([
                'order_id' => $orderId,
                'product_id' => $key->productId,
                'qty' => $qty,
                'state' => $state,
                'createdon' => $now,
                'updatedon' => $now,
            ]);
        } catch (\PDOException $exception) {
            if ($this->isDuplicateKey($exception)) {
                return false;
            }
            throw $exception;
        }

        return $stmt->rowCount() === 1;
    }

    public function transitionReservation(
        int $orderId,
        InventoryKey $key,
        string $fromState,
        string $toState,
        ?float $qty = null
    ): bool {
        [$where, $params] = $this->keyWhere($orderId, $key);
        $assignments = 'state = :to_state, updatedon = :updatedon';
        $params['to_state'] = $toState;
        $params['updatedon'] = time();
        if ($qty !== null) {
            $assignments .= ', qty = :qty';
            $params['qty'] = $qty;
        }
        $sql = "UPDATE {$this->reservationsTable}
            SET {$assignments}
            WHERE {$where} AND state = :from_state";
        $params['from_state'] = $fromState;
        $stmt = $this->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() === 1;
    }

    public function deleteReservation(int $orderId, InventoryKey $key): void
    {
        [$where, $params] = $this->keyWhere($orderId, $key);
        $sql = "DELETE FROM {$this->reservationsTable} WHERE {$where}";
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * WHERE fragment + params for the ledger identity (order, product, variant).
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function keyWhere(int $orderId, InventoryKey $key): array
    {
        if (!$this->hasVariantKeyColumn) {
            return [
                'order_id = :order_id AND product_id = :product_id',
                ['order_id' => $orderId, 'product_id' => $key->productId],
            ];
        }

        return [
            'order_id = :order_id AND product_id = :product_id AND variant_key = :variant_key',
            [
                'order_id' => $orderId,
                'product_id' => $key->productId,
                'variant_key' => $key->ledgerVariantKey(),
            ],
        ];
    }

    public function runInTransaction(callable $work): void
    {
        $alreadyOpen = $this->db->inTransaction();
        if (!$alreadyOpen) {
            $this->db->beginTransaction();
        }
        try {
            $work();
            if (!$alreadyOpen) {
                $this->db->commit();
            }
        } catch (\Throwable $exception) {
            if (!$alreadyOpen && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function isDuplicateKey(\PDOException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? $exception->getCode();

        return (string) $sqlState === '23000';
    }

    private function prepare(string $sql): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        if (!$stmt instanceof PDOStatement) {
            throw new RuntimeException('Inventory stock store failed to prepare SQL');
        }

        return $stmt;
    }

    private function quoteTable(string $table): string
    {
        $bare = str_replace('`', '', $table);
        if ($bare === '' || !preg_match('/^[A-Za-z0-9_]+$/', $bare)) {
            throw new InvalidArgumentException('Invalid inventory table name');
        }

        return '`' . $bare . '`';
    }
}
