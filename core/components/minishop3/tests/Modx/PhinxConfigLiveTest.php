<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx;

use MiniShop3\Tests\Modx\Support\ExtraTestCase;

final class PhinxConfigLiveTest extends ExtraTestCase
{
    public function testPhinxConfigUsesLiveModxDatabase(): void
    {
        // phinx.php reuses $modx from the require scope when set (ExtraTestCase kernel).
        $modx = $this->modx;
        $config = require $this->extraCorePath() . 'phinx.php';

        self::assertSame(
            $modx->getOption('dbname'),
            $config['environments']['production']['name'] ?? null,
        );
    }
}
