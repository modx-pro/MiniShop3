<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Grid;

use MiniShop3\Services\Grid\ProductDataColumnPolicy;
use PHPUnit\Framework\TestCase;

final class ProductDataColumnPolicyTest extends TestCase
{
    /** @var array<string, array<string, mixed>> */
    private array $fieldMeta = [
        'id' => ['phptype' => 'integer'],
        'article' => ['phptype' => 'string'],
        'price' => ['phptype' => 'float'],
        'stock' => ['phptype' => 'float'],
        'image' => ['phptype' => 'string'],
        'thumb' => ['phptype' => 'string'],
        'source_id' => ['phptype' => 'integer'],
        'preview_file_id' => ['phptype' => 'integer'],
        'warranty_months' => ['phptype' => 'integer'],
        'is_featured' => ['phptype' => 'boolean'],
    ];

    public function testExtraReadableColumnsIncludesCustomFieldAndStock(): void
    {
        $gridFields = [
            ['name' => 'stock', 'type' => 'model'],
            ['name' => 'warranty_months', 'type' => 'model'],
        ];

        $columns = ProductDataColumnPolicy::extraReadableColumns($gridFields, $this->fieldMeta);

        self::assertSame(['stock' => 'float', 'warranty_months' => 'integer'], $columns);
    }

    public function testExtraReadableColumnsExcludesBuiltinReservedAndInternal(): void
    {
        $gridFields = [
            ['name' => 'article', 'type' => 'model'],
            ['name' => 'price', 'type' => 'model'],
            ['name' => 'id', 'type' => 'model'],
            ['name' => 'preview_file_id', 'type' => 'model'],
            ['name' => 'source_id', 'type' => 'model'],
        ];

        $columns = ProductDataColumnPolicy::extraReadableColumns($gridFields, $this->fieldMeta);

        self::assertSame([], $columns);
    }

    public function testExtraReadableColumnsExcludesOptionAndRelationTypes(): void
    {
        $gridFields = [
            ['name' => 'warranty_months', 'type' => 'option', 'key' => 'warranty'],
            ['name' => 'vendor_name', 'type' => 'relation'],
        ];

        $columns = ProductDataColumnPolicy::extraReadableColumns($gridFields, $this->fieldMeta);

        self::assertSame([], $columns);
    }

    public function testExtraReadableColumnsDefaultTypeIsModel(): void
    {
        $gridFields = [
            ['name' => 'is_featured'],
        ];

        $columns = ProductDataColumnPolicy::extraReadableColumns($gridFields, $this->fieldMeta);

        self::assertSame(['is_featured' => 'boolean'], $columns);
    }

    public function testExtraReadableColumnsRejectsInvalidNameAndUnknownField(): void
    {
        $gridFields = [
            ['name' => 'bad-key', 'type' => 'model'],
            ['name' => 'not_in_meta', 'type' => 'model'],
            ['name' => '', 'type' => 'model'],
        ];

        $columns = ProductDataColumnPolicy::extraReadableColumns($gridFields, $this->fieldMeta);

        self::assertSame([], $columns);
    }

    public function testCastValueFollowsPhpType(): void
    {
        self::assertNull(ProductDataColumnPolicy::castValue(null, 'float'));
        self::assertSame(7.0, ProductDataColumnPolicy::castValue('7.00', 'float'));
        self::assertSame(25, ProductDataColumnPolicy::castValue('25', 'integer'));
        self::assertTrue(ProductDataColumnPolicy::castValue('1', 'boolean'));
        self::assertSame('abc', ProductDataColumnPolicy::castValue('abc', 'string'));
        self::assertSame(['a', 'b'], ProductDataColumnPolicy::castValue('["a","b"]', 'json'));
        self::assertSame('not json', ProductDataColumnPolicy::castValue('not json', 'JSON'));
    }

    public function testWritableNamesExcludesInternalAndGridReadonly(): void
    {
        $names = ProductDataColumnPolicy::writableNames($this->fieldMeta);

        self::assertContains('stock', $names);
        self::assertContains('article', $names);
        self::assertContains('warranty_months', $names);
        self::assertNotContains('id', $names);
        self::assertNotContains('preview_file_id', $names);
        self::assertNotContains('source_id', $names);
        self::assertNotContains('image', $names);
        self::assertNotContains('thumb', $names);
    }
}
