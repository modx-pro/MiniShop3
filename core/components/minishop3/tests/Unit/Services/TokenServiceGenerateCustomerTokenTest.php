<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services;

use MiniShop3\Model\msCustomerToken;
use MiniShop3\Services\TokenService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';

final class TokenServiceGenerateCustomerTokenTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_COOKIE = [];
    }

    public function testGenerateCustomerTokenMintsNewWhenSessionTokenRevokedInDb(): void
    {
        $staleToken = str_repeat('a', 64);
        $_SESSION['ms3'] = [
            'customer_token' => $staleToken,
            'customer_token_expires' => time() + 3600,
            'customer_id' => 7,
        ];

        $modx = new class extends modX {
            public int $persistCalls = 0;

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                return null;
            }

            public function newObject($className = '', $attributes = [])
            {
                return new class extends msCustomerToken {
                    /** @var array<string, mixed> */
                    private array $data;

                    public function __construct()
                    {
                        $this->data = [
                            'token' => bin2hex(random_bytes(32)),
                            'expires_at' => date('Y-m-d H:i:s', time() + 604800),
                            'customer_id' => 0,
                            'type' => msCustomerToken::TYPE_API,
                        ];
                    }

                    public function set($key, $value, $vType = '')
                    {
                        $this->data[$key] = $value;

                        return $this;
                    }

                    public function get($key)
                    {
                        return $this->data[$key] ?? null;
                    }

                    public function save($cacheFlag = null)
                    {
                        return true;
                    }
                };
            }

            public function getOption(string $key, $options = null, $default = null)
            {
                return $key === 'ms3_customer_token_ttl' ? 604800 : $default;
            }

            public function log($level, $msg, $target = '', $def = '', $file = '', $line = '', $fields = []): void
            {
            }
        };

        $service = new TokenService($modx);
        $result = $service->generateCustomerToken();

        self::assertNotSame('', $result['token']);
        self::assertNotSame($staleToken, $result['token']);
        self::assertSame($result['token'], $_SESSION['ms3']['customer_token'] ?? null);
        self::assertSame(0, $_SESSION['ms3']['customer_id'] ?? -1);
    }
}
