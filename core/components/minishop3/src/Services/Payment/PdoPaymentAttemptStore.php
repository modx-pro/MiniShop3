<?php

declare(strict_types=1);

namespace MiniShop3\Services\Payment;

use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * SQL store for payment attempts and idempotent webhook events.
 *
 * $db is PDO or xPDO (prepare + execute).
 *
 * @phpstan-import-type PaymentAttemptRow from PaymentAttemptStoreInterface
 */
final class PdoPaymentAttemptStore implements PaymentAttemptStoreInterface
{
    private string $attemptsTable;

    private string $eventsTable;

    public function __construct(
        private readonly object $db,
        string $attemptsTable,
        string $eventsTable,
    ) {
        $this->attemptsTable = $this->quoteTable($attemptsTable);
        $this->eventsTable = $this->quoteTable($eventsTable);
    }

    public function create(
        int $orderId,
        int $paymentMethodId,
        string $provider,
        ?string $externalId,
        string $status,
        float $amount,
        string $currency,
        array $payload,
    ): array {
        $now = time();
        $sql = "INSERT INTO {$this->attemptsTable}
            (order_id, payment_method_id, provider, external_id, status, amount, currency, payload,
             refunded_amount, createdon, updatedon)
            VALUES (:order_id, :payment_method_id, :provider, :external_id, :status, :amount, :currency, :payload,
             0, :createdon, :updatedon)";
        $stmt = $this->prepare($sql);
        $stmt->execute([
            'order_id' => $orderId,
            'payment_method_id' => $paymentMethodId,
            'provider' => $provider,
            'external_id' => $externalId,
            'status' => $status,
            'amount' => $amount,
            'currency' => $currency,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'createdon' => $now,
            'updatedon' => $now,
        ]);
        $id = (int) $this->lastInsertId();
        $row = $this->findById($id);
        if ($row === null) {
            throw new RuntimeException('Failed to load created payment attempt');
        }

        return $row;
    }

    public function update(int $id, array $fields): array
    {
        $allowed = [
            'external_id',
            'status',
            'amount',
            'currency',
            'payload',
            'refunded_amount',
            'refund_external_id',
            'refundedon',
        ];
        $set = ['updatedon = :updatedon'];
        $params = ['id' => $id, 'updatedon' => time()];
        foreach ($allowed as $column) {
            if (!array_key_exists($column, $fields)) {
                continue;
            }
            $set[] = "{$column} = :{$column}";
            $value = $fields[$column];
            if ($column === 'payload' && is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
            $params[$column] = $value;
        }
        $sql = 'UPDATE ' . $this->attemptsTable . ' SET ' . implode(', ', $set) . ' WHERE id = :id';
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        $row = $this->findById($id);
        if ($row === null) {
            throw new RuntimeException('Payment attempt not found after update');
        }

        return $row;
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne("SELECT * FROM {$this->attemptsTable} WHERE id = :id", ['id' => $id]);
    }

    public function findByExternalId(string $provider, string $externalId, ?int $paymentMethodId = null): ?array
    {
        $sql = "SELECT * FROM {$this->attemptsTable} WHERE provider = :provider AND external_id = :external_id";
        $params = [
            'provider' => $provider,
            'external_id' => $externalId,
        ];
        if ($paymentMethodId !== null) {
            $sql .= ' AND payment_method_id = :payment_method_id';
            $params['payment_method_id'] = $paymentMethodId;
        }

        return $this->fetchOne($sql, $params);
    }

    public function findLatestForOrder(int $orderId, ?int $paymentMethodId = null): ?array
    {
        $sql = "SELECT * FROM {$this->attemptsTable} WHERE order_id = :order_id";
        $params = ['order_id' => $orderId];
        if ($paymentMethodId !== null) {
            $sql .= ' AND payment_method_id = :payment_method_id';
            $params['payment_method_id'] = $paymentMethodId;
        }

        return $this->fetchOne($sql . ' ORDER BY id DESC LIMIT 1', $params);
    }

    public function recordEvent(int $attemptId, string $eventType, string $providerEventId): bool
    {
        $sql = "INSERT INTO {$this->eventsTable}
            (attempt_id, event_type, provider_event_id, createdon)
            VALUES (:attempt_id, :event_type, :provider_event_id, :createdon)";
        $stmt = $this->prepare($sql);
        try {
            $stmt->execute([
                'attempt_id' => $attemptId,
                'event_type' => $eventType,
                'provider_event_id' => $providerEventId,
                'createdon' => time(),
            ]);
        } catch (PDOException $exception) {
            if ($this->isDuplicate($exception)) {
                return false;
            }
            throw $exception;
        }

        return true;
    }

    public function hasEvent(int $attemptId, string $eventType, string $providerEventId): bool
    {
        $sql = "SELECT 1 FROM {$this->eventsTable}
            WHERE attempt_id = :attempt_id AND event_type = :event_type AND provider_event_id = :provider_event_id
            LIMIT 1";
        $stmt = $this->prepare($sql);
        $stmt->execute([
            'attempt_id' => $attemptId,
            'event_type' => $eventType,
            'provider_event_id' => $providerEventId,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $params
     * @return PaymentAttemptRow|null
     */
    private function fetchOne(string $sql, array $params): ?array
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);

        return $this->hydrate($stmt->fetch(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<string, mixed>|false $row
     * @return PaymentAttemptRow|null
     */
    private function hydrate(array|false $row): ?array
    {
        if ($row === false) {
            return null;
        }
        $payload = [];
        if (isset($row['payload']) && is_string($row['payload']) && $row['payload'] !== '') {
            $decoded = json_decode($row['payload'], true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        return [
            'id' => (int) $row['id'],
            'order_id' => (int) $row['order_id'],
            'payment_method_id' => (int) $row['payment_method_id'],
            'provider' => (string) $row['provider'],
            'external_id' => $row['external_id'] !== null ? (string) $row['external_id'] : null,
            'status' => (string) $row['status'],
            'amount' => round((float) $row['amount'], 3),
            'currency' => (string) $row['currency'],
            'payload' => $payload,
            'refunded_amount' => round((float) $row['refunded_amount'], 3),
            'refund_external_id' => $row['refund_external_id'] !== null
                ? (string) $row['refund_external_id']
                : null,
            'refundedon' => $row['refundedon'] !== null ? (int) $row['refundedon'] : null,
            'createdon' => (int) ($row['createdon'] ?? 0),
            'updatedon' => (int) ($row['updatedon'] ?? 0),
        ];
    }

    private function isDuplicate(PDOException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? $exception->getCode();

        return (string) $sqlState === '23000';
    }

    private function lastInsertId(): string
    {
        if (method_exists($this->db, 'lastInsertId')) {
            return (string) $this->db->lastInsertId();
        }

        throw new RuntimeException('Payment attempt store requires lastInsertId()');
    }

    private function prepare(string $sql): PDOStatement
    {
        if (!method_exists($this->db, 'prepare')) {
            throw new RuntimeException('Payment attempt store requires prepare() on the DB connection');
        }
        $stmt = $this->db->prepare($sql);
        if (!$stmt instanceof PDOStatement) {
            throw new RuntimeException('Payment attempt store failed to prepare SQL');
        }

        return $stmt;
    }

    private function quoteTable(string $table): string
    {
        $bare = str_replace('`', '', $table);
        if ($bare === '' || !preg_match('/^[A-Za-z0-9_]+$/', $bare)) {
            throw new InvalidArgumentException('Invalid payment attempt table name');
        }

        return '`' . $bare . '`';
    }
}
