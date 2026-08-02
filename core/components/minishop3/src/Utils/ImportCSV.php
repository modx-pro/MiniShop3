<?php

namespace MiniShop3\Utils;

use MiniShop3\Services\Product\Import\ProductImportService;
use MODX\Revolution\modX;

/**
 * @deprecated Use $modx->services->get('ms3_product_import') ({@see ProductImportService}) instead.
 *             Kept for backward compatibility with external `new ImportCSV($modx)` call sites.
 */
class ImportCSV
{
    private ProductImportService $service;

    public function __construct(modX &$modx)
    {
        $this->service = $modx->services->has('ms3_product_import')
            ? $modx->services->get('ms3_product_import')
            : new ProductImportService($modx);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function process(array $params): array
    {
        return $this->service->process($params);
    }

    public function getDetectedEncoding(): ?string
    {
        return $this->service->getDetectedEncoding();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getProgress(modX $modx, string $importId): ?array
    {
        return ProductImportService::getProgress($modx, $importId);
    }

    public static function countRows(string $filePath, string $delimiter = ';'): int
    {
        return ProductImportService::countRows($filePath, $delimiter);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPreview(string $filePath, string $delimiter = ';', int $rows = 5, bool $skipHeader = false): array
    {
        return ProductImportService::getPreview($filePath, $delimiter, $rows, $skipHeader);
    }

    /**
     * @return list<string>
     */
    public static function detectHeaders(string $filePath, string $delimiter = ';'): array
    {
        return ProductImportService::detectHeaders($filePath, $delimiter);
    }
}
