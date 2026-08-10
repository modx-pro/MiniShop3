<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Controllers\Api\Manager\Concerns\RunsMs3Processors;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * Manager REST for CSV product import (processor bridge → Response envelope).
 *
 * @package MiniShop3\Controllers\Api\Manager
 */
class ImportController
{
    use RunsMs3Processors;

    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * GET /api/mgr/import/fields
     */
    public function fields(array $params = []): Response
    {
        return $this->runMs3Processor('MiniShop3\\Processors\\Utilities\\Import\\Fields');
    }

    /**
     * POST /api/mgr/import/upload
     */
    public function upload(array $params = []): Response
    {
        return $this->runMs3Processor(
            'MiniShop3\\Processors\\Utilities\\Import\\Upload',
            $_POST
        );
    }

    /**
     * POST /api/mgr/import/preview
     */
    public function preview(array $params = []): Response
    {
        return $this->runMs3Processor(
            'MiniShop3\\Processors\\Utilities\\Import\\Preview',
            $this->jsonBody()
        );
    }

    /**
     * POST /api/mgr/import/start
     */
    public function start(array $params = []): Response
    {
        return $this->runMs3Processor(
            'MiniShop3\\Processors\\Utilities\\Import\\Import',
            $this->jsonBody()
        );
    }

    /**
     * GET /api/mgr/import/progress/{import_id}
     */
    public function progress(array $params = []): Response
    {
        return $this->runMs3Processor(
            'MiniShop3\\Processors\\Utilities\\Import\\Progress',
            ['import_id' => $params['import_id'] ?? '']
        );
    }
}
