<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Support;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerToken;
use MiniShop3\Utils\ApiTokenExpiry;
use PDO;

/**
 * PDO store for ms_customer + ms_customer_token used by AuthManager lifecycle tests.
 */
final class CustomerAuthPdoStore
{
    private PDO $pdo;

    private bool $sqlite;

    private int $nextCustomerId = 1;

    private int $nextTokenId = 1;

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

    public function reset(): void
    {
        $this->pdo->query('DELETE FROM ms_customer_token');
        $this->pdo->query('DELETE FROM ms_customer');
        $this->nextCustomerId = 1;
        $this->nextTokenId = 1;
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function insertCustomer(array $fields): int
    {
        $id = (int) ($fields['id'] ?? 0);
        if ($id <= 0) {
            $id = $this->nextCustomerId++;
        } else {
            $this->nextCustomerId = max($this->nextCustomerId, $id + 1);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO ms_customer (
                id, email, password, is_active, is_blocked, failed_login_attempts, blocked_until, last_login_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $id,
            (string) ($fields['email'] ?? ''),
            (string) ($fields['password'] ?? ''),
            (int) ($fields['is_active'] ?? 1),
            (int) ($fields['is_blocked'] ?? 0),
            (int) ($fields['failed_login_attempts'] ?? 0),
            $fields['blocked_until'] ?? null,
            $fields['last_login_at'] ?? null,
        ]);

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCustomerById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ms_customer WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->decodeCustomer($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCustomerByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ms_customer WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->decodeCustomer($row);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function updateCustomer(int $id, array $fields): bool
    {
        $existing = $this->findCustomerById($id);
        if ($existing === null) {
            return false;
        }
        $merged = array_merge($existing, $fields, ['id' => $id]);
        $stmt = $this->pdo->prepare(
            'UPDATE ms_customer SET
                email = ?, password = ?, is_active = ?, is_blocked = ?, failed_login_attempts = ?,
                blocked_until = ?, last_login_at = ?
             WHERE id = ?'
        );

        return $stmt->execute([
            (string) $merged['email'],
            (string) ($merged['password'] ?? ''),
            (int) $merged['is_active'],
            (int) $merged['is_blocked'],
            (int) $merged['failed_login_attempts'],
            $merged['blocked_until'],
            $merged['last_login_at'],
            $id,
        ]);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function insertToken(array $fields): int
    {
        $id = (int) ($fields['id'] ?? 0);
        if ($id <= 0) {
            $id = $this->nextTokenId++;
        } else {
            $this->nextTokenId = max($this->nextTokenId, $id + 1);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO ms_customer_token (
                id, customer_id, token, type, expires_at, created_at, used_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $id,
            (int) ($fields['customer_id'] ?? 0),
            (string) ($fields['token'] ?? ''),
            (string) ($fields['type'] ?? msCustomerToken::TYPE_API),
            (string) ($fields['expires_at'] ?? ''),
            $fields['created_at'] ?? date('Y-m-d H:i:s'),
            $fields['used_at'] ?? null,
        ]);

        return $id;
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array<string, mixed>|null
     */
    public function findToken(array $criteria): ?array
    {
        $parts = [];
        $params = [];
        foreach ($criteria as $key => $value) {
            if (str_contains((string) $key, ':')) {
                [$col, $op] = explode(':', (string) $key, 2);
                if ($op === '<') {
                    $parts[] = $col . ' < ?';
                    $params[] = $value;
                    continue;
                }
            }
            $parts[] = $key . ' = ?';
            $params[] = $value;
        }
        $sql = 'SELECT * FROM ms_customer_token WHERE ' . implode(' AND ', $parts) . ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->decodeToken($row);
    }

    /**
     * @param array<string, mixed> $criteria
     * @return list<array<string, mixed>>
     */
    public function findTokens(array $criteria = []): array
    {
        if ($criteria === []) {
            $stmt = $this->pdo->query('SELECT * FROM ms_customer_token ORDER BY id');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $parts = [];
            $params = [];
            foreach ($criteria as $key => $value) {
                if (str_contains((string) $key, ':')) {
                    [$col, $op] = explode(':', (string) $key, 2);
                    if ($op === '<') {
                        $parts[] = $col . ' < ?';
                        $params[] = $value;
                        continue;
                    }
                }
                $parts[] = $key . ' = ?';
                $params[] = $value;
            }
            $stmt = $this->pdo->prepare(
                'SELECT * FROM ms_customer_token WHERE ' . implode(' AND ', $parts) . ' ORDER BY id'
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        return array_map(fn(array $row): array => $this->decodeToken($row), $rows);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function updateToken(int $id, array $fields): bool
    {
        $existing = $this->findToken(['id' => $id]);
        if ($existing === null) {
            return false;
        }
        $merged = array_merge($existing, $fields, ['id' => $id]);
        $stmt = $this->pdo->prepare(
            'UPDATE ms_customer_token SET
                customer_id = ?, token = ?, type = ?, expires_at = ?, created_at = ?, used_at = ?
             WHERE id = ?'
        );

        return $stmt->execute([
            (int) $merged['customer_id'],
            (string) $merged['token'],
            (string) $merged['type'],
            (string) $merged['expires_at'],
            $merged['created_at'],
            $merged['used_at'],
            $id,
        ]);
    }

    public function deleteToken(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM ms_customer_token WHERE id = ?');

        return $stmt->execute([$id]);
    }

    public function countTokens(?int $customerId = null, ?string $type = null): int
    {
        $criteria = [];
        if ($customerId !== null) {
            $criteria['customer_id'] = $customerId;
        }
        if ($type !== null) {
            $criteria['type'] = $type;
        }

        return count($this->findTokens($criteria));
    }

    private function ensureSchema(): void
    {
        $this->pdo->query('DROP TABLE IF EXISTS ms_customer_token');
        $this->pdo->query('DROP TABLE IF EXISTS ms_customer');

        if ($this->sqlite) {
            $this->pdo->query(
                'CREATE TABLE ms_customer (
                    id INTEGER PRIMARY KEY,
                    email TEXT NOT NULL DEFAULT \'\',
                    password TEXT NOT NULL DEFAULT \'\',
                    is_active INTEGER NOT NULL DEFAULT 1,
                    is_blocked INTEGER NOT NULL DEFAULT 0,
                    failed_login_attempts INTEGER NOT NULL DEFAULT 0,
                    blocked_until TEXT NULL,
                    last_login_at TEXT NULL
                )'
            );
            $this->pdo->query(
                'CREATE TABLE ms_customer_token (
                    id INTEGER PRIMARY KEY,
                    customer_id INTEGER NOT NULL DEFAULT 0,
                    token TEXT NOT NULL,
                    type TEXT NOT NULL,
                    expires_at TEXT NOT NULL,
                    created_at TEXT NULL,
                    used_at TEXT NULL
                )'
            );

            return;
        }

        $this->pdo->query(
            'CREATE TABLE ms_customer (
                id INT NOT NULL PRIMARY KEY,
                email VARCHAR(191) NOT NULL DEFAULT \'\',
                password VARCHAR(255) NOT NULL DEFAULT \'\',
                is_active TINYINT NOT NULL DEFAULT 1,
                is_blocked TINYINT NOT NULL DEFAULT 0,
                failed_login_attempts INT NOT NULL DEFAULT 0,
                blocked_until DATETIME NULL,
                last_login_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->pdo->query(
            'CREATE TABLE ms_customer_token (
                id INT NOT NULL PRIMARY KEY,
                customer_id INT NOT NULL DEFAULT 0,
                token VARCHAR(128) NOT NULL,
                type VARCHAR(32) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NULL,
                used_at DATETIME NULL,
                UNIQUE KEY uniq_token_type (token, type),
                KEY idx_customer_type (customer_id, type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decodeCustomer(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'password' => (string) ($row['password'] ?? ''),
            'is_active' => (int) $row['is_active'],
            'is_blocked' => (int) $row['is_blocked'],
            'failed_login_attempts' => (int) $row['failed_login_attempts'],
            'blocked_until' => $row['blocked_until'],
            'last_login_at' => $row['last_login_at'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decodeToken(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'customer_id' => (int) $row['customer_id'],
            'token' => (string) $row['token'],
            'type' => (string) $row['type'],
            'expires_at' => (string) $row['expires_at'],
            'created_at' => $row['created_at'],
            'used_at' => $row['used_at'],
        ];
    }
}

final class StoredMsCustomer extends msCustomer
{
    public $id;

    /** @var array<string, mixed> */
    private array $fields = [];

    private ?CustomerAuthPdoStore $store = null;

    public function bindStore(CustomerAuthPdoStore $store): void
    {
        $this->store = $store;
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function hydrate(array $fields): void
    {
        $this->fields = $fields;
        $this->id = (int) ($fields['id'] ?? 0);
    }

    public function get($k, $format = null, $formatTemplate = null)
    {
        return $this->fields[$k] ?? null;
    }

    public function set($key, $value)
    {
        $this->fields[$key] = $value;
        if ($key === 'id') {
            $this->id = (int) $value;
        }

        return true;
    }

    public function save($cacheFlag = null)
    {
        if ($this->store === null) {
            return false;
        }
        $id = (int) ($this->fields['id'] ?? 0);
        if ($id > 0 && $this->store->findCustomerById($id)) {
            return $this->store->updateCustomer($id, $this->fields);
        }
        $id = $this->store->insertCustomer($this->fields);
        $this->fields['id'] = $id;
        $this->id = $id;

        return true;
    }
}

final class StoredMsCustomerToken extends msCustomerToken
{
    /** @var array<string, mixed> */
    private array $fields = [];

    private ?CustomerAuthPdoStore $store = null;

    private bool $persisted = false;

    public function bindStore(CustomerAuthPdoStore $store): void
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

    public function get($k, $format = null, $formatTemplate = null)
    {
        return $this->fields[$k] ?? null;
    }

    public function set($key, $value)
    {
        $this->fields[$key] = $value;

        return true;
    }

    public function isExpired()
    {
        return ApiTokenExpiry::isExpiresAtBefore((string) $this->get('expires_at'), time());
    }

    public function getOne($alias, $criteria = null, $cacheFlag = true)
    {
        if ($alias !== 'Customer' || $this->store === null) {
            return null;
        }
        $row = $this->store->findCustomerById((int) $this->get('customer_id'));
        if ($row === null) {
            return null;
        }
        $customer = new StoredMsCustomer();
        $customer->bindStore($this->store);
        $customer->hydrate($row);

        return $customer;
    }

    public function save($cacheFlag = null)
    {
        if ($this->store === null) {
            return false;
        }
        if ($this->persisted && isset($this->fields['id'])) {
            return $this->store->updateToken((int) $this->fields['id'], $this->fields);
        }
        $id = $this->store->insertToken($this->fields);
        $this->fields['id'] = $id;
        $this->persisted = true;

        return true;
    }

    public function remove(array $ancestors = [])
    {
        if ($this->store === null || !isset($this->fields['id'])) {
            return false;
        }
        $ok = $this->store->deleteToken((int) $this->fields['id']);
        $this->persisted = false;

        return $ok;
    }
}
