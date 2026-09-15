<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\Customer;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerToken;
use MiniShop3\Services\TokenService;
use MiniShop3\Utils\ApiTokenExpiry;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Level-2: TokenService::resolveApiToken runtime (expired → remove, no silent renew).
 */
final class ResolveApiTokenTest extends TestCase
{
    public function testEmptyTokenIsMissing(): void
    {
        $service = new TokenService($this->modxWithTokens([]));
        self::assertSame(
            ['token' => null, 'reason' => 'missing'],
            $service->resolveApiToken('')
        );
    }

    public function testUnknownTokenIsMissing(): void
    {
        $service = new TokenService($this->modxWithTokens([]));
        self::assertSame(
            ['token' => null, 'reason' => 'missing'],
            $service->resolveApiToken('nope')
        );
    }

    public function testValidTokenReturnsOk(): void
    {
        $token = new FakeCustomerToken([
            'token' => 'alive-token',
            'type' => msCustomerToken::TYPE_API,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);
        $service = new TokenService($this->modxWithTokens(['alive-token' => $token]));

        $resolved = $service->resolveApiToken('alive-token');
        self::assertSame('ok', $resolved['reason']);
        self::assertSame($token, $resolved['token']);
        self::assertFalse($token->removed);
    }

    public function testExpiredTokenIsRemoved(): void
    {
        $token = new FakeCustomerToken([
            'token' => 'dead-token',
            'type' => msCustomerToken::TYPE_API,
            'expires_at' => date('Y-m-d H:i:s', time() - 10),
        ]);
        $service = new TokenService($this->modxWithTokens(['dead-token' => $token]));

        $resolved = $service->resolveApiToken('dead-token');
        self::assertSame('expired', $resolved['reason']);
        self::assertNull($resolved['token']);
        self::assertTrue($token->removed);
    }

    public function testBlockedCustomerTokenIsMissing(): void
    {
        $token = new FakeCustomerToken([
            'token' => 'blocked-token',
            'type' => msCustomerToken::TYPE_API,
            'customer_id' => 42,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);
        $customer = new FakeAccessCustomer([
            'id' => 42,
            'is_active' => 1,
            'is_blocked' => 1,
            'blocked_until' => null,
        ]);
        $service = new TokenService($this->modxWithTokens(['blocked-token' => $token], [42 => $customer]));

        $resolved = $service->resolveApiToken('blocked-token');
        self::assertSame('missing', $resolved['reason']);
        self::assertNull($resolved['token']);
        self::assertFalse($token->removed);
    }

    public function testInactiveCustomerTokenIsMissing(): void
    {
        $token = new FakeCustomerToken([
            'token' => 'inactive-token',
            'type' => msCustomerToken::TYPE_API,
            'customer_id' => 43,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);
        $customer = new FakeAccessCustomer([
            'id' => 43,
            'is_active' => 0,
            'is_blocked' => 0,
        ]);
        $service = new TokenService($this->modxWithTokens(['inactive-token' => $token], [43 => $customer]));

        $resolved = $service->resolveApiToken('inactive-token');
        self::assertSame('missing', $resolved['reason']);
        self::assertNull($resolved['token']);
    }

    public function testExpiredLockoutCustomerTokenRemainsOk(): void
    {
        $token = new FakeCustomerToken([
            'token' => 'expired-lockout-token',
            'type' => msCustomerToken::TYPE_API,
            'customer_id' => 44,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);
        $customer = new FakeAccessCustomer([
            'id' => 44,
            'is_active' => 1,
            'is_blocked' => 1,
            'blocked_until' => date('Y-m-d H:i:s', time() - 60),
        ]);
        $service = new TokenService($this->modxWithTokens(['expired-lockout-token' => $token], [44 => $customer]));

        $resolved = $service->resolveApiToken('expired-lockout-token');
        self::assertSame('ok', $resolved['reason']);
        self::assertSame($token, $resolved['token']);
    }

    public function testGuestTokenSkipsCustomerAccessCheck(): void
    {
        $token = new FakeCustomerToken([
            'token' => 'guest-token',
            'type' => msCustomerToken::TYPE_API,
            'customer_id' => 0,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);
        $service = new TokenService($this->modxWithTokens(['guest-token' => $token]));

        $resolved = $service->resolveApiToken('guest-token');
        self::assertSame('ok', $resolved['reason']);
        self::assertSame($token, $resolved['token']);
    }

    public function testRotateApiTokenRejectsBlockedCustomer(): void
    {
        $token = new FakeCustomerToken([
            'token' => 'rotate-blocked',
            'type' => msCustomerToken::TYPE_API,
            'customer_id' => 45,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);
        $customer = new FakeAccessCustomer([
            'id' => 45,
            'is_active' => 1,
            'is_blocked' => 1,
            'blocked_until' => date('Y-m-d H:i:s', time() + 3600),
        ]);
        $service = new TokenService($this->modxWithTokens(['rotate-blocked' => $token], [45 => $customer]));

        self::assertNull($service->rotateApiToken('rotate-blocked'));
    }

    /**
     * @param array<string, FakeCustomerToken> $tokens
     * @param array<int, FakeAccessCustomer> $customers
     */
    private function modxWithTokens(array $tokens, array $customers = []): modX
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }

        return new class ($tokens, $customers) extends modX {
            /**
             * @param array<string, FakeCustomerToken> $tokens
             * @param array<int, FakeAccessCustomer> $customers
             */
            public function __construct(private array $tokens, private array $customers)
            {
                parent::__construct();
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                if ($className === msCustomer::class) {
                    $id = is_numeric($criteria) ? (int) $criteria : (int) ($criteria['id'] ?? 0);

                    return $this->customers[$id] ?? null;
                }

                if ($className !== msCustomerToken::class || !is_array($criteria)) {
                    return null;
                }

                $token = (string) ($criteria['token'] ?? '');
                $type = (string) ($criteria['type'] ?? '');
                $obj = $this->tokens[$token] ?? null;
                if ($obj === null || $type !== msCustomerToken::TYPE_API) {
                    return null;
                }

                return $obj;
            }
        };
    }
}

final class FakeAccessCustomer extends msCustomer
{
    /** @param array<string, mixed> $fields */
    public function __construct(private array $fields)
    {
    }

    public function get($key)
    {
        return $this->fields[$key] ?? null;
    }
}

final class FakeCustomerToken extends msCustomerToken
{
    /** @var array<string, mixed> */
    private array $fields;

    public bool $removed = false;

    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(array $fields)
    {
        $this->fields = $fields;
    }

    public function get($key)
    {
        return $this->fields[$key] ?? null;
    }

    public function isExpired()
    {
        return ApiTokenExpiry::isExpiresAtBefore((string) $this->get('expires_at'), time());
    }

    public function remove(array $ancestors = [])
    {
        $this->removed = true;

        return true;
    }
}
