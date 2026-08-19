<?php

namespace MiniShop3\Services\Inventory;

use InvalidArgumentException;
use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Atomic SQL store: decrement stock with WHERE stock >= qty, ledger in ms3_inventory_reservations.
 *
 * $db is PDO or xPDO (prepare + execute). Table names are identifier-quoted; values are bound.
 */
final class PdoInventoryStockStore implements InventoryStockStoreInterface
{
    private string $productsTable;

    private string $reservationsTable;

    public function __construct(
        private readonly object $db,
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

    public function runInTransaction(callable $work): void
    {
        $alreadyOpen = method_exists($this->db, 'inTransaction') && $this->db->inTransaction();
        $started = false;
        if (!$alreadyOpen && method_exists($this->db, 'beginTransaction')) {
            $this->db->beginTransaction();
            $started = true;
        }
        try {
            $work();
            if ($started) {
                $this->commit();
            }
        } catch (\Throwable $exception) {
            if ($started) {
                $this->rollback();
            }
            throw $exception;
        }
    }

    private function commit(): void
    {
        if (method_exists($this->db, 'commit')) {
            $this->db->commit();
        }
    }

    private function rollback(): void
    {
        if (method_exists($this->db, 'rollBack')) {
            $this->db->rollBack();

            return;
        }
        if (method_exists($this->db, 'rollback')) {
            $this->db->rollback();
        }
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
