<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services;

use MiniShop3\Services\ExtraFieldsService;
use PHPUnit\Framework\TestCase;

/**
 * Regression #812: list filter must match both short aliases and FQCN.
 */
final class ExtraFieldsClassFilterTest extends TestCase
{
    public function testShortAliasExpandsToFqcn(): void
    {
        self::assertSame(
            ['msOrder', 'MiniShop3\\Model\\msOrder'],
            ExtraFieldsService::classFilterVariants('msOrder')
        );
    }

    public function testFqcnAlsoKeepsShortAlias(): void
    {
        self::assertSame(
            ['MiniShop3\\Model\\msOrderAddress', 'msOrderAddress'],
            ExtraFieldsService::classFilterVariants('MiniShop3\\Model\\msOrderAddress')
        );
    }

    public function testNonModelClassIsLeftAlone(): void
    {
        self::assertSame(
            ['Some\\Other\\Class'],
            ExtraFieldsService::classFilterVariants('Some\\Other\\Class')
        );
    }
}
