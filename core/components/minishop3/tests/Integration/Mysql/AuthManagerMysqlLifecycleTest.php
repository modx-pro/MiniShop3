<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\Mysql;

use MiniShop3\Tests\Integration\Customer\AuthManagerLifecycleTest;
use MiniShop3\Tests\Support\CustomerAuthPdoStore;
use MiniShop3\Tests\Support\MysqlTestConnection;
use PHPUnit\Framework\Attributes\Group;

/**
 * Same AuthManager lifecycle against real MySQL (CI service / local DSN).
 */
#[Group('mysql')]
final class AuthManagerMysqlLifecycleTest extends AuthManagerLifecycleTest
{
    protected function createStore(): CustomerAuthPdoStore
    {
        require_once dirname(__DIR__, 2) . '/support/MysqlTestConnection.php';
        require_once dirname(__DIR__, 2) . '/support/CustomerAuthPdoStore.php';

        $pdo = MysqlTestConnection::requireOrSkip($this);

        return new CustomerAuthPdoStore($pdo);
    }
}
