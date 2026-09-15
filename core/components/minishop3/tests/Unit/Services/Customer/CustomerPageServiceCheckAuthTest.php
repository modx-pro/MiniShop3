<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Customer;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerToken;
use MiniShop3\Services\Customer\CustomerPageService;
use MiniShop3\Services\TokenService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class CustomerPageServiceCheckAuthTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_COOKIE = [];
    }

    public function testCheckAuthRejectsPermanentManagerBlock(): void
    {
        $customer = new FakePageCustomer([
            'id' => 10,
            'is_active' => 1,
            'is_blocked' => 1,
            'blocked_until' => null,
        ]);
        $service = $this->pageService($this->modx($customer), $customer);
        $_SESSION['ms3'] = [
            'customer_id' => 10,
            'customer_token' => 'session-token',
            'customer_token_expires' => time() + 3600,
        ];

        self::assertFalse($service->checkAuth());
        self::assertArrayNotHasKey('customer_id', $_SESSION['ms3'] ?? []);
    }

    public function testCheckAuthAllowsExpiredTemporaryLockoutWithoutMutation(): void
    {
        $customer = new FakePageCustomer([
            'id' => 11,
            'is_active' => 1,
            'is_blocked' => 1,
            'blocked_until' => date('Y-m-d H:i:s', time() - 60),
        ]);
        $service = $this->pageService($this->modx($customer), $customer);
        $_SESSION['ms3'] = [
            'customer_id' => 11,
            'customer_token' => 'session-token',
            'customer_token_expires' => time() + 3600,
        ];

        self::assertTrue($service->checkAuth());
        self::assertTrue((bool) $customer->get('is_blocked'));
        self::assertNotEmpty($customer->get('blocked_until'));
    }

    private function pageService(modX $modx, FakePageCustomer $customer): CustomerPageService
    {
        return new class ($modx) extends CustomerPageService {
            public function __construct(modX $modx)
            {
                $this->modx = $modx;
            }

            public function render(): string
            {
                return '';
            }

            public function getData(): array
            {
                return [];
            }
        };
    }

    private function modx(FakePageCustomer $customer): modX
    {
        $token = new FakePageCustomerToken([
            'token' => 'session-token',
            'type' => msCustomerToken::TYPE_API,
            'customer_id' => (int) $customer->get('id'),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        return new class ($customer, $token) extends modX {
            public function __construct(
                private FakePageCustomer $customer,
                private FakePageCustomerToken $token,
            ) {
                parent::__construct();
                $tokenService = new TokenService($this);
                $this->services = new class ($tokenService) {
                    public function __construct(private TokenService $tokenService)
                    {
                    }

                    public function has(string $key): bool
                    {
                        return $key === 'ms3_token_service';
                    }

                    public function get(string $key): mixed
                    {
                        return $key === 'ms3_token_service' ? $this->tokenService : null;
                    }
                };
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                if ($className === msCustomer::class) {
                    $id = is_numeric($criteria) ? (int) $criteria : (int) ($criteria['id'] ?? 0);

                    return $id === (int) $this->customer->get('id') ? $this->customer : null;
                }

                if ($className === msCustomerToken::class && is_array($criteria)) {
                    if (($criteria['token'] ?? '') === (string) $this->token->get('token')) {
                        return $this->token;
                    }
                }

                return null;
            }
        };
    }
}

final class FakePageCustomer extends msCustomer
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

final class FakePageCustomerToken extends msCustomerToken
{
    /** @param array<string, mixed> $fields */
    public function __construct(private array $fields)
    {
    }

    public function get($key)
    {
        return $this->fields[$key] ?? null;
    }

    public function isExpired()
    {
        return false;
    }
}
