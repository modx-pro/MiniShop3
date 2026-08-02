<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Product\Import;

use MiniShop3\Services\Product\Import\ImportCsvEventBridge;
use MiniShop3\Services\Product\Import\ImportCsvParamsNormalizer;
use MiniShop3\Services\Product\Import\ImportCsvReader;
use MiniShop3\Services\Product\Import\ImportCsvRowFieldMapper;
use PHPUnit\Framework\TestCase;

final class ImportCsvDecompositionTest extends TestCase
{
    public function testApplyReturnedArrayMergesAssociativeKeys(): void
    {
        $current = ['a' => 1, 'b' => 2];
        $returned = ['data' => ['b' => 9, 'c' => 3]];

        $result = ImportCsvEventBridge::applyReturnedArray($current, $returned, 'data');

        $this->assertSame(['a' => 1, 'b' => 9, 'c' => 3], $result);
    }

    public function testApplyReturnedArrayReplacesList(): void
    {
        $current = ['old'];
        $returned = ['gallery' => ['img1.jpg', 'img2.jpg']];

        $result = ImportCsvEventBridge::applyReturnedArray($current, $returned, 'gallery');

        $this->assertSame(['img1.jpg', 'img2.jpg'], $result);
    }

    public function testIsCancelledDetectsFalseAndCancel(): void
    {
        $this->assertTrue(ImportCsvEventBridge::isCancelled([true, 'cancel']));
        $this->assertTrue(ImportCsvEventBridge::isCancelled([false]));
        $this->assertFalse(ImportCsvEventBridge::isCancelled([true, 'ok']));
        $this->assertFalse(ImportCsvEventBridge::isCancelled(null));
    }

    public function testRemoveBomStripsUtf8Marker(): void
    {
        $content = "\xEF\xBB\xBFhello";
        $this->assertSame('hello', ImportCsvReader::removeBom($content));
    }

    public function testDetectEncodingRecognizesUtf8Bom(): void
    {
        $this->assertSame('UTF-8', ImportCsvReader::detectEncoding("\xEF\xBB\xBFtext"));
    }

    public function testMapOptionTvGalleryAndRemainsAlias(): void
    {
        $mapper = new ImportCsvRowFieldMapper();
        $result = $mapper->map(
            ['pagetitle', 'parent', 'gallery', 'option.color', 'tv.brand', 'remains', 'vendor'],
            ['Phone', '5', 'assets/img.jpg', 'red', 'Sony', '10', 'Acme'],
            static fn (string $name): int => $name === 'Acme' ? 42 : 0,
        );

        $this->assertSame('Phone', $result['data']['pagetitle']);
        $this->assertSame('5', $result['data']['parent']);
        $this->assertSame('10', $result['data']['stock']);
        $this->assertSame(42, $result['data']['vendor_id']);
        $this->assertSame(['assets/img.jpg'], $result['gallery']);
        $this->assertSame(['color' => 'red'], $result['optionData']);
        $this->assertSame(['brand' => 'Sony'], $result['tvData']);
        $this->assertNull($result['missingField']);
    }

    public function testParamsNormalizerBuildsKeysFromMapping(): void
    {
        $params = ImportCsvParamsNormalizer::normalize([
            'mapping' => [0 => 'pagetitle', 1 => 'option.size'],
            'update' => 1,
            'key' => 'article',
        ]);

        $this->assertSame(['pagetitle', 'option.size'], $params['keys']);
        $this->assertTrue($params['update']);
        $this->assertTrue($params['option_enabled']);
        $this->assertFalse($params['tv_enabled']);
    }
}
