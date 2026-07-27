<?php

/**
 * Static checks for Manager list filter whitelists (without MODX).
 *
 * Run: php tests/ManagerListFilterPolicyTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Grid\ManagerListFilterPolicy;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertTrue = static function (bool $value, string $case) use ($fail): void {
    if (!$value) {
        $fail($case . ': expected true');
    }
};

$assertFalse = static function (bool $value, string $case) use ($fail): void {
    if ($value) {
        $fail($case . ': expected false');
    }
};

$assertFalse(
    ManagerListFilterPolicy::isAllowedFilter('token', ManagerListFilterPolicy::CUSTOMER_FILTER_MAP),
    'customer token filter blocked'
);
$assertFalse(
    ManagerListFilterPolicy::isAllowedFilter('password', ManagerListFilterPolicy::CUSTOMER_FILTER_MAP),
    'customer password filter blocked'
);
$assertTrue(
    ManagerListFilterPolicy::isAllowedFilter('email', ManagerListFilterPolicy::CUSTOMER_FILTER_MAP),
    'customer email filter allowed'
);
$assertTrue(
    ManagerListFilterPolicy::isAllowedFilter('is_active', ManagerListFilterPolicy::CUSTOMER_FILTER_MAP),
    'customer is_active filter allowed'
);
$assertTrue(
    ManagerListFilterPolicy::isAllowedFilter('active', ManagerListFilterPolicy::CUSTOMER_FILTER_MAP),
    'customer active alias allowed'
);

$assertFalse(
    ManagerListFilterPolicy::isAllowedFilter('properties', ManagerListFilterPolicy::DELIVERY_FILTER_MAP),
    'delivery properties filter blocked'
);
$assertTrue(
    ManagerListFilterPolicy::isAllowedFilter('name', ManagerListFilterPolicy::DELIVERY_FILTER_MAP),
    'delivery name filter allowed'
);

$criteria = [];
ManagerListFilterPolicy::applyCriteriaFilter(
    $criteria,
    'properties',
    'secret',
    ManagerListFilterPolicy::DELIVERY_FILTER_MAP
);
$assertSame([], $criteria, 'blocked delivery filter does not mutate criteria');

ManagerListFilterPolicy::applyCriteriaFilter(
    $criteria,
    'active',
    '1',
    ManagerListFilterPolicy::DELIVERY_FILTER_MAP
);
$assertSame(['active' => 1], $criteria, 'delivery active filter maps to int');

$criteria = [];
ManagerListFilterPolicy::applyCriteriaFilter(
    $criteria,
    'token',
    'abc',
    ManagerListFilterPolicy::PAYMENT_FILTER_MAP
);
$assertSame([], $criteria, 'blocked payment token filter ignored');

ManagerListFilterPolicy::applyCriteriaFilter(
    $criteria,
    'name',
    'cash',
    ManagerListFilterPolicy::PAYMENT_FILTER_MAP
);
$assertSame(['name:LIKE' => '%cash%'], $criteria, 'payment name filter uses LIKE');

$query = new class {
    /** @var list<array<string, mixed>> */
    public array $wheres = [];

    public function where(array $criteria): void
    {
        $this->wheres[] = $criteria;
    }
};

ManagerListFilterPolicy::applyCustomerFilter($query, 'token', 'abc');
$assertSame([], $query->wheres, 'blocked customer filter does not mutate query');

ManagerListFilterPolicy::applyCustomerFilter($query, 'customer_name', 'Ann');
$assertSame(
    [
        [
            'first_name:LIKE' => '%Ann%',
            'OR:last_name:LIKE' => '%Ann%',
        ],
    ],
    $query->wheres,
    'customer_name filter searches first and last name'
);

ManagerListFilterPolicy::applyCustomerFilter($query, 'is_active', '0');
$assertSame(
    [
        [
            'first_name:LIKE' => '%Ann%',
            'OR:last_name:LIKE' => '%Ann%',
        ],
        ['is_active' => 0],
    ],
    $query->wheres,
    'is_active filter accepts zero'
);

fwrite(STDOUT, "OK ManagerListFilterPolicyTest\n");
exit(0);
