<?php

declare(strict_types=1);

namespace MiniShop3\Services\Shipment;

use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * @phpstan-import-type ShipmentRow from ShipmentStoreInterface
 */
final class PdoShipmentStore implements ShipmentStoreInterface
{
    private string $table;

    private string $eventsTable;

    /** True only when this store opened the current transaction (nested-safe). */
    private bool $ownsTransaction = false;

    public function __construct(
        private readonly PDO $db,
        string $table,
        string $eventsTable,
    ) {
        $this->table = $this->quoteTable($table);
        $this->eventsTable = $this->quoteTable($eventsTable);
    }

    public function create(
        int $orderId,
        int $deliveryId,
        string $status,
        ?string $provider,
        array $meta = [],
    ): array {
        $now = time();
        $sql = "INSERT INTO {$this->table}
            (order_id, delivery_id, status, provider, meta, createdon, updatedon)
            VALUES (:order_id, :delivery_id, :status, :provider, :meta, :createdon, :updatedon)";
        $stmt = $this->prepare($sql);
        $inserted = $this->executeWrite($stmt, [
            'order_id' => $orderId,
            'delivery_id' => $deliveryId,
            'status' => $status,
            'provider' => $provider,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'createdon' => $now,
            'updatedon' => $now,
        ], allowDuplicate: true);
        if (!$inserted) {
            $existing = $this->findByOrderId($orderId);
            if ($existing !== null) {
                return $existing;
            }
            throw new RuntimeException('shipment insert hit integrity constraint without existing row');
        }

        $id = (int) $this->db->lastInsertId();
        $row = $this->findById($id);
        if ($row === null) {
            throw new RuntimeException('shipment insert did not persist');
        }

        return $row;
    }

    public function update(int $id, array $fields): array
    {
        $row = $this->findById($id);
        if ($row === null) {
            throw new RuntimeException('shipment not found');
        }
        unset($fields['id'], $fields['createdon']);
        if ($fields === []) {
            return $row;
        }
        if (array_key_exists('meta', $fields) && is_array($fields['meta'])) {
            $fields['meta'] = json_encode($fields['meta'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        $fields['updatedon'] = time();
        $sets = [];
        $params = ['id' => $id];
        foreach ($fields as $key => $value) {
            if (!preg_match('/^[a-z_]+$/', (string) $key)) {
                continue;
            }
            $sets[] = "`{$key}` = :{$key}";
            $params[$key] = $value;
        }
        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $this->executeWrite($this->prepare($sql), $params, allowDuplicate: false);
        $updated = $this->findById($id);
        if ($updated === null) {
            throw new RuntimeException('shipment update did not persist');
        }

        return $updated;
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne("SELECT * FROM {$this->table} WHERE id = :id", ['id' => $id]);
    }

    public function findByIdForUpdate(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM {$this->table} WHERE id = :id FOR UPDATE",
            ['id' => $id],
        );
    }

    public function findByOrderId(int $orderId): ?array
    {
        return $this->fetchOne("SELECT * FROM {$this->table} WHERE order_id = :order_id", ['order_id' => $orderId]);
    }

    public function findByExternalId(string $provider, string $externalId, ?int $deliveryId = null): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE provider = :provider AND external_id = :external_id";
        $params = ['provider' => $provider, 'external_id' => $externalId];
        if ($deliveryId !== null) {
            $sql .= ' AND delivery_id = :delivery_id';
            $params['delivery_id'] = $deliveryId;
        }

        return $this->fetchOne($sql, $params);
    }

    public function hasEvent(int $shipmentId, string $providerEventId): bool
    {
        $sql = "SELECT 1 FROM {$this->eventsTable}
            WHERE shipment_id = :shipment_id AND provider_event_id = :provider_event_id
            LIMIT 1";
        $stmt = $this->prepare($sql);
        $this->executeWrite($stmt, [
            'shipment_id' => $shipmentId,
            'provider_event_id' => $providerEventId,
        ], allowDuplicate: false);

        return $stmt->fetchColumn() !== false;
    }

    public function claimEvent(int $shipmentId, string $providerEventId): bool
    {
        $sql = "INSERT INTO {$this->eventsTable}
            (shipment_id, provider_event_id, createdon)
            VALUES (:shipment_id, :provider_event_id, :createdon)";

        return $this->executeWrite($this->prepare($sql), [
            'shipment_id' => $shipmentId,
            'provider_event_id' => $providerEventId,
            'createdon' => time(),
        ], allowDuplicate: true);
    }

    public function recordEvent(int $shipmentId, string $providerEventId): void
    {
        $this->claimEvent($shipmentId, $providerEventId);
    }

    public function beginTransaction(): void
    {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $this->ownsTransaction = true;
        }
    }

    public function commit(): void
    {
        if (!$this->ownsTransaction) {
            return;
        }
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
        $this->ownsTransaction = false;
    }

    public function rollBack(): void
    {
        if (!$this->ownsTransaction) {
            return;
        }
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        $this->ownsTransaction = false;
    }

    /**
     * Run a write (or parameterised read) without relying on PDO ERRMODE.
     * MODX opens `$modx->pdo` with ERRMODE_SILENT: unique violations return false
     * from execute() instead of throwing. Never change ATTR_ERRMODE on the shared handle.
     *
     * @param array<string, mixed> $params
     * @return bool true on success; false only when allowDuplicate and SQLSTATE 23000
     */
    private function executeWrite(PDOStatement $stmt, array $params, bool $allowDuplicate): bool
    {
        try {
            $ok = $stmt->execute($params);
        } catch (PDOException $exception) {
            if ($allowDuplicate && $this->isIntegrityViolation($exception->errorInfo)) {
                return false;
            }
            throw $exception;
        }

        if ($ok === true) {
            return true;
        }

        $errorInfo = $stmt->errorInfo();
        if ($allowDuplicate && $this->isIntegrityViolation($errorInfo)) {
            return false;
        }

        throw $this->statementFailure('Shipment store statement failed', $stmt);
    }

    /**
     * @param array<int, mixed>|null $errorInfo
     */
    private function isIntegrityViolation(?array $errorInfo): bool
    {
        return is_array($errorInfo) && (string) ($errorInfo[0] ?? '') === '23000';
    }

    private function statementFailure(string $message, PDOStatement $stmt): RuntimeException
    {
        $info = $stmt->errorInfo();
        $detail = implode(' | ', array_map(
            static fn (mixed $part): string => is_scalar($part) || $part === null ? (string) $part : gettype($part),
            $info,
        ));

        return new RuntimeException($message . ($detail !== '' ? ': ' . $detail : ''));
    }

    /**
     * @param array<string, mixed> $params
     * @return ShipmentRow|null
     */
    private function fetchOne(string $sql, array $params): ?array
    {
        $stmt = $this->prepare($sql);
        $this->executeWrite($stmt, $params, allowDuplicate: false);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return ShipmentRow
     */
    private function hydrate(array $row): array
    {
        $meta = $row['meta'] ?? [];
        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($meta)) {
            $meta = [];
        }

        return [
            'id' => (int) $row['id'],
            'order_id' => (int) $row['order_id'],
            'delivery_id' => (int) $row['delivery_id'],
            'status' => (string) $row['status'],
            'tracking_number' => $this->nullableString($row['tracking_number'] ?? null),
            'external_id' => $this->nullableString($row['external_id'] ?? null),
            'provider' => $this->nullableString($row['provider'] ?? null),
            'carrier' => $this->nullableString($row['carrier'] ?? null),
            'shipped_at' => isset($row['shipped_at']) ? (int) $row['shipped_at'] : null,
            'delivered_at' => isset($row['delivered_at']) ? (int) $row['delivered_at'] : null,
            'last_event_id' => $this->nullableString($row['last_event_id'] ?? null),
            'meta' => $meta,
            'createdon' => (int) ($row['createdon'] ?? 0),
            'updatedon' => (int) ($row['updatedon'] ?? 0),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        return isset($value) && $value !== '' ? (string) $value : null;
    }

    private function prepare(string $sql): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        if (!$stmt instanceof PDOStatement) {
            throw new RuntimeException('Shipment store failed to prepare SQL');
        }

        return $stmt;
    }

    private function quoteTable(string $table): string
    {
        $bare = str_replace('`', '', $table);
        if ($bare === '' || !preg_match('/^[A-Za-z0-9_]+$/', $bare)) {
            throw new InvalidArgumentException('Invalid shipment table name');
        }

        return '`' . $bare . '`';
    }
}
