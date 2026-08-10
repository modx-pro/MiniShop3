<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\Mysql;

use MiniShop3\Services\Option\OptionSyncService;
use MiniShop3\Tests\Support\MysqlTestConnection;
use MiniShop3\Tests\Support\ProductOptionPdoXpdo;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * OptionSyncService against MySQL (CI service).
 */
#[Group('mysql')]
final class OptionSyncMysqlTest extends TestCase
{
    private ProductOptionPdoXpdo $xpdo;

    private OptionSyncService $sync;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/support/xpdo_stub.php';
        require_once dirname(__DIR__, 2) . '/support/MysqlTestConnection.php';
        require_once dirname(__DIR__, 2) . '/support/ProductOptionPdoXpdo.php';

        $pdo = MysqlTestConnection::requireOrSkip($this);
        $this->xpdo = new ProductOptionPdoXpdo($pdo);
        $this->xpdo->reset();
        $this->sync = new OptionSyncService($this->xpdo);
    }

    public function testSaveAndRemoveOtherOnMysql(): void
    {
        self::assertTrue($this->sync->saveProductOptions(10, [
            'color' => 'Red',
            'tags' => ['a', 'b'],
        ]));
        $loaded = $this->sync->getForProduct(10);
        ksort($loaded);
        self::assertSame(
            [
                'color' => ['Red'],
                'tags' => ['a', 'b'],
            ],
            $loaded
        );

        $this->sync->saveProductOptions(10, ['color' => 'Blue'], true);
        self::assertSame(['color' => ['Blue']], $this->sync->getForProduct(10));
    }
}
