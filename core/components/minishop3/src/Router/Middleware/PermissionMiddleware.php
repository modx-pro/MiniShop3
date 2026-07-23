<?php

namespace MiniShop3\Router\Middleware;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * Middleware for access permission check
 */
class PermissionMiddleware implements MiddlewareInterface
{
    protected $modx;
    protected $permission;

    /**
     * @param modX $modx
     * @param string $permission Permission name
     */
    public function __construct(modX $modx, string $permission)
    {
        $this->modx = $modx;
        $this->permission = $permission;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $params)
    {
        if (!$this->modx->hasPermission($this->permission)) {
            return Response::error(
                "Access denied. Required permission: {$this->permission}",
                HttpStatus::FORBIDDEN
            );
        }

        return null;
    }
}
