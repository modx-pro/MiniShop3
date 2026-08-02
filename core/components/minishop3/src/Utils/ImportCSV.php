<?php

namespace MiniShop3\Utils;

use MiniShop3\MiniShop3;
use MiniShop3\Services\Product\Import\ImportCsvContext;
use MiniShop3\Services\Product\Import\ImportCsvEventBridge;
use MiniShop3\Services\Product\Import\ImportCsvGalleryHandler;
use MiniShop3\Services\Product\Import\ImportCsvOptionHandler;
use MiniShop3\Services\Product\Import\ImportCsvParamsNormalizer;
use MiniShop3\Services\Product\Import\ImportCsvProductUpserter;
use MiniShop3\Services\Product\Import\ImportCsvProgressTracker;
use MiniShop3\Services\Product\Import\ImportCsvReader;
use MiniShop3\Services\Product\Import\ImportCsvRowProcessor;
use MiniShop3\Services\Product\Import\ImportCsvRunValidator;
use MODX\Revolution\modX;

/**
 * Thin facade: orchestrates CSV product import (reader → rows → upsert).
 */
class ImportCSV
{
    private modX $modx;
    private MiniShop3 $ms3;
    private ?ImportCsvContext $ctx = null;
    private ?string $detectedEncoding = null;
    private ImportCsvReader $reader;
    private ImportCsvRowProcessor $rowProcessor;
    private ImportCsvRunValidator $validator;

    public function __construct(modX &$modx)
    {
        $this->modx = $modx;
        $this->ms3 = $this->modx->services->get('ms3');
        set_time_limit(600);
        $tmp = 'Trying to set time limit = 600 sec: ';
        $tmp .= ini_get('max_execution_time') == 600 ? 'done' : 'error';
        $this->modx->log(modX::LOG_LEVEL_INFO, $tmp);
    }

    public function process(array $params): array
    {
        $importId = $params['import_id'] ?? uniqid('import_');
        $this->ctx = new ImportCsvContext(
            modx: $this->modx,
            ms3: $this->ms3,
            params: ImportCsvParamsNormalizer::normalize($params),
            importId: $importId,
        );
        $this->initCollaborators();

        if (($error = $this->validator->validateParams($this->ctx)) !== null) {
            return $error;
        }

        $fileValidation = $this->validator->validateFilePath($this->ctx, $this->ctx->params['file']);
        if ($fileValidation !== true) {
            return $fileValidation;
        }

        ImportCsvEventBridge::clearReturnedValues($this->modx);
        $eventResult = $this->modx->invokeEvent('msOnBeforeImport', [
            'file' => $this->ctx->params['file'],
            'params' => &$this->ctx->params,
        ]);
        $this->ctx->params = ImportCsvEventBridge::applyReturnedArray(
            $this->ctx->params,
            ImportCsvEventBridge::getReturnedValues($this->modx),
            'params'
        );
        if (ImportCsvEventBridge::isCancelled($eventResult)) {
            return $this->ms3->utils->error($this->modx->lexicon('ms3_utilities_import_cancelled'));
        }

        $this->importRows();
        $this->detectedEncoding = $this->ctx->detectedEncoding;

        $this->modx->invokeEvent('msOnAfterImport', [
            'stats' => [
                'total' => $this->ctx->rows,
                'created' => $this->ctx->created,
                'updated' => $this->ctx->updated,
                'errors' => $this->ctx->errors,
                'skipped' => $this->ctx->skipped,
            ],
        ]);

        return $this->ms3->utils->success(
            $this->modx->lexicon('ms3_utilities_import_success', [
                'total' => $this->ctx->rows,
                'created' => $this->ctx->created,
                'updated' => $this->ctx->updated,
            ]),
            [
                'total' => $this->ctx->rows,
                'created' => $this->ctx->created,
                'updated' => $this->ctx->updated,
                'errors' => $this->ctx->errors,
                'skipped' => $this->ctx->skipped,
            ]
        );
    }

    private function initCollaborators(): void
    {
        $this->reader = new ImportCsvReader($this->modx);
        $this->validator = new ImportCsvRunValidator($this->modx, $this->ms3);
        $optionHandler = new ImportCsvOptionHandler($this->modx);
        $galleryHandler = new ImportCsvGalleryHandler($this->modx);
        $upserter = new ImportCsvProductUpserter($this->ctx, $optionHandler, $galleryHandler);
        $this->rowProcessor = new ImportCsvRowProcessor($this->ctx, $upserter);
    }

    private function importRows(): void
    {
        $preparedFile = $this->reader->prepareFile($this->ctx->params['file'], $this->ctx);
        $handle = fopen($preparedFile, 'r');

        $totalRows = 0;
        while (fgetcsv($handle, 0, $this->ctx->params['delimiter']) !== false) {
            $totalRows++;
        }
        rewind($handle);

        $rowsToProcess = $this->ctx->params['skip_header'] ? $totalRows - 1 : $totalRows;
        ImportCsvProgressTracker::save($this->ctx, 0, $rowsToProcess);

        while (($csv = fgetcsv($handle, 0, $this->ctx->params['delimiter'])) !== false) {
            $this->ctx->rows++;

            if (!empty($this->ctx->params['skip_header']) && $this->ctx->rows === 1) {
                continue;
            }

            $this->rowProcessor->process($csv);
            ImportCsvProgressTracker::save($this->ctx, $this->ctx->rows, $rowsToProcess);

            if ($this->ctx->params['is_debug'] && $this->ctx->rows === 1) {
                $this->modx->log(
                    modX::LOG_LEVEL_INFO,
                    'You in debug mode, so we process only 1 row. Time: ' . number_format(
                        microtime(true) - $this->modx->startTime,
                        7
                    ) . ' s'
                );
                break;
            }
        }
        fclose($handle);

        ImportCsvProgressTracker::save($this->ctx, $this->ctx->rows, $rowsToProcess, true);
    }

    public function getDetectedEncoding(): ?string
    {
        return $this->detectedEncoding;
    }

    public static function getProgress(modX $modx, string $importId): ?array
    {
        return ImportCsvProgressTracker::getProgress($modx, $importId);
    }

    public static function countRows(string $filePath, string $delimiter = ';'): int
    {
        return ImportCsvReader::countRows($filePath, $delimiter);
    }

    public static function getPreview(string $filePath, string $delimiter = ';', int $rows = 5, bool $skipHeader = false): array
    {
        return ImportCsvReader::getPreview($filePath, $delimiter, $rows, $skipHeader);
    }

    public static function detectHeaders(string $filePath, string $delimiter = ';'): array
    {
        return ImportCsvReader::detectHeaders($filePath, $delimiter);
    }

    public static function detectEncoding(string $content): string
    {
        return ImportCsvReader::detectEncoding($content);
    }

    public static function removeBom(string $content): string
    {
        return ImportCsvReader::removeBom($content);
    }

    public static function convertToUtf8(string $content, ?string $fromEncoding = null): string
    {
        return ImportCsvReader::convertToUtf8($content, $fromEncoding);
    }
}
