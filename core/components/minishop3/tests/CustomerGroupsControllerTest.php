<?php

/**
 * Smoke checks for CustomerGroupsController validation (#669 / #677 review).
 *
 * Run: php tests/CustomerGroupsControllerTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/stubs/ModxStub.php';

use MiniShop3\Controllers\Api\Manager\CustomerGroupsController;
use MiniShop3\Model\msCustomerGroup;
use MiniShop3\Router\HttpStatus;
use MODX\Revolution\modUserGroup;
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

$assertFalse = static function (bool $actual, string $case) use ($fail): void {
    if ($actual) {
        $fail($case . ': expected false');
    }
};

$makeModx = static function (bool $userGroupExists = false): modX {
    return new class ($userGroupExists) extends modX {
        public function __construct(private bool $userGroupExists)
        {
            $this->lexicon = new class {
                public function load(string $topic): void
                {
                }

                public function __invoke(string $key): string
                {
                    return $key;
                }
            };
        }

        public function lexicon(string $key, array $params = []): string
        {
            return $key;
        }

        public function getCount($className, $criteria = null)
        {
            if ($className === modUserGroup::class) {
                return $this->userGroupExists ? 1 : 0;
            }
            if ($className === msCustomerGroup::class) {
                return 0;
            }

            return 0;
        }

        public function getObject($className, $criteria = null, $cacheFlag = true)
        {
            return null;
        }

        public function newQuery($className, $criteria = null, $cacheFlag = true)
        {
            return new class {
                public function sortby($s, $d = 'ASC')
                {
                    return $this;
                }

                public function limit($l, $o = 0)
                {
                    return $this;
                }
            };
        }

        public function getIterator($className, $criteria = null, $cacheFlag = true)
        {
            return new \ArrayIterator([]);
        }
    };
};

$controller = new CustomerGroupsController($makeModx());

$missingId = $controller->get(['id' => 0]);
$assertSame(false, $missingId['success'] ?? null, 'get missing id success');
$assertSame(HttpStatus::BAD_REQUEST, $missingId['code'] ?? null, 'get missing id code');

$notFound = $controller->get(['id' => 42]);
$assertSame(false, $notFound['success'] ?? null, 'get unknown id success');
$assertSame(HttpStatus::NOT_FOUND, $notFound['code'] ?? null, 'get unknown id code');

$emptyName = $controller->create(['name' => '  ', 'user_group_id' => 1]);
$assertSame(false, $emptyName['success'] ?? null, 'create empty name success');
$assertSame(HttpStatus::BAD_REQUEST, $emptyName['code'] ?? null, 'create empty name code');

$badUserGroup = $controller->create(['name' => 'VIP', 'user_group_id' => 99]);
$assertSame(false, $badUserGroup['success'] ?? null, 'create invalid user group success');
$assertSame(HttpStatus::BAD_REQUEST, $badUserGroup['code'] ?? null, 'create invalid user group code');

$deleteMissing = $controller->delete(['id' => 0]);
$assertSame(false, $deleteMissing['success'] ?? null, 'delete missing id success');
$assertSame(HttpStatus::BAD_REQUEST, $deleteMissing['code'] ?? null, 'delete missing id code');

$list = $controller->getList(['start' => 0, 'limit' => 10]);
$assertSame(true, $list['success'] ?? null, 'empty list success');
$assertSame([], $list['data']['results'] ?? null, 'empty list results');
$assertSame(0, $list['data']['total'] ?? null, 'empty list total');

fwrite(STDOUT, "OK CustomerGroupsControllerTest\n");
exit(0);
