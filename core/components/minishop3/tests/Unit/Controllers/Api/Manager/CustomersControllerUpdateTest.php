<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Controllers\Api\Manager;

use MiniShop3\Controllers\Api\Manager\CustomersController;
use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Customer\AuthManager;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class CustomersControllerUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 4) . '/stubs/ModxStub.php';
        }
    }

    public function testUpdateRevokesApiTokensWhenCustomerBlocked(): void
    {
        $customer = new FakeUpdateCustomer([
            'id' => 5,
            'first_name' => 'Ada',
            'is_active' => 1,
            'is_blocked' => 1,
            'blocked_until' => null,
        ]);
        $authManager = $this->createMock(AuthManager::class);
        $authManager->expects(self::once())
            ->method('revokeTokens')
            ->with(self::identicalTo($customer))
            ->willReturn(2);

        $data = (new CustomersController($this->modx($customer, $authManager)))->update([
            'id' => 5,
            'is_blocked' => 1,
        ]);

        self::assertTrue($data['success'] ?? false);
    }

    public function testUpdateRevokesApiTokensWhenCustomerDeactivated(): void
    {
        $customer = new FakeUpdateCustomer([
            'id' => 6,
            'first_name' => 'Bob',
            'is_active' => 0,
            'is_blocked' => 0,
        ]);
        $authManager = $this->createMock(AuthManager::class);
        $authManager->expects(self::once())
            ->method('revokeTokens')
            ->with(self::identicalTo($customer))
            ->willReturn(1);

        $data = (new CustomersController($this->modx($customer, $authManager)))->update([
            'id' => 6,
            'is_active' => 0,
        ]);

        self::assertTrue($data['success'] ?? false);
        self::assertNull($customer->get('blocked_until'));
    }

    public function testUpdateNewBlockClearsLeftoverLockoutUntilAndRevokes(): void
    {
        $until = date('Y-m-d H:i:s', time() + 900);
        $customer = new FakeUpdateCustomer([
            'id' => 9,
            'first_name' => 'Eve',
            'is_active' => 1,
            'is_blocked' => 0,
            'blocked_until' => $until,
            'failed_login_attempts' => 9,
        ]);
        $authManager = $this->createMock(AuthManager::class);
        $authManager->expects(self::once())
            ->method('revokeTokens')
            ->with(self::identicalTo($customer))
            ->willReturn(1);

        $data = (new CustomersController($this->modx($customer, $authManager)))->update([
            'id' => 9,
            'is_blocked' => 1,
        ]);

        self::assertTrue($data['success'] ?? false);
        self::assertNull($customer->get('blocked_until'));
    }

    public function testUpdateKeepsLockoutUntilWhenAlreadyBlocked(): void
    {
        $until = date('Y-m-d H:i:s', time() + 900);
        $customer = new FakeUpdateCustomer([
            'id' => 10,
            'first_name' => 'Fay',
            'is_active' => 1,
            'is_blocked' => 1,
            'blocked_until' => $until,
            'failed_login_attempts' => 4,
        ]);
        $authManager = $this->createMock(AuthManager::class);
        $authManager->expects(self::once())
            ->method('revokeTokens')
            ->with(self::identicalTo($customer))
            ->willReturn(1);

        $data = (new CustomersController($this->modx($customer, $authManager)))->update([
            'id' => 10,
            'is_blocked' => 1,
            'phone' => '+100',
        ]);

        self::assertTrue($data['success'] ?? false);
        self::assertSame($until, $customer->get('blocked_until'));
        self::assertSame(4, $customer->get('failed_login_attempts'));
        self::assertSame('+100', $customer->get('phone'));
    }

    public function testUpdateUnblockClearsUntilWithoutRevoke(): void
    {
        $customer = new FakeUpdateCustomer([
            'id' => 7,
            'first_name' => 'Cal',
            'is_active' => 1,
            'is_blocked' => 1,
            'blocked_until' => date('Y-m-d H:i:s', time() + 900),
            'failed_login_attempts' => 9,
        ]);
        $authManager = $this->createMock(AuthManager::class);
        $authManager->expects(self::never())->method('revokeTokens');

        $data = (new CustomersController($this->modx($customer, $authManager)))->update([
            'id' => 7,
            'is_blocked' => 0,
        ]);

        self::assertTrue($data['success'] ?? false);
        self::assertNull($customer->get('blocked_until'));
        self::assertSame(0, $customer->get('failed_login_attempts'));
    }

    public function testUpdateDoesNotRevokeWhenOnlyProfileFieldsChange(): void
    {
        $customer = new FakeUpdateCustomer([
            'id' => 8,
            'first_name' => 'Dan',
            'is_active' => 1,
            'is_blocked' => 0,
        ]);
        $authManager = $this->createMock(AuthManager::class);
        $authManager->expects(self::never())->method('revokeTokens');

        $data = (new CustomersController($this->modx($customer, $authManager)))->update([
            'id' => 8,
            'first_name' => 'Daniel',
        ]);

        self::assertTrue($data['success'] ?? false);
        self::assertSame('Daniel', $customer->get('first_name'));
    }

    private function modx(FakeUpdateCustomer $customer, AuthManager $authManager): modX
    {
        return new class ($customer, $authManager) extends modX {
            public function __construct(
                private FakeUpdateCustomer $customer,
                private AuthManager $authManager,
            ) {
                parent::__construct();
                $this->services = new class ($this->authManager) {
                    public function __construct(private AuthManager $authManager)
                    {
                    }

                    public function has(string $key): bool
                    {
                        return $key === 'ms3_auth_manager';
                    }

                    public function get(string $key): mixed
                    {
                        return $key === 'ms3_auth_manager' ? $this->authManager : null;
                    }
                };
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                if ($className !== msCustomer::class) {
                    return null;
                }
                $id = is_array($criteria) ? (int) ($criteria['id'] ?? 0) : (int) $criteria;

                return $id === (int) $this->customer->get('id') ? $this->customer : null;
            }
        };
    }
}

final class FakeUpdateCustomer extends msCustomer
{
    /** @param array<string, mixed> $fields */
    public function __construct(private array $fields)
    {
    }

    public function get($key)
    {
        return $this->fields[$key] ?? null;
    }

    public function set($key, $value, $vType = '')
    {
        $this->fields[$key] = $value;

        return $this;
    }

    public function save($cacheFlag = null)
    {
        return true;
    }
}
