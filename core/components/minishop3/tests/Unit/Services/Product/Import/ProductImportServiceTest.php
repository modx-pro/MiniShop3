<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Product\Import;

use MiniShop3\Services\Product\Import\ProductImportService;
use MiniShop3\Utils\ImportCSV;
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

    public function testDeprecatedImportCsvWrapperExists(): void
    {
        $this->assertTrue(class_exists(ImportCSV::class));
        $this->assertTrue(method_exists(ImportCSV::class, 'process'));
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
