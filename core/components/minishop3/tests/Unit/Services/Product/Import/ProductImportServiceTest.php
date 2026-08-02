<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Product\Import;

use MiniShop3\Services\Product\Import\ProductImportService;
use PHPUnit\Framework\TestCase;

final class ProductImportServiceTest extends TestCase
{
    public function testServiceExposesImportApi(): void
    {
        $this->assertTrue(method_exists(ProductImportService::class, 'process'));
        $this->assertTrue(method_exists(ProductImportService::class, 'countRows'));
        $this->assertTrue(method_exists(ProductImportService::class, 'getPreview'));
        $this->assertTrue(method_exists(ProductImportService::class, 'detectHeaders'));
        $this->assertTrue(method_exists(ProductImportService::class, 'getProgress'));
    }

    public function testLegacyImportCsvWrapperFileExists(): void
    {
        $path = dirname(__DIR__, 5) . '/src/Utils/ImportCSV.php';
        $this->assertFileExists($path);
    }

    public function testServiceRegistryDeclaresProductImportKey(): void
    {
        $registryPath = dirname(__DIR__, 5) . '/src/ServiceRegistry.php';
        $source = file_get_contents($registryPath);
        $this->assertNotFalse($source);
        $this->assertStringContainsString("'ms3_product_import'", $source);
        $this->assertStringContainsString(ProductImportService::class, $source);
    }
}
