<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product\Import;

use MiniShop3\MiniShop3;
use MiniShop3\Services\Import\ImportExtraFieldCatalog;
use MiniShop3\Utils\EventGate;
use MODX\Revolution\modX;

/**
 * Canonical CSV product import service (reader → row processor → upsert).
 */
class ProductImportService
{
    private modX $modx;
    private MiniShop3 $ms3;
    private ?ImportCsvContext $ctx = null;
    private ?string $detectedEncoding = null;
    private ImportCsvReader $reader;
    private ImportCsvRowProcessor $rowProcessor;
    private ImportCsvRunValidator $validator;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->ms3 = $this->modx->services->get('ms3');
        set_time_limit(600);
        $tmp = 'Trying to set time limit = 600 sec: ';
        $tmp .= ini_get('max_execution_time') == 600 ? 'done' : 'error';
        $this->modx->log(modX::LOG_LEVEL_INFO, $tmp);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
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

        $event = EventGate::invokeRaw($this->modx, 'msOnBeforeImport', [
            'file' => $this->ctx->params['file'],
            'params' => &$this->ctx->params,
        ]);
        $this->ctx->params = EventGate::applyReturnedArray(
            $this->ctx->params,
            $event['returnedValues'],
            'params'
        );
        if ($event['cancelled']) {
            return $this->ms3->utils->error($this->modx->lexicon('ms3_utilities_import_cancelled'));
        }

        $fileValidation = $this->validator->validateFilePath($this->ctx, $this->ctx->params['file']);
        if ($fileValidation !== true) {
            return $fileValidation;
        }

        // Ensure msExtraField / Object Extension columns are in xPDO maps before save.
        $this->ms3->loadMap();

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
        $fieldMapper = new ImportCsvRowFieldMapper(new ImportExtraFieldCatalog($this->modx));
        $this->rowProcessor = new ImportCsvRowProcessor($this->ctx, $upserter, $fieldMapper);
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

    /**
     * @return array<string, mixed>|null
     */
    public static function getProgress(modX $modx, string $importId): ?array
    {
        return ImportCsvProgressTracker::getProgress($modx, $importId);
    }

    public static function countRows(string $filePath, string $delimiter = ';'): int
    {
        return ImportCsvReader::countRows($filePath, $delimiter);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPreview(string $filePath, string $delimiter = ';', int $rows = 5, bool $skipHeader = false): array
    {
        return ImportCsvReader::getPreview($filePath, $delimiter, $rows, $skipHeader);
    }

    /**
     * @return list<string>
     */
    public static function detectHeaders(string $filePath, string $delimiter = ';'): array
    {
        return ImportCsvReader::detectHeaders($filePath, $delimiter);
    }
}
