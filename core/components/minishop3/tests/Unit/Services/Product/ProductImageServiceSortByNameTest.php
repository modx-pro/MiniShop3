<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Product;

use MiniShop3\Services\Product\ProductImageService;
use PHPUnit\Framework\TestCase;

final class ProductImageServiceSortByNameTest extends TestCase
{
    public function testBuildNaturalSortRanksOrdersNumericFilenamesNaturally(): void
    {
        $rows = [
            ['id' => 3, 'name' => '10.jpg', 'file' => '10.jpg'],
            ['id' => 1, 'name' => '2.jpg', 'file' => '2.jpg'],
            ['id' => 2, 'name' => '01.jpg', 'file' => '01.jpg'],
        ];

        $ranks = ProductImageService::buildNaturalSortRanks($rows);

        self::assertSame([2 => 0, 1 => 1, 3 => 2], $ranks);
    }

    /**
     * Upload processor stores name without extension (01.jpg → "01").
     */
    public function testBuildNaturalSortRanksOrdersExtensionlessUploadNames(): void
    {
        $rows = [
            ['id' => 3, 'name' => '10', 'file' => 'hash10.jpg'],
            ['id' => 1, 'name' => '2', 'file' => 'hash2.jpg'],
            ['id' => 2, 'name' => '01', 'file' => 'hash01.jpg'],
        ];

        $ranks = ProductImageService::buildNaturalSortRanks($rows);

        self::assertSame([2 => 0, 1 => 1, 3 => 2], $ranks);
    }

    public function testBuildNaturalSortRanksIsCaseInsensitive(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'B.jpg', 'file' => 'b.jpg'],
            ['id' => 2, 'name' => 'a.jpg', 'file' => 'a.jpg'],
        ];

        $ranks = ProductImageService::buildNaturalSortRanks($rows);

        self::assertSame([2 => 0, 1 => 1], $ranks);
    }

    /**
     * strnatcasecmp alone does not case-fold Cyrillic under locale C (#616 review).
     */
    public function testBuildNaturalSortRanksIsCaseInsensitiveForCyrillic(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Фото 1.jpg', 'file' => 'f1.jpg'],
            ['id' => 4, 'name' => 'Фото 10.jpg', 'file' => 'f10.jpg'],
            ['id' => 2, 'name' => 'фото 2.jpg', 'file' => 'f2.jpg'],
            ['id' => 3, 'name' => 'фото 3.jpg', 'file' => 'f3.jpg'],
        ];

        $ranks = ProductImageService::buildNaturalSortRanks($rows);

        self::assertSame([1 => 0, 2 => 1, 3 => 2, 4 => 3], $ranks);
    }

    public function testBuildNaturalSortRanksFallsBackToFileWhenNameEmpty(): void
    {
        $rows = [
            ['id' => 1, 'name' => '', 'file' => 'z.jpg'],
            ['id' => 2, 'name' => '', 'file' => 'a.jpg'],
        ];

        $ranks = ProductImageService::buildNaturalSortRanks($rows);

        self::assertSame([2 => 0, 1 => 1], $ranks);
    }

    public function testBuildNaturalSortRanksTieBreaksById(): void
    {
        $rows = [
            ['id' => 5, 'name' => 'same.jpg', 'file' => 'same.jpg'],
            ['id' => 2, 'name' => 'same.jpg', 'file' => 'same.jpg'],
        ];

        $ranks = ProductImageService::buildNaturalSortRanks($rows);

        self::assertSame([2 => 0, 5 => 1], $ranks);
    }

    public function testBuildNaturalSortRanksReturnsEmptyForEmptyInput(): void
    {
        self::assertSame([], ProductImageService::buildNaturalSortRanks([]));
    }
}
