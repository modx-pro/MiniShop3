<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Utils;

use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductFile;
use MiniShop3\Utils\ProductThumbnailJoin;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class ProductThumbnailJoinPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }
    }

    public function testBuildLeftJoinOnPrefersPreviewFileIdOverPosition(): void
    {
        $modx = new class extends modX {
            public function getTableName($className, $escape = true)
            {
                return match ($className) {
                    msProductFile::class => '`modx_ms3_product_files`',
                    msProductData::class => '`modx_ms3_products`',
                    default => '`' . $className . '`',
                };
            }
        };

        $sql = ProductThumbnailJoin::buildLeftJoinOn($modx, 'Thumb', 'small', 'msProduct');

        self::assertStringContainsString('preview_file_id', $sql);
        self::assertStringContainsString('CASE WHEN `main`.`id` =', $sql);
        self::assertStringContainsString('ORDER BY', $sql);
        self::assertStringContainsString('`main`.`position` ASC', $sql);
        self::assertStringContainsString('`modx_ms3_products`', $sql);
        self::assertStringContainsString('`modx_ms3_product_files`', $sql);
    }

    public function testBuildLeftJoinOnRejectsEmptyIdentifiers(): void
    {
        $modx = new modX();
        self::assertSame('1 = 0', ProductThumbnailJoin::buildLeftJoinOn($modx, '', 'small'));
    }
}
