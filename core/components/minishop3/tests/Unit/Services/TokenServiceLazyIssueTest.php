<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services;

use MiniShop3\Model\msCustomerToken;
use MiniShop3\Services\TokenService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';

/**
 * Regression #1000: anonymous page views must not write DB rows.
 *
 * Snippets call resolveOrCreateToken() (now lazy: session + cookie only).
 * The row is persisted only on the first protected API action
 * (TokenMiddleware / generateCustomerToken via ensureTokenPersisted()).
 */
final class TokenServiceLazyIssueTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_COOKIE = [];
    }

    public function testResolveOrCreateTokenDoesNotWriteDbRow(): void
    {
        $modx = $this->makeModx(new LazyTokenRowStore());

        $token = (new TokenService($modx))->resolveOrCreateToken();

        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $token);
        self::assertSame($token, $_SESSION['ms3']['customer_token']);
        self::assertSame(0, $modx->store->count);
    }

    public function testSecondSnippetCallReusesLazyToken(): void
    {
        $modx = $this->makeModx(new LazyTokenRowStore());
        $service = new TokenService($modx);

        $first = $service->resolveOrCreateToken();
        $second = $service->resolveOrCreateToken();

        self::assertSame($first, $second);
        self::assertSame(0, $modx->store->count);
    }

    public function testEnsureTokenPersistedWritesTheLazyToken(): void
    {
        $modx = $this->makeModx(new LazyTokenRowStore());
        $service = new TokenService($modx);

        $lazy = $service->getOrCreateSessionToken();
        $persisted = $service->ensureTokenPersisted($lazy, 0);

        self::assertNotNull($persisted);
        self::assertSame($lazy, $persisted->get('token'));
        self::assertSame(1, $modx->store->count);
    }

    public function testGenerateCustomerTokenPersistsLazyTokenInsteadOfMintingNew(): void
    {
        $modx = $this->makeModx(new LazyTokenRowStore());
        $service = new TokenService($modx);

        $lazy = $service->getOrCreateSessionToken();
        $result = $service->generateCustomerToken();

        self::assertSame($lazy, $result['token']);
        self::assertSame(1, $modx->store->count);
    }

    private function makeModx(LazyTokenRowStore $store): modX
    {
        return new class ($store) extends modX {
            /** @var LazyTokenRowStore */
            public LazyTokenRowStore $store;

            public function __construct(LazyTokenRowStore $storeParam)
            {
                $this->store = $storeParam;
                parent::__construct();
            }

            public function getOption(string $key, $options = null, $default = null)
            {
                return match ($key) {
                    'ms3_customer_token_ttl' => 86400,
                    default => $default,
                };
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                if ($className === msCustomerToken::class && is_array($criteria)) {
                    return $this->store->find((string) ($criteria['token'] ?? ''));
                }

                return null;
            }

            public function newObject($className = '', $attributes = [])
            {
                return new LazyTokenRow($this->store);
            }

            public function log($level, $message): void
            {
            }
        };
    }
}

final class LazyTokenRowStore
{
    /** @var array<string, LazyTokenRow> */
    private array $rows = [];

    public int $count = 0;

    public function find(string $token): ?LazyTokenRow
    {
        return $this->rows[$token] ?? null;
    }

    public function add(string $token, LazyTokenRow $row): void
    {
        $this->rows[$token] = $row;
        $this->count++;
    }
}

final class LazyTokenRow extends msCustomerToken
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly LazyTokenRowStore $store)
    {
    }

    public function set($key, $value, $vType = '')
    {
        $this->data[$key] = $value;

        return $this;
    }

    public function get($key, $format = '', $strict = true)
    {
        return $this->data[$key] ?? null;
    }

    public function save($cacheFlag = null)
    {
        $this->store->add((string) ($this->data['token'] ?? ''), $this);

        return true;
    }

    public function isExpired(): bool
    {
        return false;
    }
}
