<?php

namespace MiniShop3\Processors\Utilities\Import;

use MiniShop3\Services\Product\Import\ProductImportService;
use MODX\Revolution\Processors\Processor;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msProduct;

class Import extends Processor
{
    public $classKey = msProduct::class;
    public $objectType = 'msProduct';
    public $languageTopics = ['minishop3:default', 'minishop3:manager'];
    public $permission = 'msproduct_save';
    public $properties = [];

    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        $this->properties = $this->getProperties();
        return parent::initialize();
    }

    /**
     * {@inheritDoc}
     */
    public function checkPermissions(): bool
    {
        return !empty($this->permission) ? $this->modx->hasPermission($this->permission) : true;
    }

    /**
     * {@inheritDoc}
     */
    public function getLanguageTopics(): array
    {
        return $this->languageTopics;
    }

    /**
     * {@inheritDoc}
     */
    public function process(): array
    {
        // Check required fields - support both legacy (fields string) and new (mapping array) formats
        $mapping = $this->getProperty('mapping');
        $fields = $this->getProperty('fields');

        if (empty($mapping) && empty($fields)) {
            $this->addFieldError('fields', $this->modx->lexicon('field_required'));
            return $this->failure();
        }

        $required = ['importfile', 'delimiter'];
        foreach ($required as $field) {
            if (!trim($this->getProperty($field, ''))) {
                $this->addFieldError($field, $this->modx->lexicon('field_required'));
                return $this->failure();
            }
        }

        // Parse mapping if it's a JSON string
        if (!empty($mapping) && is_string($mapping)) {
            $mapping = json_decode($mapping, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->failure('Invalid mapping format');
            }
        }

        // Generate import ID for progress tracking
        $importId = uniqid('import_');

        $importParams = [
            'file' => $this->properties['importfile'],
            'fields' => $fields, // Legacy format
            'mapping' => $mapping, // New format
            'update' => $this->getProperty('update', false),
            'key' => $this->getProperty('key', 'article'),
            'debug' => $this->getProperty('debug', false),
            'delimiter' => $this->getProperty('delimiter', ';'),
            'skip_header' => $this->getProperty('skip_header', false),
            'import_id' => $importId,
        ];

        $useScheduler = $this->getProperty('scheduler', 0);

        // Check if file exceeds sync limit and scheduler is not enabled
        $syncLimit = (int)$this->modx->getOption('ms3_import_sync_limit', null, 300);
        $filePath = str_replace('//', '/', MODX_BASE_PATH . $importParams['file']);
        $realPath = realpath($filePath);

        if ($realPath && !$useScheduler) {
            $rowCount = ProductImportService::countRows($realPath, $importParams['delimiter']);
            if ($rowCount > $syncLimit) {
                // Return warning but allow to proceed
                // Frontend will show confirmation dialog
            }
        }

        if (empty($useScheduler)) {
            // Synchronous import
            /** @var ProductImportService $importService */
            $importService = $this->modx->services->get('ms3_product_import');
            $result = $importService->process($importParams);

            // Convert utils response format to processor format
            $data = $result['data'] ?? [];
            $data['import_id'] = $importId;
            $message = $result['message'] ?? '';

            if (!empty($result['success'])) {
                return $this->success($message, $data);
            } else {
                return $this->failure($message, $data);
            }
        }

        // Asynchronous import via Scheduler
        $schedulerPath = $this->modx->getOption(
            'scheduler.core_path',
            null,
            $this->modx->getOption('core_path') . 'components/scheduler/'
        );

        if (!file_exists($schedulerPath . 'model/scheduler/scheduler.class.php')) {
            return $this->failure($this->modx->lexicon('ms3_utilities_scheduler_nf'));
        }

        require_once $schedulerPath . 'model/scheduler/scheduler.class.php';
        $scheduler = new \Scheduler($this->modx);
        $task = $scheduler->getTask('MiniShop3', 'ms3_csv_import');

        if (!$task) {
            $task = $this->createImportTask();
        }

        if (empty($task)) {
            return $this->failure($this->modx->lexicon('ms3_utilities_scheduler_task_ce'));
        }

        $task->schedule('+1 second', $importParams);

        return $this->success($this->modx->lexicon('ms3_utilities_scheduler_success'), [
            'import_id' => $importId,
            'scheduled' => true,
        ]);
    }

    /**
     * Creating Scheduler's task for start import
     * @return false|object|null
     */
    private function createImportTask()
    {
        $task = $this->modx->newObject('sFileTask');
        $task->fromArray([
            'class_key' => 'sFileTask',
            'content' => '/elements/tasks/csvImport.php',
            'namespace' => 'MiniShop3',
            'reference' => 'ms3_csv_import',
            'description' => 'MiniShop3 CSV import'
        ]);

        if (!$task->save()) {
            return false;
        }

        return $task;
    }
}
