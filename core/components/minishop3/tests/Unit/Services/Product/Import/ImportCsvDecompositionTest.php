<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Product\Import;

use MiniShop3\Services\Product\Import\ImportCsvParamsNormalizer;
use MiniShop3\Services\Product\Import\ImportCsvPathGuard;
use MiniShop3\Services\Product\Import\ImportCsvReader;
use MiniShop3\Services\Product\Import\ImportCsvRowFieldMapper;
use PHPUnit\Framework\TestCase;

final class ImportCsvDecompositionTest extends TestCase
{
    private string $tempBase;

    protected function setUp(): void
    {
        $this->tempBase = sys_get_temp_dir() . '/ms3_import_test_' . uniqid('', true);
        mkdir($this->tempBase, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempBase);
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

    public function testMapReportsMissingCsvColumn(): void
    {
        $mapper = new ImportCsvRowFieldMapper();
        $result = $mapper->map(
            ['pagetitle', 'price'],
            ['Only title'],
            static fn (string $name): int => 0,
        );

        $this->assertSame('price', $result['missingField']);
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

    public function testPathGuardResolvesCsvUnderBase(): void
    {
        $csvPath = $this->tempBase . '/import.csv';
        file_put_contents($csvPath, "a;b\n");

        $resolved = ImportCsvPathGuard::resolveCsvFileUnderBase('import.csv', $this->tempBase . '/');

        $this->assertSame(realpath($csvPath), $resolved);
    }

    public function testPathGuardRejectsTraversalForCsv(): void
    {
        $this->assertNull(ImportCsvPathGuard::resolveCsvFileUnderBase('../etc/passwd.csv', $this->tempBase . '/'));
    }

    public function testPathGuardResolvesAssetAndRejectsDotDot(): void
    {
        $assetsDir = $this->tempBase . '/assets';
        mkdir($assetsDir);
        $imagePath = $assetsDir . '/photo.jpg';
        file_put_contents($imagePath, 'jpeg');

        $resolved = ImportCsvPathGuard::resolveAssetUnderBase('assets/photo.jpg', $this->tempBase . '/');
        $this->assertSame(realpath($imagePath), $resolved);

        $this->assertNull(ImportCsvPathGuard::resolveAssetUnderBase('../outside.jpg', $this->tempBase . '/'));
    }

    public function testPathGuardAcceptsAbsoluteCsvAlreadyUnderBase(): void
    {
        $csvPath = $this->tempBase . '/absolute.csv';
        file_put_contents($csvPath, "x\n");

        $resolved = ImportCsvPathGuard::resolveCsvFileUnderBase($csvPath, $this->tempBase . '/');

        $this->assertSame(realpath($csvPath), $resolved);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
