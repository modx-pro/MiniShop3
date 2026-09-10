<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx;

use MiniShop3\Tests\Modx\Support\ExtraTestCase;
use MiniShop3\Tests\Modx\Support\PackageModels;

final class SchemaTablesTest extends ExtraTestCase
{
    public function testEveryRegisteredModelHasAMysqlTable(): void
    {
        foreach (PackageModels::tables() as $class) {
            $this->assertTableExists($class);
        }
    }
}
