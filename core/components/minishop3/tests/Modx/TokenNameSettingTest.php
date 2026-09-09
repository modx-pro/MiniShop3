<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx;

use MiniShop3\MiniShop3;
use MiniShop3\Tests\Modx\Support\ExtraTestCase;

final class TokenNameSettingTest extends ExtraTestCase
{
    public function testSetSettingWritesDatabaseAndMemory(): void
    {
        $this->setSetting('ms3_token_name', 'ms3_test_token');

        $this->assertSettingEquals('ms3_token_name', 'ms3_test_token');
        self::assertSame('ms3_test_token', $this->modx->getOption('ms3_token_name'));
    }

    public function testMs3ServiceAndRegistryAreRegistered(): void
    {
        self::assertTrue($this->modx->services->has('ms3'));
        self::assertInstanceOf(MiniShop3::class, $this->modx->services->get('ms3'));
        self::assertTrue($this->modx->services->has('ms3_order_status'));
    }

    public function testPhinxConfigUsesLiveModxDatabase(): void
    {
        $modx = $this->modx;
        $config = require $this->extraCorePath() . 'phinx.php';

        self::assertSame(
            $modx->getOption('dbname'),
            $config['environments']['production']['name'] ?? null,
        );
    }
}
