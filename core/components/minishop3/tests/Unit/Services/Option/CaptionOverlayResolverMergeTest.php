<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Option;

use MiniShop3\Services\Option\CaptionOverlayResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use xPDO\xPDO;

final class CaptionOverlayResolverMergeTest extends TestCase
{
    private CaptionOverlayResolver $resolver;

    protected function setUp(): void
    {
        $xpdo = $this->createStub(xPDO::class);
        $this->resolver = new CaptionOverlayResolver($xpdo);
    }

    #[DataProvider('mergeCases')]
    public function testMergeCaptionDescription(string $expected, ?string $override, string $global): void
    {
        self::assertSame($expected, $this->resolver->mergeCaptionDescription($override, $global));
    }

    /**
     * @return iterable<string, array{0: string, 1: ?string, 2: string}>
     */
    public static function mergeCases(): iterable
    {
        yield 'non-empty override wins' => ['Custom', 'Custom', 'Global'];
        yield 'null inherits global' => ['Global', null, 'Global'];
        yield 'whitespace-only inherits global' => ['Global', '   ', 'Global'];
        yield 'trimmed override wins' => ['Custom', '  Custom  ', 'Global'];
        yield 'empty string inherits global' => ['Global', '', 'Global'];
    }

    public function testPickWinningCategoryOptionLinkPrefersParentCategory(): void
    {
        $winner = $this->resolver->pickWinningCategoryOptionLink([
            ['category_id' => 20, 'position' => 1, 'caption' => 'Other'],
            ['category_id' => 10, 'position' => 99, 'caption' => 'Parent wins'],
        ], 10);

        self::assertSame(10, (int)$winner['category_id']);
        self::assertSame('Parent wins', $winner['caption']);
    }

    public function testPickWinningCategoryOptionLinkUsesPositionThenCategoryId(): void
    {
        $winner = $this->resolver->pickWinningCategoryOptionLink([
            ['category_id' => 30, 'position' => 5, 'caption' => 'Higher position'],
            ['category_id' => 20, 'position' => 2, 'caption' => 'Lower position wins'],
        ], 0);

        self::assertSame(20, (int)$winner['category_id']);
        self::assertSame('Lower position wins', $winner['caption']);
    }

    public function testPickWinningCategoryOptionLinkReturnsNullForEmptyInput(): void
    {
        self::assertNull($this->resolver->pickWinningCategoryOptionLink([], 10));
    }
}
