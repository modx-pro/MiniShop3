<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Services\ExtraFieldsService;
use MiniShop3\Services\Grid\GridColumnRules;
use MiniShop3\Services\MigrationGenerator;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Field-key and host-class rules for Extra Fields (#645).
 */
final class ExtraFieldsValidationTest extends TestCase
{
    private bool $savedCalled = false;

    protected function setUp(): void
    {
        $this->savedCalled = false;
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

    public function testCreateFieldRejectsUnsupportedClassBeforeSave(): void
    {
        $service = new ExtraFieldsService($this->createModxStub());
        $result = $service->createField([
            'class' => msProduct::class,
            'key' => 'warranty_months',
            'dbtype' => 'varchar',
            'phptype' => 'string',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('class', $result['field'] ?? null);
        $this->assertSame('ms3_err_extra_field_class_unsupported', $result['message'] ?? null);
        $this->assertFalse($this->savedCalled);
    }

    public function testCreateFieldRejectsCyrillicKeyBeforeSave(): void
    {
        $service = new ExtraFieldsService($this->createModxStub());
        $result = $service->createField([
            'class' => msProductData::class,
            'key' => 'гарантия',
            'dbtype' => 'varchar',
            'phptype' => 'string',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('key', $result['field'] ?? null);
        $this->assertSame('ms3_err_extra_field_key_invalid', $result['message'] ?? null);
        $this->assertFalse($this->savedCalled);
    }

    public function testCreateFieldRejectsCategoryStiClassBeforeSave(): void
    {
        $service = new ExtraFieldsService($this->createModxStub());
        $result = $service->createField([
            'class' => msCategory::class,
            'key' => 'warranty_months',
            'dbtype' => 'varchar',
            'phptype' => 'string',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('class', $result['field'] ?? null);
        $this->assertFalse($this->savedCalled);
    }

    private function createModxStub(): modX
    {
        $test = $this;

        return new class ($test) extends modX {
            public function __construct(private ExtraFieldsValidationTest $test)
            {
                parent::__construct();
            }

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

            public function newObject($className, $fields = [])
            {
                $this->test->markSavedCalled();

                return new class {
                    public function fromArray($data): void
                    {
                    }

                    public function save(): bool
                    {
                        return true;
                    }

                    public function remove(): bool
                    {
                        return true;
                    }

                    public function get($key)
                    {
                        return null;
                    }

                    public function toArray(): array
                    {
                        return [];
                    }
                };
            }

            public function getObject($className, $criteria = null)
            {
                return null;
            }
        };
    }

    public function markSavedCalled(): void
    {
        $this->savedCalled = true;
    }
}
