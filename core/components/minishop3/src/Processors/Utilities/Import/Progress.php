<?php

namespace MiniShop3\Processors\Utilities\Import;

use MiniShop3\Services\Product\Import\ProductImportService;
use MODX\Revolution\Processors\Processor;

/**
 * Get import progress by import ID
 */
class Progress extends Processor
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
        $importId = $this->getProperty('import_id');

        if (empty($importId)) {
            return $this->failure('Import ID is required');
        }

        $progress = ProductImportService::getProgress($this->modx, $importId);

        if ($progress === null) {
            return $this->failure('Import not found or expired');
        }

        // Check if import is stale (no update for more than 60 seconds)
        $isStale = (time() - $progress['timestamp']) > 60;

        return $this->success('', [
            'import_id' => $progress['import_id'],
            'current' => $progress['current'],
            'total' => $progress['total'],
            'percent' => $progress['percent'],
            'created' => $progress['created'],
            'updated' => $progress['updated'],
            'errors' => $progress['errors'],
            'skipped' => $progress['skipped'],
            'completed' => $progress['completed'],
            'stale' => $isStale,
        ]);
    }
}
