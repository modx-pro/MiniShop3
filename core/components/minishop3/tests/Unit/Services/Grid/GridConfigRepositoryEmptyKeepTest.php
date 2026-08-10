<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Grid;

use MiniShop3\Services\Grid\GridConfigRepository;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Empty keep-list must not wipe all non-system fields (#365 Phase A review).
 */
final class GridConfigRepositoryEmptyKeepTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testEmptyKeepReturnsNoDeletionCandidates(): void
    {
        $modx = new class extends modX {
            public function getCollection($class, $criteria = null, $cacheFlag = true): never
            {
                throw new \RuntimeException('getCollection must not run for empty keep');
            }
        };

        $repo = new GridConfigRepository($modx);

        self::assertSame([], $repo->findNonSystemNotIn('orders', []));
    }
}
