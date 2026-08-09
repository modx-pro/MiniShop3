<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Controllers\Api\Manager\Concerns\RunsMs3Processors;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * Manager REST for gallery utilities (processor bridge → Response envelope).
 *
 * @package MiniShop3\Controllers\Api\Manager
 */
class UtilitiesGalleryController
{
    use RunsMs3Processors;

    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * POST /api/mgr/utilities/gallery/update
     */
    public function update(array $params = []): Response
    {
        return $this->runMs3Processor(
            'MiniShop3\\Processors\\Utilities\\Gallery\\Update',
            $this->jsonBody()
        );
    }
}
