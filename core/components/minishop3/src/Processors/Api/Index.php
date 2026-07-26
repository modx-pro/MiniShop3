<?php

namespace MiniShop3\Processors\Api;

use MODX\Revolution\Processors\Processor;

/**
 * Processor for handling API requests through connector.php (Vue Manager / custom mgr fronts).
 *
 * Manager routes only: manager.php, ms3_routes_manager.custom.php, ms3.routes.d/manager/.
 * Storefront /api/v1 lives on api.php — not loaded here (#384).
 *
 * Usage:
 * connector.php?action=MiniShop3\Processors\Api\Index&route=/api/mgr/...
 */
class Index extends Processor
{
    use ProcessesManagerConnectorRouteTrait;

    /** @var string $permission Empty string = public access without permission check */
    public $permission = '';

    /**
     * Router middleware enforces mgr Auth + permissions per route.
     */
    public function checkPermissions()
    {
        return true;
    }
}
