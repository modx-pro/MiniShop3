<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Support;

use PDO;
use xPDO\xPDO;

/**
 * PDO-backed xPDO stand-in for OptionSyncService (SQLite or MySQL).
 */
final class ProductOptionPdoXpdo extends xPDO
{
    private PDO $pdo;

    private bool $sqlite;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->sqlite = str_starts_with((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite');
        $this->ensureSchema();
    }

    public static function sqliteMemory(): self
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return new self($pdo);
    }

    public function getTableName($className, $includeDb = false): string
    {
        return 'ms2_product_options';
    }

    public function prepare($sql, $options = [])
    {
        return $this->pdo->prepare($this->normalizeSql((string) $sql));
    }

    public function newQuery($class, $criteria = null, $cacheFlag = true): ProductOptionPdoQuery
    {
        return new ProductOptionPdoQuery($this->pdo, is_array($criteria) ? $criteria : [], $this->sqlite);
    }

    public function log($level, $msg): void
    {
    }

    /**
     * @param list<array{product_id: int, key: string, value: string}> $rows
     */
    public function seed(array $rows): void
    {
        $stmt = $this->pdo->prepare(
            $this->normalizeSql('INSERT INTO ms2_product_options (product_id, `key`, value) VALUES (?, ?, ?)')
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
        $sql = $this->normalizeSql(
            'SELECT product_id, `key` AS `key`, value FROM ms2_product_options ORDER BY product_id, `key`, value'
        );
        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            return [
                'product_id' => (int) $row['product_id'],
                'key' => (string) $row['key'],
                'value' => (string) $row['value'],
            ];
        }, $rows);
    }

    public function reset(): void
    {
        $this->pdo->query('DELETE FROM ms2_product_options');
    }

    private function ensureSchema(): void
    {
        if ($this->sqlite) {
            $this->pdo->query(
                'CREATE TABLE IF NOT EXISTS ms2_product_options (
                    product_id INTEGER NOT NULL,
                    "key" TEXT NOT NULL,
                    value TEXT NOT NULL
                )'
            );

            return;
        }

        $this->pdo->query(
            'CREATE TABLE IF NOT EXISTS ms2_product_options (
                product_id INT NOT NULL,
                `key` VARCHAR(191) NOT NULL,
                value TEXT NOT NULL,
                INDEX idx_product_key (product_id, `key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private function normalizeSql(string $sql): string
    {
        if ($this->sqlite) {
            return str_replace('`', '"', $sql);
        }

        return $sql;
    }
}

final class ProductOptionPdoQuery
{
    /** @var \PDOStatement|null */
    public $stmt;

    /** @var array<string, mixed> */
    private array $criteria;

    /** @var list<string> */
    private array $keyIn = [];

    public function __construct(
        private PDO $pdo,
        array $criteria,
        private bool $sqlite
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

    public function where($criteria): self
    {
        if (isset($criteria['key:IN']) && is_array($criteria['key:IN'])) {
            $this->keyIn = array_values(array_map('strval', $criteria['key:IN']));
        }

        return $this;
    }

    public function prepare(): bool
    {
        $keyCol = $this->sqlite ? '"key"' : '`key`';
        $sql = "SELECT {$keyCol} AS {$keyCol}, value FROM ms2_product_options WHERE product_id = ?";
        $params = [(int) ($this->criteria['product_id'] ?? 0)];

        if ($this->keyIn !== []) {
            $placeholders = implode(',', array_fill(0, count($this->keyIn), '?'));
            $sql .= " AND {$keyCol} IN ({$placeholders})";
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
