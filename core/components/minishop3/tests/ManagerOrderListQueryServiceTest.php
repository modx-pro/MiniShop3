<?php

/**
 * ManagerOrderListQueryService: conditional Address JOIN (#353).
 *
 * Run: php tests/ManagerOrderListQueryServiceTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\Order\ManagerOrderListQueryService;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $label) use ($fail): void {
    if ($actual !== $expected) {
        $fail($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$minimalGrid = [
    ['name' => 'id', 'visible' => true],
    ['name' => 'num', 'visible' => true],
    ['name' => 'cost', 'visible' => true],
];

$gridWithCustomer = [
    ...$minimalGrid,
    ['name' => 'customer', 'visible' => true],
];

$gridHiddenCustomer = [
    ...$minimalGrid,
    ['name' => 'customer', 'visible' => false],
];

$assertSame(
    false,
    ManagerOrderListQueryService::needsAddressJoin([], '', $minimalGrid, 'id'),
    'minimal grid without filters'
);

$assertSame(
    true,
    ManagerOrderListQueryService::needsAddressJoin([], '', $gridWithCustomer, 'id'),
    'visible customer column'
);

$assertSame(
    false,
    ManagerOrderListQueryService::needsAddressJoin([], '', $gridHiddenCustomer, 'id'),
    'hidden customer column'
);

$assertSame(
    true,
    ManagerOrderListQueryService::needsAddressJoin(['filter_email' => 'a@b.c'], '', $minimalGrid, 'id'),
    'address filter'
);

$assertSame(
    true,
    ManagerOrderListQueryService::needsAddressJoin([], 'search', $minimalGrid, 'id'),
    'non-empty query search'
);

$assertSame(
    true,
    ManagerOrderListQueryService::needsAddressJoin([], '', $minimalGrid, 'email'),
    'sort by address field'
);

$assertSame(false, ManagerOrderListQueryService::shouldIncludeStats([]), 'include_stats default off');
$assertSame(false, ManagerOrderListQueryService::shouldIncludeStats(['include_stats' => '0']), 'include_stats 0');
$assertSame(true, ManagerOrderListQueryService::shouldIncludeStats(['include_stats' => '1']), 'include_stats 1');

fwrite(STDOUT, "OK ManagerOrderListQueryServiceTest\n");
exit(0);
