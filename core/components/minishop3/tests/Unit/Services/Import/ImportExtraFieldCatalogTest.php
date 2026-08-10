<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Import;

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Services\ExtraFields\RepeaterFieldService;
use MiniShop3\Services\ExtraFieldsService;
use MiniShop3\Services\Import\ImportExtraFieldCatalog;
use MODX\Revolution\modX;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImportExtraFieldCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testListImportFieldsMergesByClassAndSkipsReservedAndMissingColumns(): void
    {
        $service = $this->fakeExtraFields([
            msProduct::class => [
                [
                    'key' => 'pagetitle',
                    'class' => msProduct::class,
                    'phptype' => 'string',
                    'label' => 'Should be skipped (reserved)',
                    'column_exists' => true,
                ],
                [
                    'key' => 'custom_flag',
                    'class' => msProduct::class,
                    'phptype' => 'boolean',
                    'label' => 'Custom Flag',
                    'column_exists' => true,
                ],
            ],
            msProductData::class => [
                [
                    'key' => 'pending_col',
                    'class' => msProductData::class,
                    'phptype' => 'string',
                    'label' => 'Pending',
                    'column_exists' => false,
                ],
                [
                    'key' => 'ext_sku',
                    'class' => msProductData::class,
                    'phptype' => 'string',
                    'label' => 'External SKU',
                    'column_exists' => true,
                ],
                [
                    'key' => 'specs',
                    'class' => msProductData::class,
                    'phptype' => 'json',
                    'xtype' => RepeaterFieldService::XTYPE,
                    'label' => 'Specs',
                    'column_exists' => true,
                ],
            ],
        ]);

        $catalog = new ImportExtraFieldCatalog(new modX(), $service, [
            'resource' => ['pagetitle' => ['label' => 'x']],
            'product_data' => ['price' => ['label' => 'y']],
            'special' => ['gallery' => ['label' => 'z']],
        ]);
        $fields = $catalog->listImportFields();

        $values = array_column($fields, 'value');
        self::assertSame(['custom_flag', 'ext_sku'], $values);
        self::assertSame('resource', $fields[0]['group']);
        self::assertSame('boolean', $fields[0]['type']);
        self::assertSame('product', $fields[1]['group']);
        self::assertSame('External SKU', $fields[1]['label']);
    }

    public function testNormalizeCellDoesNotApplyExtraSemanticsToReservedStaticKeys(): void
    {
        $catalog = new ImportExtraFieldCatalog(new modX(), $this->fakeExtraFields([
            msProduct::class => [[
                'key' => 'price',
                'class' => msProduct::class,
                'phptype' => 'integer',
                'label' => 'Conflicting Extra',
                'column_exists' => true,
            ]],
        ]), [
            'resource' => [],
            'product_data' => ['price' => ['label' => 'y']],
            'special' => [],
        ]);

        // Reserved static product_data.price must stay a plain string cell (no skip-on-empty).
        self::assertSame('', $catalog->normalizeCell('price', '', true));
        self::assertSame('12.50', $catalog->normalizeCell('price', '12.50', true));
    }

    public function testNormalizeCellSkipsEmptyExtraOnUpdate(): void
    {
        $catalog = new ImportExtraFieldCatalog(new modX(), $this->fakeExtraFields([
            msProductData::class => [[
                'key' => 'ext_sku',
                'class' => msProductData::class,
                'phptype' => 'string',
                'label' => 'External SKU',
                'column_exists' => true,
            ]],
        ]), [
            'resource' => [],
            'product_data' => [],
            'special' => [],
        ]);

        self::assertNull($catalog->normalizeCell('ext_sku', '', true));
        self::assertSame('', $catalog->normalizeCell('ext_sku', '', false));
        self::assertSame('A', $catalog->normalizeCell('ext_sku', 'A', true));
        self::assertSame('', $catalog->normalizeCell('price', '', true));
    }

    public function testNormalizeCellCastsExtraFieldValues(): void
    {
        $catalog = new ImportExtraFieldCatalog(new modX(), $this->fakeExtraFields([
            msProductData::class => [[
                'key' => 'ext_sku',
                'class' => msProductData::class,
                'phptype' => 'integer',
                'label' => 'External SKU',
                'column_exists' => true,
            ]],
        ]), [
            'resource' => [],
            'product_data' => [],
            'special' => [],
        ]);

        self::assertSame(7, $catalog->normalizeCell('ext_sku', '7', true));
        self::assertSame('pagetitle', $catalog->normalizeCell('pagetitle', 'pagetitle', true));
    }

    #[DataProvider('castCases')]
    public function testCastValue(mixed $expected, string $phptype, string $raw): void
    {
        $catalog = new ImportExtraFieldCatalog(new modX(), $this->fakeExtraFields([
            msProductData::class => [[
                'key' => 'field',
                'class' => msProductData::class,
                'phptype' => $phptype,
                'label' => 'Field',
                'column_exists' => true,
            ]],
        ]), [
            'resource' => [],
            'product_data' => [],
            'special' => [],
        ]);

        self::assertSame($expected, $catalog->castValue('field', $raw));
    }

    /**
     * @return iterable<string, array{0: mixed, 1: string, 2: string}>
     */
    public static function castCases(): iterable
    {
        yield 'integer' => [42, 'integer', '42'];
        yield 'invalid integer stays string' => ['12abc', 'integer', '12abc'];
        yield 'float' => [3.5, 'float', '3.5'];
        yield 'invalid float stays string' => ['x', 'float', 'x'];
        yield 'boolean true' => [true, 'boolean', 'yes'];
        yield 'boolean false' => [false, 'boolean', '0'];
        yield 'json array' => [['a', 'b'], 'json', '["a","b"]'];
        yield 'csv-like json fallback' => [['a', 'b'], 'array', 'a, b'];
        yield 'string passthrough' => ['hello', 'string', 'hello'];
    }

    /**
     * @param array<string, list<array<string, mixed>>> $byClass
     */
    private function fakeExtraFields(array $byClass): ExtraFieldsService
    {
        return new class ($byClass) extends ExtraFieldsService {
            /** @param array<string, list<array<string, mixed>>> $byClass */
            public function __construct(private array $byClass)
            {
            }

            public function getFields(array $criteria = []): array
            {
                $class = (string) ($criteria['class'] ?? '');

                return $this->byClass[$class] ?? [];
            }
        };
    }
}
