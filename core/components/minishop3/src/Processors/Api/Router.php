<?php

namespace MiniShop3\Processors\Api;

use MODX\Revolution\Processors\Processor;

/**
 * Processor for handling API requests through connector.php (built-in MODX manager UI).
 *
 * Manager routes only: manager.php, ms3_routes_manager.custom.php, ms3.routes.d/manager/.
 * Storefront /api/v1 lives on api.php (#384).
 *
 * Usage:
 * connector.php?action=MiniShop3\Processors\Api\Router&route=/api/mgr/...
 */
class Router extends Processor
{
    use ProcessesManagerConnectorRouteTrait;
}
