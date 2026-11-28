<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Utils;

use MiniShop3\Model\msExtraField;
use MiniShop3\Utils\ExtraFields;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Regression #866: NOT NULL (`null` = false) must stay in the xPDO field map.
 */
final class ExtraFieldsNullMetaTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }
    }

    public function testNotNullFlagIsKeptInFieldMeta(): void
    {
        $field = $this->createMock(msExtraField::class);
        $field->method('get')->willReturnCallback(static function (string $key) {
            return match ($key) {
                'class' => 'MiniShop3\\Model\\msProductData',
                'key' => 'required_note',
                'dbtype' => 'varchar',
                'phptype' => 'string',
                'null' => false,
                'precision' => '191',
                'default' => 'NULL',
                default => null,
            };
        });

        $info = $this->invokeGetFieldInfo($field);

        self::assertIsArray($info);
        self::assertArrayHasKey('null', $info['meta']);
        self::assertFalse($info['meta']['null']);
    }

    public function testNullableFlagIsStillTrue(): void
    {
        $field = $this->createMock(msExtraField::class);
        $field->method('get')->willReturnCallback(static function (string $key) {
            return match ($key) {
                'class' => 'MiniShop3\\Model\\msProductData',
                'key' => 'optional_note',
                'dbtype' => 'varchar',
                'phptype' => 'string',
                'null' => true,
                'precision' => '191',
                'default' => 'NULL',
                default => null,
            };
        });

        $info = $this->invokeGetFieldInfo($field);

        self::assertIsArray($info);
        self::assertTrue($info['meta']['null']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function invokeGetFieldInfo(msExtraField $field): mixed
    {
        $extra = new ExtraFields(new modX());
        $method = new ReflectionMethod(ExtraFields::class, 'getFieldInfo');

        return $method->invoke($extra, $field);
    }
}
