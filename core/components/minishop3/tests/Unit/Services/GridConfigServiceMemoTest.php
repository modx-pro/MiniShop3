<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services;

require_once __DIR__ . '/../../stubs/ModxStub.php';
require_once __DIR__ . '/../../stubs/GridConfigQueryStub.php';
require_once __DIR__ . '/../../stubs/GridConfigModxStub.php';

use MiniShop3\Services\GridConfigService;
use MiniShop3\Tests\Stubs\GridConfigModxStub;
use PHPUnit\Framework\TestCase;

final class GridConfigServiceMemoTest extends TestCase
{
    public function testGetGridConfigMemoizesWithinSameInstance(): void
    {
        $modx = new GridConfigModxStub();
        $service = new GridConfigService($modx);

        $first = $service->getGridConfig('orders', true);
        $second = $service->getGridConfig('orders', true);

        self::assertSame($first, $second);
        self::assertSame(1, $modx->getCollectionCalls);
        self::assertSame([], $first);
    }

    public function testIncludeHiddenUsesSeparateCacheEntry(): void
    {
        $modx = new GridConfigModxStub();
        $service = new GridConfigService($modx);

        $service->getGridConfig('orders', false);
        $service->getGridConfig('orders', true);

        self::assertSame(2, $modx->getCollectionCalls);
    }

    public function testSaveGridConfigInvalidatesMemoForSameRequest(): void
    {
        $modx = new GridConfigModxStub();
        $service = new GridConfigService($modx);

        $service->getGridConfig('orders', true);
        $service->saveGridConfig('orders', []);
        $service->getGridConfig('orders', true);

        // 1st read + 2nd read after cache invalidation.
        // Empty save keep-list short-circuits findNonSystemNotIn (no getCollection).
        self::assertSame(2, $modx->getCollectionCalls);
    }
}
