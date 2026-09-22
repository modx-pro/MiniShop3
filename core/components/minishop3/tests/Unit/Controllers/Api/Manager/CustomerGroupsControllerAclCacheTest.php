<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Controllers\Api\Manager;

use MiniShop3\Controllers\Api\Manager\CustomerGroupsController;
use MiniShop3\Model\msCustomerGroup;
use MiniShop3\Services\Catalog\CatalogAclCacheInvalidator;
use MiniShop3\Services\Catalog\CatalogResourceGroupVisibility;
use MiniShop3\Tests\Stubs\CatalogAclInvalidatorModxStub;
use MODX\Revolution\modUserGroup;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/stubs/ModxStub.php';
require_once dirname(__DIR__, 4) . '/stubs/CatalogAclModxStub.php';

final class CustomerGroupsControllerAclCacheTest extends TestCase
{
    public function testUpdateSchedulesAclCacheWhenUserGroupIdChanges(): void
    {
        $group = new FakeUpdateCustomerGroup([
            'id' => 2,
            'name' => 'VIP',
            'user_group_id' => 5,
            'active' => true,
        ]);
        [$modx, $invalidator] = $this->modx($group, userGroupExists: true);

        $data = (new CustomerGroupsController($modx))->update([
            'id' => 2,
            'user_group_id' => 8,
        ]);

        self::assertTrue($data['success'] ?? false);
        self::assertSame(8, $group->get('user_group_id'));
        self::assertTrue($invalidator->wasScheduledForTests());
    }

    public function testUpdateDoesNotScheduleAclCacheWhenOnlyNameChanges(): void
    {
        $group = new FakeUpdateCustomerGroup([
            'id' => 2,
            'name' => 'VIP',
            'user_group_id' => 5,
            'active' => true,
        ]);
        [$modx, $invalidator] = $this->modx($group, userGroupExists: true);

        $data = (new CustomerGroupsController($modx))->update([
            'id' => 2,
            'name' => 'VIP Plus',
        ]);

        self::assertTrue($data['success'] ?? false);
        self::assertSame('VIP Plus', $group->get('name'));
        self::assertFalse($invalidator->wasScheduledForTests());
    }

    public function testDeleteSchedulesAclCache(): void
    {
        $group = new FakeUpdateCustomerGroup([
            'id' => 2,
            'name' => 'VIP',
            'user_group_id' => 5,
            'active' => true,
        ]);
        [$modx, $invalidator] = $this->modx($group, userGroupExists: true);

        $data = (new CustomerGroupsController($modx))->delete(['id' => 2]);

        self::assertTrue($data['success'] ?? false);
        self::assertTrue($group->wasRemoved);
        self::assertTrue($invalidator->wasScheduledForTests());
    }

    /** @return array{0: modX, 1: CatalogAclCacheInvalidator} */
    private function modx(FakeUpdateCustomerGroup $group, bool $userGroupExists): array
    {
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
            [
                'access_resource_group_enabled' => true,
                CatalogResourceGroupVisibility::SETTING_KEY => true,
            ],
            ['web'],
        ));

        $modx = new class ($group, $invalidator, $userGroupExists) extends modX {
            public function __construct(
                private FakeUpdateCustomerGroup $group,
                private CatalogAclCacheInvalidator $invalidator,
                private bool $userGroupExists,
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
                $this->services = new class ($this->invalidator) {
                    public function __construct(private CatalogAclCacheInvalidator $invalidator)
                    {
                    }

                    public function has(string $key): bool
                    {
                        return $key === 'ms3_catalog_acl_cache';
                    }

                    public function get(string $key): mixed
                    {
                        return $key === 'ms3_catalog_acl_cache' ? $this->invalidator : null;
                    }
                };
            }

            public function lexicon(string $key, array $params = []): string
            {
                return $key;
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                if ($className === msCustomerGroup::class) {
                    $id = is_array($criteria) ? (int) ($criteria['id'] ?? 0) : (int) $criteria;

                    return $id === (int) $this->group->get('id') ? $this->group : null;
                }

                return null;
            }

            public function getCount($className, $criteria = null)
            {
                return $className === modUserGroup::class && $this->userGroupExists ? 1 : 0;
            }

            public function updateCollection($className, array $set, $criteria = null)
            {
                return 0;
            }
        };

        return [$modx, $invalidator];
    }
}

final class FakeUpdateCustomerGroup extends msCustomerGroup
{
    public bool $wasRemoved = false;

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

    public function remove(array $ancestors = [])
    {
        $this->wasRemoved = true;

        return true;
    }
}
