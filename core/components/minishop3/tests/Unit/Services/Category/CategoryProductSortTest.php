<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Category;

use MiniShop3\Services\Category\CategoryProductSort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CategoryProductSortTest extends TestCase
{
    public function testProductColumnsAreQualified(): void
    {
        self::assertSame('`msProduct`.`longtitle`', CategoryProductSort::builtinExpression('longtitle'));
        self::assertSame('`msProduct`.`parent`', CategoryProductSort::builtinExpression('parent'));
        self::assertSame('`msProduct`.`hidemenu`', CategoryProductSort::builtinExpression('hidemenu'));
    }

    public function testDataColumnsAreQualified(): void
    {
        self::assertSame('`Data`.`article`', CategoryProductSort::builtinExpression('article'));
        self::assertSame('`Data`.`new`', CategoryProductSort::builtinExpression('new'));
        self::assertSame('`Data`.`favorite`', CategoryProductSort::builtinExpression('favorite'));
    }

    public function testNonSqlAndUnknownNamesAreRejected(): void
    {
        $hostile = 'id,(select 1)';

        self::assertNull(CategoryProductSort::builtinExpression('preview_url'));
        self::assertNull(CategoryProductSort::builtinExpression('category_name'));
        self::assertNull(CategoryProductSort::builtinExpression($hostile));
        self::assertNull(CategoryProductSort::builtinExpression('menuindex'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function builtinColumns(): array
    {
        $cases = [];
        foreach (CategoryProductSort::BUILTIN as $name => $table) {
            $alias = $table === 'product' ? 'msProduct' : 'Data';
            $cases[$name] = [$name, "`{$alias}`.`{$name}`"];
        }

        return $cases;
    }

    #[DataProvider('builtinColumns')]
    public function testEveryBuiltinColumnIsQualified(string $name, string $expected): void
    {
        $marker = 'EFFECTIVE_MENUINDEX_SENTINEL';

        self::assertSame($expected, CategoryProductSort::expression($name, [], [], [], $marker));
    }

    public function testUnknownSortEqualsTheMenuindexMarker(): void
    {
        $marker = 'EFFECTIVE_MENUINDEX_SENTINEL';
        $hostile = 'id,(select 1)';

        $sql = CategoryProductSort::expression($hostile, [], [], [$hostile => 'string'], $marker);

        self::assertSame($marker, $sql);
        self::assertStringNotContainsString($hostile, $sql);
        self::assertSame($marker, CategoryProductSort::expression('menuindex', [], [], [], $marker));
        self::assertSame($marker, CategoryProductSort::expression('preview_url', [], [], [], $marker));
    }

    public function testGridColumnsWinOverTheFallback(): void
    {
        $marker = 'EFFECTIVE_MENUINDEX_SENTINEL';

        self::assertSame(
            'GROUP_CONCAT(DISTINCT `opt_color`.value)',
            CategoryProductSort::expression('color', ['color' => 'GROUP_CONCAT(DISTINCT `opt_color`.value)'], [], [], $marker)
        );
        self::assertSame('Data.`stock`', CategoryProductSort::expression('stock', [], [], ['stock' => 'integer'], $marker));
    }
}
