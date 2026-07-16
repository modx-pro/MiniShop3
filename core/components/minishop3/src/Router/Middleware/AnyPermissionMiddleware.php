<?php

namespace MiniShop3\Router\Middleware;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * Allow the request when the user has at least one of the listed permissions.
 */
class AnyPermissionMiddleware implements MiddlewareInterface
{
    protected modX $modx;

    /** @var list<string> */
    protected array $permissions;

    /**
     * @param list<string> $permissions
     */
    public function __construct(modX $modx, array $permissions)
    {
        $this->modx = $modx;
        $this->permissions = array_values(array_filter($permissions, static fn($p) => is_string($p) && $p !== ''));
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $params)
    {
        foreach ($this->permissions as $permission) {
            if ($this->modx->hasPermission($permission)) {
                return null;
            }
        }

        $required = $this->permissions !== []
            ? implode(' or ', $this->permissions)
            : '(none configured)';

        return Response::error(
            "Access denied. Required permission: {$required}",
            HttpStatus::FORBIDDEN
        );
    }
}
