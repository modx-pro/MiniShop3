<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Services\Grid\GridColumnRules;
use MiniShop3\Services\MigrationGenerator;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Field-key and host-class rules for Extra Fields (#645).
 * Full ExtraFieldsService needs xPDO cache constants; covered via MigrationGenerator + GridColumnRules.
 */
final class ExtraFieldsValidationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }
        // xpdo/modResource parents come from PHPUnit bootstrap (tests/support/*).
    }

    public function testGridColumnRulesRejectsCyrillicAndAcceptsAsciiKey(): void
    {
        $this->assertFalse(GridColumnRules::isValidSqlIdentifier('гарантия'));
        $this->assertFalse(GridColumnRules::isValidSqlIdentifier('warranty months'));
        $this->assertTrue(GridColumnRules::isValidSqlIdentifier('warranty_months'));
    }

    public function testMigrationGeneratorRejectsResourceStiClasses(): void
    {
        $generator = new MigrationGenerator($this->createModxStub());

        $this->assertTrue($generator->canHostExtraField(msProductData::class));
        $this->assertFalse($generator->canHostExtraField(msProduct::class));
        $this->assertFalse($generator->canHostExtraField(msCategory::class));
        $this->assertSame('ms3_products', $generator->resolveTableName(msProductData::class));
    }

    private function createModxStub(): modX
    {
        return new class extends modX {
            public function getOption($key, $options = null, $default = null)
            {
                if ($key === 'dbtype') {
                    return 'mysql';
                }

                return $default;
            }

            public function log($level, $msg, $target = '', $def = '', $file = '', $line = ''): void
            {
            }
        };
    }
}
