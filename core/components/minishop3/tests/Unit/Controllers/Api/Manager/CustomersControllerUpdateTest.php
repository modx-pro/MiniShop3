<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Controllers\Api\Manager;

use MiniShop3\Controllers\Api\Manager\CustomersController;
use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerGroup;
use MiniShop3\Services\Catalog\CatalogAclCacheInvalidator;
use MiniShop3\Services\Catalog\CatalogResourceGroupVisibility;
use MiniShop3\Services\Customer\AuthManager;
use MiniShop3\Tests\Stubs\CatalogAclInvalidatorModxStub;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/stubs/CatalogAclModxStub.php';

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

    public function testUpdateSchedulesAclCacheWhenCustomerGroupChanges(): void
    {
        $customer = new FakeUpdateCustomer([
            'id' => 11,
            'first_name' => 'Gus',
            'is_active' => 1,
            'is_blocked' => 0,
            'customer_group_id' => 1,
        ]);
        $authManager = $this->createMock(AuthManager::class);
        $authManager->expects(self::never())->method('revokeTokens');
        [$modx, $invalidator] = $this->modxWithAclInvalidator($customer, $authManager, aclEnabled: true);

        $data = (new CustomersController($modx))->update([
            'id' => 11,
            'customer_group_id' => 2,
        ]);

        self::assertTrue($data['success'] ?? false);
        self::assertSame(2, $customer->get('customer_group_id'));
        self::assertTrue($invalidator->wasScheduledForTests());
    }

    public function testUpdateDoesNotScheduleAclCacheWhenGroupUnchanged(): void
    {
        $customer = new FakeUpdateCustomer([
            'id' => 12,
            'first_name' => 'Hal',
            'is_active' => 1,
            'is_blocked' => 0,
            'customer_group_id' => 3,
        ]);
        $authManager = $this->createMock(AuthManager::class);
        $authManager->expects(self::never())->method('revokeTokens');
        [$modx, $invalidator] = $this->modxWithAclInvalidator($customer, $authManager, aclEnabled: true);

        $data = (new CustomersController($modx))->update([
            'id' => 12,
            'first_name' => 'Harry',
        ]);

        self::assertTrue($data['success'] ?? false);
        self::assertFalse($invalidator->wasScheduledForTests());
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

    /** @return array{0: modX, 1: CatalogAclCacheInvalidator} */
    private function modxWithAclInvalidator(
        FakeUpdateCustomer $customer,
        AuthManager $authManager,
        bool $aclEnabled,
    ): array {
        $invalidator = new CatalogAclCacheInvalidator(new CatalogAclInvalidatorModxStub(
            new class {
                public function refresh(array $providers = [], array &$results = []): bool
                {
                    return true;
                }
            },
            new class {
                public function has(string $key): bool
                {
                    return false;
                }
            },
            $aclEnabled
                ? [
                    'access_resource_group_enabled' => true,
                    CatalogResourceGroupVisibility::SETTING_KEY => true,
                ]
                : [CatalogResourceGroupVisibility::SETTING_KEY => false],
            ['web'],
        ));

        $modx = new class ($customer, $authManager, $invalidator) extends modX {
            public function __construct(
                private FakeUpdateCustomer $customer,
                private AuthManager $authManager,
                private CatalogAclCacheInvalidator $invalidator,
            ) {
                parent::__construct();
                $this->lexicon = new class {
                    public function load(string $topic): void
                    {
                    }

                    public function __invoke(string $key): string
                    {
                        return $key;
                    }
                };
                $this->services = new class ($this->authManager, $this->invalidator) {
                    public function __construct(
                        private AuthManager $authManager,
                        private CatalogAclCacheInvalidator $invalidator,
                    ) {
                    }

                    public function has(string $key): bool
                    {
                        return in_array($key, ['ms3_auth_manager', 'ms3_catalog_acl_cache'], true);
                    }

                    public function get(string $key): mixed
                    {
                        return match ($key) {
                            'ms3_auth_manager' => $this->authManager,
                            'ms3_catalog_acl_cache' => $this->invalidator,
                            default => null,
                        };
                    }
                };
            }

            public function lexicon(string $key, array $params = []): string
            {
                return $key;
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                if ($className === msCustomer::class) {
                    $id = is_array($criteria) ? (int) ($criteria['id'] ?? 0) : (int) $criteria;

                    return $id === (int) $this->customer->get('id') ? $this->customer : null;
                }
                if ($className === msCustomerGroup::class) {
                    $id = is_array($criteria) ? (int) ($criteria['id'] ?? 0) : (int) $criteria;
                    if ($id <= 0) {
                        return null;
                    }

                    return new class ($id) extends msCustomerGroup {
                        public function __construct(private int $id)
                        {
                        }

                        public function get($key)
                        {
                            return match ($key) {
                                'id' => $this->id,
                                'active' => 1,
                                default => null,
                            };
                        }
                    };
                }

                return null;
            }
        };

        return [$modx, $invalidator];
    }

    private function modx(FakeUpdateCustomer $customer, AuthManager $authManager): modX
    {
        return $this->modxWithAclInvalidator($customer, $authManager, aclEnabled: false)[0];
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
