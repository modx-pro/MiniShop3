<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Support;

use PDO;
use xPDO\xPDO;

/**
 * In-memory SQLite xPDO stand-in for OptionSyncService Level-2 tests.
 * Uses real PDOStatement so production typehints stay satisfied.
 */
final class ProductOptionSqliteXpdo extends xPDO
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->query(
            'CREATE TABLE ms2_product_options (
                product_id INTEGER NOT NULL,
                "key" TEXT NOT NULL,
                value TEXT NOT NULL
            )'
        );
    }

    public function getTableName($className, $includeDb = false): string
    {
        return 'ms2_product_options';
    }

    public function prepare($sql, $options = [])
    {
        return $this->pdo->prepare($this->toSqliteSql((string) $sql));
    }

    /**
     * @param class-string $class
     * @param array<string, mixed>|null $criteria
     */
    public function newQuery($class, $criteria = null, $cacheFlag = true): ProductOptionSqliteQuery
    {
        return new ProductOptionSqliteQuery($this->pdo, is_array($criteria) ? $criteria : []);
    }

    public function log($level, $msg): void
    {
    }

    /**
     * Seed rows for tests (bypasses service).
     *
     * @param list<array{product_id: int, key: string, value: string}> $rows
     */
    public function seed(array $rows): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ms2_product_options (product_id, "key", value) VALUES (?, ?, ?)'
        );
        foreach ($rows as $row) {
            $stmt->execute([(int) $row['product_id'], (string) $row['key'], (string) $row['value']]);
        }
    }

    /**
     * @return list<array{product_id: int, key: string, value: string}>
     */
    public function allRows(): array
    {
        $stmt = $this->pdo->query(
            'SELECT product_id, "key" AS "key", value FROM ms2_product_options ORDER BY product_id, "key", value'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            return [
                'product_id' => (int) $row['product_id'],
                'key' => (string) $row['key'],
                'value' => (string) $row['value'],
            ];
        }, $rows);
    }

    private function toSqliteSql(string $sql): string
    {
        // Production SQL uses MySQL backticks; SQLite wants double quotes.
        return str_replace('`', '"', $sql);
    }
}

/**
 * Minimal xPDOQuery stand-in for OptionSyncService::getForProduct().
 */
final class ProductOptionSqliteQuery
{
    /** @var \PDOStatement|null */
    public $stmt;

    /** @var array<string, mixed> */
    private array $criteria;

    /** @var list<string> */
    private array $keyIn = [];

    public function __construct(
        private PDO $pdo,
        array $criteria
    ) {
        $this->criteria = $criteria;
    }

    public function select($columns): self
    {
        return $this;
    }

    public function sortby($column, $dir = 'ASC'): self
    {
        return $this;
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function where($criteria): self
    {
        if (isset($criteria['key:IN']) && is_array($criteria['key:IN'])) {
            $this->keyIn = array_values(array_map('strval', $criteria['key:IN']));
        }

        return $this;
    }

    public function prepare(): bool
    {
        $sql = 'SELECT "key" AS "key", value FROM ms2_product_options WHERE product_id = ?';
        $params = [(int) ($this->criteria['product_id'] ?? 0)];

        if ($this->keyIn !== []) {
            $placeholders = implode(',', array_fill(0, count($this->keyIn), '?'));
            $sql .= " AND \"key\" IN ({$placeholders})";
            foreach ($this->keyIn as $key) {
                $params[] = $key;
            }
        }

        $sql .= ' ORDER BY value';
        $this->stmt = $this->pdo->prepare($sql);
        foreach ($params as $index => $value) {
            $this->stmt->bindValue($index + 1, $value);
        }

        return true;
    }
}
