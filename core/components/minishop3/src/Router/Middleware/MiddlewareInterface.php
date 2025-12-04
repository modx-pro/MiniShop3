<?php

namespace MiniShop3\Router\Middleware;

use MiniShop3\Router\Response;

/**
 * Interface for all middleware
 */
interface MiddlewareInterface
{
    /**
     * Handle request
     *
     * @param array $params URL parameters
     * @return Response|null Return Response to interrupt, or null to continue
     */
    public function handle(array $params);
}
