<?php

namespace MiniShop3\Processors\Utilities\Import;

use MiniShop3\Services\Product\Import\ProductImportService;
use MODX\Revolution\Processors\Processor;

/**
 * Preview CSV file - returns headers and first N rows
 */
class Preview extends Processor
{
    public $languageTopics = ['minishop3:default', 'minishop3:manager'];
    public $permission = 'msproduct_save';

    public function checkPermissions(): bool
    {
        return !empty($this->permission) ? $this->modx->hasPermission($this->permission) : true;
    }

    public function getLanguageTopics(): array
    {
        return $this->languageTopics;
    }

    public function process(): array
    {
        try {
            $file = $this->getProperty('file');
            $delimiter = $this->getProperty('delimiter') ?: ';';
            $defaultRows = (int)$this->modx->getOption('ms3_import_preview_rows', null, 5);
            $previewRows = (int)($this->getProperty('rows') ?: $defaultRows);

            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_INFO,
                "[Import Preview] file=$file, delimiter=$delimiter, rows=$previewRows");

            if (empty($file)) {
                return $this->failure($this->modx->lexicon('ms3_utilities_import_file_ns'));
            }

            if (!preg_match('/\.csv$/i', $file)) {
                return $this->failure($this->modx->lexicon('ms3_utilities_import_file_ext_err'));
            }

            // Build and validate file path
            $fullPath = str_replace('//', '/', MODX_BASE_PATH . $file);
            $realPath = realpath($fullPath);
            $realBasePath = realpath(MODX_BASE_PATH);

            if ($realPath === false) {
                return $this->failure($this->modx->lexicon('ms3_utilities_import_file_nf', ['path' => $fullPath]));
            }

            // Path traversal protection
            if (!str_starts_with($realPath, $realBasePath)) {
                return $this->failure($this->modx->lexicon('ms3_utilities_import_file_outside'));
            }

            // Get headers (first row)
            $headers = ProductImportService::detectHeaders($realPath, $delimiter);

            // Count total rows
            $totalRows = ProductImportService::countRows($realPath, $delimiter);

            // Get preview data with encoding info
            $previewData = ProductImportService::getPreview($realPath, $delimiter, $previewRows, false);
            $preview = $previewData['rows'] ?? [];
            $encoding = $previewData['encoding'] ?? 'UTF-8';

            // Get sync limit from settings
            $syncLimit = (int)$this->modx->getOption('ms3_import_sync_limit', null, 300);

            // Check if Scheduler is available
            $schedulerAvailable = $this->isSchedulerAvailable();

            // Determine if file exceeds sync limit
            $exceedsLimit = $totalRows > $syncLimit;

            return $this->success('', [
                'file' => $file,
                'headers' => $headers,
                'preview' => $preview,
                'encoding' => $encoding,
                'total_rows' => $totalRows,
                'sync_limit' => $syncLimit,
                'exceeds_limit' => $exceedsLimit,
                'scheduler_available' => $schedulerAvailable,
                'delimiter' => $delimiter,
            ]);
        } catch (\Throwable $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR,
                "[Import Preview] Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return $this->failure('Preview error: ' . $e->getMessage());
        }
    }

    /**
     * Check if Scheduler component is installed
     */
    private function isSchedulerAvailable(): bool
    {
        $schedulerPath = $this->modx->getOption(
            'scheduler.core_path',
            null,
            $this->modx->getOption('core_path') . 'components/scheduler/'
        );

        return file_exists($schedulerPath . 'model/scheduler/scheduler.class.php');
    }
}
