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

    public function __construct(
        private readonly PDO $db,
        string $table,
    ) {
        $this->table = $this->quoteTable($table);
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
        try {
            $stmt->execute([
                'order_id' => $orderId,
                'delivery_id' => $deliveryId,
                'status' => $status,
                'provider' => $provider,
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'createdon' => $now,
                'updatedon' => $now,
            ]);
        } catch (PDOException $exception) {
            if ($this->isDuplicate($exception)) {
                $existing = $this->findByOrderId($orderId);
                if ($existing !== null) {
                    return $existing;
                }
            }
            throw $exception;
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
        $this->prepare($sql)->execute($params);
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

    /**
     * @param array<string, mixed> $params
     * @return ShipmentRow|null
     */
    private function fetchOne(string $sql, array $params): ?array
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
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

    private function isDuplicate(PDOException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? $exception->getCode();

        return (string) $sqlState === '23000';
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
