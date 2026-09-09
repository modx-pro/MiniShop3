<?php

/**
 * Smoke checks for CustomerResourceGroupResolver (#669).
 *
 * Run: php tests/CustomerResourceGroupResolverTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/stubs/ModxStub.php';

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerGroup;
use MiniShop3\Services\Catalog\CustomerResourceGroupResolver;
use MODX\Revolution\modAccessResourceGroup;
use MODX\Revolution\modX;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

/**
 * @param array<int, object|null> $customers
 * @param array<int, object|null> $groups
 * @param list<int> $accessTargets
 */
$makeModx = static function (
    array $customers = [],
    array $groups = [],
    array $accessTargets = [],
): modX {
    return new class ($customers, $groups, $accessTargets) extends modX {
        /**
         * @param array<int, object|null> $customers
         * @param array<int, object|null> $groups
         * @param list<int> $accessTargets
         */
        public function __construct(
            private array $customers,
            private array $groups,
            private array $accessTargets,
        ) {
        }

        public function getObject($className, $criteria = null, $cacheFlag = true)
        {
            if ($className === msCustomer::class) {
                $id = is_array($criteria) ? (int) ($criteria['id'] ?? $criteria) : (int) $criteria;

                return $this->customers[$id] ?? null;
            }
            if ($className === msCustomerGroup::class) {
                $id = is_array($criteria) ? (int) ($criteria['id'] ?? $criteria) : (int) $criteria;

                return $this->groups[$id] ?? null;
            }

            return null;
        }

        public function newQuery($className, $criteria = null, $cacheFlag = true)
        {
            if ($className === modAccessResourceGroup::class) {
                $targets = $this->accessTargets;

                return new class ($targets) {
                    /** @param list<int> $targets */
                    public function __construct(private array $targets)
                    {
                        $this->stmt = new class ($targets) {
                            /** @param list<int> $targets */
                            public function __construct(private array $targets)
                            {
                            }

                            private int $index = 0;

                            public function execute()
                            {
                                return true;
                            }

                            public function fetch($fetchStyle = 0)
                            {
                                if ($this->index >= count($this->targets)) {
                                    return false;
                                }
                                $target = $this->targets[$this->index];
                                $this->index++;

                                return ['target' => $target];
                            }
                        };
                    }

                    /** @var object */
                    public $stmt;

                    public function where($conditions, $conjunction = 'AND', $binding = null, $condGroup = 0)
                    {
                        return $this;
                    }

                    public function select($columns = '*')
                    {
                        return $this;
                    }

                    public function prepare($bindings = null)
                    {
                        return true;
                    }
                };
            }

            return null;
        }
    };
};

$fieldObject = static function (array $fields): object {
    return new class ($fields) {
        /** @param array<string, mixed> $fields */
        public function __construct(private array $fields)
        {
        }

        public function get(string $key)
        {
            return $this->fields[$key] ?? null;
        }
    };
};

$resolver = new CustomerResourceGroupResolver($makeModx());
$assertSame([], $resolver->resolveAllowedResourceGroupIdsForCustomer(0, 'web'), 'invalid customer id');
$assertSame([], $resolver->resolveAllowedResourceGroupIdsForCustomer(-1, 'web'), 'negative customer id');
$assertSame([], $resolver->resolveAllowedResourceGroupIdsForCustomer(1, ''), 'empty context');

$resolver = new CustomerResourceGroupResolver($makeModx([
    10 => $fieldObject(['customer_group_id' => null, 'is_active' => true, 'is_blocked' => false]),
]));
$assertSame([], $resolver->resolveAllowedResourceGroupIdsForCustomer(10, 'web'), 'customer without group');

$resolver = new CustomerResourceGroupResolver($makeModx([
    11 => $fieldObject(['customer_group_id' => 2, 'is_active' => true, 'is_blocked' => false]),
], [
    2 => $fieldObject(['active' => false, 'user_group_id' => 5]),
]));
$assertSame([], $resolver->resolveAllowedResourceGroupIdsForCustomer(11, 'web'), 'inactive group fails closed');

$resolver = new CustomerResourceGroupResolver($makeModx([
    12 => $fieldObject(['customer_group_id' => 3, 'is_active' => true, 'is_blocked' => false]),
], [
    3 => $fieldObject(['active' => true, 'user_group_id' => 0]),
]));
$assertSame([], $resolver->resolveAllowedResourceGroupIdsForCustomer(12, 'web'), 'missing user_group_id');

$resolver = new CustomerResourceGroupResolver($makeModx([
    20 => $fieldObject(['customer_group_id' => 4, 'is_active' => true, 'is_blocked' => false]),
], [
    4 => $fieldObject(['active' => true, 'user_group_id' => 7]),
], [15, 22, 15]));
$assertSame([15, 22], $resolver->resolveAllowedResourceGroupIdsForCustomer(20, 'web'), 'active group resolves ACL targets');

$resolver = new CustomerResourceGroupResolver($makeModx([
    21 => $fieldObject(['customer_group_id' => 4, 'is_active' => false, 'is_blocked' => false]),
], [
    4 => $fieldObject(['active' => true, 'user_group_id' => 7]),
], [15]));
$assertSame([], $resolver->resolveAllowedResourceGroupIdsForCustomer(21, 'web'), 'inactive customer fails closed');

$resolver = new CustomerResourceGroupResolver($makeModx([
    22 => $fieldObject(['customer_group_id' => 4, 'is_active' => true, 'is_blocked' => true]),
], [
    4 => $fieldObject(['active' => true, 'user_group_id' => 7]),
], [15]));
$assertSame([], $resolver->resolveAllowedResourceGroupIdsForCustomer(22, 'web'), 'blocked customer fails closed');

$resolver = new CustomerResourceGroupResolver($makeModx([], [], [8, 3]));
$assertSame([3, 8], $resolver->resolveAllowedResourceGroupIdsForUserGroups([99, 5], 'web'), 'direct user group ids sorted unique');

// Optional token path: empty request → anonymous (no TokenService).
$assertSame([], $resolver->resolveAllowedIdsForRequest('web'), 'no token service → anonymous');

if (!str_contains(
    (string) file_get_contents(__DIR__ . '/../src/Services/Catalog/CatalogResourceGroupVisibility.php'),
    'resolveAllowedIdsForRequest',
)) {
    $fail('Visibility applyForRequest must call resolver resolveAllowedIdsForRequest');
}
if (is_file(__DIR__ . '/../src/Services/Catalog/CatalogVisitorResourceGroups.php')) {
    $fail('CatalogVisitorResourceGroups must be removed (static identity-blind cache)');
}

fwrite(STDOUT, "OK CustomerResourceGroupResolverTest\n");
exit(0);
