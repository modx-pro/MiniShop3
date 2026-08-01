<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\Customer;

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

    /**
     * @param array<string, FakeCustomerToken> $tokens
     */
    private function modxWithTokens(array $tokens): modX
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }

        return new class ($tokens) extends modX {
            /** @param array<string, FakeCustomerToken> $tokens */
            public function __construct(private array $tokens)
            {
                parent::__construct();
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
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
