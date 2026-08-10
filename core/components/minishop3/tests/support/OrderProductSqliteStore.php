<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Support;

use MiniShop3\Model\msOrderProduct;
use PDO;

/**
 * In-memory SQLite store for ms_order_product rows used by Cart Level-2 tests.
 */
final class OrderProductSqliteStore
{
    private PDO $pdo;

    private int $nextId = 1;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->query(
            'CREATE TABLE ms_order_product (
                id INTEGER PRIMARY KEY,
                order_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                product_key TEXT NOT NULL,
                name TEXT NOT NULL DEFAULT \'\',
                count INTEGER NOT NULL DEFAULT 0,
                price REAL NOT NULL DEFAULT 0,
                weight REAL NOT NULL DEFAULT 0,
                cost REAL NOT NULL DEFAULT 0,
                options TEXT NOT NULL DEFAULT \'{}\',
                properties TEXT NOT NULL DEFAULT \'{}\'
            )'
        );
    }

    public function insert(array $fields): int
    {
        $id = (int) ($fields['id'] ?? 0);
        if ($id <= 0) {
            $id = $this->nextId++;
        } else {
            $this->nextId = max($this->nextId, $id + 1);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO ms_order_product (
                id, order_id, product_id, product_key, name, count, price, weight, cost, options, properties
            ) VALUES (
                :id, :order_id, :product_id, :product_key, :name, :count, :price, :weight, :cost, :options, :properties
            )'
        );
        $stmt->execute([
            ':id' => $id,
            ':order_id' => (int) ($fields['order_id'] ?? 0),
            ':product_id' => (int) ($fields['product_id'] ?? 0),
            ':product_key' => (string) ($fields['product_key'] ?? ''),
            ':name' => (string) ($fields['name'] ?? ''),
            ':count' => (int) ($fields['count'] ?? 0),
            ':price' => (float) ($fields['price'] ?? 0),
            ':weight' => (float) ($fields['weight'] ?? 0),
            ':cost' => (float) ($fields['cost'] ?? 0),
            ':options' => $this->encodeJson($fields['options'] ?? []),
            ':properties' => $this->encodeJson($fields['properties'] ?? []),
        ]);

        return $id;
    }

    public function update(int $id, array $fields): bool
    {
        $existing = $this->findById($id);
        if ($existing === null) {
            return false;
        }

        $merged = array_merge($existing, $fields, ['id' => $id]);
        $stmt = $this->pdo->prepare(
            'UPDATE ms_order_product SET
                order_id = :order_id,
                product_id = :product_id,
                product_key = :product_key,
                name = :name,
                count = :count,
                price = :price,
                weight = :weight,
                cost = :cost,
                options = :options,
                properties = :properties
             WHERE id = :id'
        );

        return $stmt->execute([
            ':id' => $id,
            ':order_id' => (int) ($merged['order_id'] ?? 0),
            ':product_id' => (int) ($merged['product_id'] ?? 0),
            ':product_key' => (string) ($merged['product_key'] ?? ''),
            ':name' => (string) ($merged['name'] ?? ''),
            ':count' => (int) ($merged['count'] ?? 0),
            ':price' => (float) ($merged['price'] ?? 0),
            ':weight' => (float) ($merged['weight'] ?? 0),
            ':cost' => (float) ($merged['cost'] ?? 0),
            ':options' => $this->encodeJson($merged['options'] ?? []),
            ':properties' => $this->encodeJson($merged['properties'] ?? []),
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM ms_order_product WHERE id = ?');

        return $stmt->execute([$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ms_order_product WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->decodeRow($row);
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array<string, mixed>|null
     */
    public function findOne(array $criteria): ?array
    {
        [$sql, $params] = $this->buildWhere($criteria);
        $stmt = $this->pdo->prepare('SELECT * FROM ms_order_product WHERE ' . $sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->decodeRow($row);
    }

    /**
     * @param array<string, mixed> $criteria
     * @return list<array<string, mixed>>
     */
    public function findAll(array $criteria = []): array
    {
        if ($criteria === []) {
            $stmt = $this->pdo->query('SELECT * FROM ms_order_product ORDER BY id');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            [$sql, $params] = $this->buildWhere($criteria);
            $stmt = $this->pdo->prepare('SELECT * FROM ms_order_product WHERE ' . $sql . ' ORDER BY id');
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        return array_map(fn(array $row): array => $this->decodeRow($row), $rows);
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ms_order_product')->fetchColumn();
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildWhere(array $criteria): array
    {
        $parts = [];
        $params = [];
        foreach ($criteria as $key => $value) {
            $parts[] = $key . ' = ?';
            $params[] = $value;
        }

        return [implode(' AND ', $parts), $params];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decodeRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['order_id'] = (int) $row['order_id'];
        $row['product_id'] = (int) $row['product_id'];
        $row['count'] = (int) $row['count'];
        $row['price'] = (float) $row['price'];
        $row['weight'] = (float) $row['weight'];
        $row['cost'] = (float) $row['cost'];
        $row['options'] = $this->decodeJson((string) $row['options']);
        $row['properties'] = $this->decodeJson((string) $row['properties']);

        return $row;
    }

    private function encodeJson(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value ?? [], JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}

/**
 * msOrderProduct that persists fields into OrderProductSqliteStore.
 */
final class SqliteOrderProduct extends msOrderProduct
{
    /** @var array<string, mixed> */
    private array $fields = [];

    private ?OrderProductSqliteStore $store = null;

    private bool $persisted = false;

    public function bindStore(OrderProductSqliteStore $store): void
    {
        $this->store = $store;
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function hydrate(array $fields): void
    {
        $this->fields = $fields;
        $this->persisted = isset($fields['id']);
    }

    public function fromArray($fields, $keyPrefix = '', $setPrimaryKeys = false, $rawValues = false, $adhoc = false)
    {
        foreach ($fields as $key => $value) {
            $this->fields[$key] = $value;
        }

        return true;
    }

    public function get($k, $format = null, $formatTemplate = null)
    {
        return $this->fields[$k] ?? null;
    }

    public function set($key, $value)
    {
        $this->fields[$key] = $value;

        return true;
    }

    public function toArray($keyPrefix = '', $rawValues = false, $excludeLazy = false, $includeRelated = false)
    {
        return $this->fields;
    }

    public function save($cacheFlag = null)
    {
        if ($this->store === null) {
            return false;
        }

        if ($this->persisted && isset($this->fields['id'])) {
            return $this->store->update((int) $this->fields['id'], $this->fields);
        }

        $id = $this->store->insert($this->fields);
        $this->fields['id'] = $id;
        $this->persisted = true;

        return true;
    }

    public function remove(array $ancestors = [])
    {
        if ($this->store === null || !isset($this->fields['id'])) {
            return false;
        }

        $ok = $this->store->delete((int) $this->fields['id']);
        $this->persisted = false;

        return $ok;
    }
}
