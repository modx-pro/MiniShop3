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
 */
final class PdoInventoryStockStore implements InventoryStockStoreInterface
{
    private string $productsTable;

    private string $reservationsTable;

    public function __construct(
        private readonly PDO $db,
        string $productsTable,
        string $reservationsTable,
    ) {
        $this->productsTable = $this->quoteTable($productsTable);
        $this->reservationsTable = $this->quoteTable($reservationsTable);
    }

    public function getAvailable(int $productId): float
    {
        $sql = "SELECT stock FROM {$this->productsTable} WHERE id = :id";
        $stmt = $this->prepare($sql);
        $stmt->execute(['id' => $productId]);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null) {
            return 0.0;
        }

        return round((float) $value, 3);
    }

    public function tryDecrement(int $productId, float $qty): bool
    {
        $sql = "UPDATE {$this->productsTable}
            SET stock = stock - :qty
            WHERE id = :id AND stock >= :qty_check";
        $stmt = $this->prepare($sql);
        $stmt->execute([
            'qty' => $qty,
            'id' => $productId,
            'qty_check' => $qty,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function increment(int $productId, float $qty): void
    {
        $sql = "UPDATE {$this->productsTable} SET stock = stock + :qty WHERE id = :id";
        $stmt = $this->prepare($sql);
        $stmt->execute([
            'qty' => $qty,
            'id' => $productId,
        ]);
    }

    public function findReservation(int $orderId, int $productId): ?array
    {
        $sql = "SELECT order_id, product_id, qty, state
            FROM {$this->reservationsTable}
            WHERE order_id = :order_id AND product_id = :product_id";
        $stmt = $this->prepare($sql);
        $stmt->execute([
            'order_id' => $orderId,
            'product_id' => $productId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'order_id' => (int) $row['order_id'],
            'product_id' => (int) $row['product_id'],
            'qty' => round((float) $row['qty'], 3),
            'state' => (string) $row['state'],
        ];
    }

    public function saveReservation(int $orderId, int $productId, float $qty, string $state): void
    {
        $now = time();
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
            'product_id' => $productId,
            'qty' => $qty,
            'state' => $state,
            'createdon' => $now,
            'updatedon' => $now,
        ]);
    }

    public function insertReservation(int $orderId, int $productId, float $qty, string $state): bool
    {
        $now = time();
        $sql = "INSERT INTO {$this->reservationsTable}
            (order_id, product_id, qty, state, createdon, updatedon)
            VALUES (:order_id, :product_id, :qty, :state, :createdon, :updatedon)";
        $stmt = $this->prepare($sql);
        try {
            $stmt->execute([
                'order_id' => $orderId,
                'product_id' => $productId,
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
        int $productId,
        string $fromState,
        string $toState,
        ?float $qty = null
    ): bool {
        $assignments = 'state = :to_state, updatedon = :updatedon';
        $params = [
            'to_state' => $toState,
            'updatedon' => time(),
            'order_id' => $orderId,
            'product_id' => $productId,
            'from_state' => $fromState,
        ];
        if ($qty !== null) {
            $assignments .= ', qty = :qty';
            $params['qty'] = $qty;
        }
        $sql = "UPDATE {$this->reservationsTable}
            SET {$assignments}
            WHERE order_id = :order_id AND product_id = :product_id AND state = :from_state";
        $stmt = $this->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() === 1;
    }

    public function deleteReservation(int $orderId, int $productId): void
    {
        $sql = "DELETE FROM {$this->reservationsTable}
            WHERE order_id = :order_id AND product_id = :product_id";
        $stmt = $this->prepare($sql);
        $stmt->execute([
            'order_id' => $orderId,
            'product_id' => $productId,
        ]);
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
        if (!method_exists($this->db, 'prepare')) {
            throw new RuntimeException('Inventory stock store requires prepare() on the DB connection');
        }
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
