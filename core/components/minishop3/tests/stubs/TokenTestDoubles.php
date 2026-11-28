<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Stubs;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerToken;
use MiniShop3\Services\TokenService;
use MiniShop3\Utils\ApiTokenExpiry;
use MODX\Revolution\modX;

if (!class_exists(modX::class, false)) {
    require_once __DIR__ . '/ModxStub.php';
}

/**
 * In-memory ms3_customer_tokens replacement with #927 hash identity.
 *
 * Rows are keyed by token_hash; the legacy plaintext column is indexed
 * separately so the hash-first / plaintext-fallback lookup can be exercised.
 */
final class TokenRowStore
{
    /** @var array<string, TokenRow> */
    private array $byHash = [];

    /** @var array<string, TokenRow> */
    private array $byPlain = [];

    public int $count = 0;

    /** @var list<string> */
    public array $errors = [];

    public bool $throwOnDuplicate = false;

    /** Number of lookups that must pretend the table is empty (race emulation). */
    public int $hideFinds = 0;

    public function findByCriteria(array $criteria): ?TokenRow
    {
        if ($this->hideFinds > 0) {
            $this->hideFinds--;

            return null;
        }

        if (isset($criteria['token_hash'])) {
            return $this->byHash[(string) $criteria['token_hash']] ?? null;
        }
        if (isset($criteria['token'])) {
            return $this->byPlain[(string) $criteria['token']] ?? null;
        }

        return null;
    }

    public function insert(TokenRow $row): bool
    {
        $hash = (string) $row->raw('token_hash');
        if ($hash !== '' && isset($this->byHash[$hash])) {
            $message = "Duplicate entry '{$hash}' for key 'ms3_customer_tokens_idx_token_hash'";
            $this->errors[] = $message;
            if ($this->throwOnDuplicate) {
                throw new \RuntimeException($message);
            }

            return false;
        }

        $this->index($row);
        $this->count++;

        return true;
    }

    /** Add a row that already existed before the test (does not count as a write). */
    public function seed(TokenRow $row): void
    {
        $this->index($row);
    }

    public function remove(TokenRow $row): void
    {
        $hash = (string) $row->raw('token_hash');
        $plain = (string) $row->raw('token');
        if ($hash !== '') {
            unset($this->byHash[$hash]);
        }
        if ($plain !== '') {
            unset($this->byPlain[$plain]);
        }
    }

    private function index(TokenRow $row): void
    {
        $hash = (string) $row->raw('token_hash');
        $plain = (string) $row->raw('token');
        if ($hash !== '') {
            $this->byHash[$hash] = $row;
        }
        if ($plain !== '') {
            $this->byPlain[$plain] = $row;
        }
    }
}

final class TokenRow extends msCustomerToken
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @param array<string, mixed> $fields */
    public function __construct(private readonly TokenRowStore $store, array $fields = [])
    {
        $this->data = $fields;
    }

    public function set($key, $value, $vType = '')
    {
        $this->data[$key] = $value;

        return $this;
    }

    public function get($k, $format = null, $formatTemplate = null)
    {
        if ($k === 'token' && $this->getPlainToken() !== null) {
            return $this->getPlainToken();
        }

        return $this->data[$k] ?? null;
    }

    /** Stored column value, bypassing the in-memory plaintext. */
    public function raw(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function save($cacheFlag = null)
    {
        return $this->store->insert($this);
    }

    public function isExpired()
    {
        return ApiTokenExpiry::isExpiresAtBefore((string) $this->get('expires_at'), time());
    }

    public function remove(array $ancestors = [])
    {
        $this->store->remove($this);

        return true;
    }
}

final class CustomerRow extends msCustomer
{
    /** @param array<string, mixed> $fields */
    public function __construct(private array $fields)
    {
    }

    public function get($k, $format = null, $formatTemplate = null)
    {
        return $this->fields[$k] ?? null;
    }
}

/**
 * modX stub backed by an in-memory token table and customer registry.
 */
class TokenTestModx extends modX
{
    public TokenRowStore $store;

    /** @var array<int, CustomerRow> */
    public array $customers = [];

    /** @var list<string> */
    public array $logs = [];

    public function __construct(?TokenRowStore $store = null)
    {
        $this->store = $store ?? new TokenRowStore();
        parent::__construct();
    }

    public function bindTokenService(): TokenService
    {
        $service = new TokenService($this);
        $this->services = new class ($service) {
            public function __construct(private readonly TokenService $service)
            {
            }

            public function has(string $key): bool
            {
                return $key === 'ms3_token_service';
            }

            public function get(string $key): mixed
            {
                return $key === 'ms3_token_service' ? $this->service : null;
            }
        };

        return $service;
    }

    public function getOption(string $key, $options = null, $default = null)
    {
        return $key === 'ms3_customer_token_ttl' ? 86400 : $default;
    }

    public function getObject($className, $criteria = null, $cacheFlag = true)
    {
        if ($className === msCustomerToken::class && is_array($criteria)) {
            return $this->store->findByCriteria($criteria);
        }
        if ($className === msCustomer::class) {
            $id = is_numeric($criteria) ? (int) $criteria : (int) ($criteria['id'] ?? 0);

            return $this->customers[$id] ?? null;
        }

        return null;
    }

    public function newObject($className = '', $attributes = [])
    {
        return $className === msCustomerToken::class ? new TokenRow($this->store) : null;
    }

    public function log($level, $message): void
    {
        $this->logs[] = (string) $message;
    }

    /** @return list<string> */
    public function errorInfo()
    {
        return $this->store->errors;
    }
}
