<?php

namespace MiniShop3\Services\Category;

use MiniShop3\Router\HttpStatus;

/** Maps category product bulk/multiple actions to MODX permissions (#378). */
final class CategoryProductActionPermissions
{
    /**
     * Permissions that may authorize POST …/products/multiple (route gate).
     *
     * @return list<string>
     */
    public static function mutationPermissions(): array
    {
        return ['msproduct_publish', 'msproduct_delete', 'msproduct_save'];
    }

    public static function forMethod(string $method): ?string
    {
        return match ($method) {
            'publish', 'unpublish' => 'msproduct_publish',
            'delete', 'undelete' => 'msproduct_delete',
            'show', 'hide' => 'msproduct_save',
            default => null,
        };
    }

    /**
     * Evaluate access for a bulk/multiple action before any product mutation.
     *
     * @param callable(string):bool $hasPermission
     * @return array{
     *     allowed: bool,
     *     status: int,
     *     message: string,
     *     permission: ?string,
     *     reason: 'ok'|'unknown_method'|'forbidden'
     * }
     */
    public static function evaluate(string $method, callable $hasPermission): array
    {
        $permission = self::forMethod($method);
        if ($permission === null) {
            return [
                'allowed' => false,
                'status' => HttpStatus::BAD_REQUEST,
                'message' => 'Unknown method',
                'permission' => null,
                'reason' => 'unknown_method',
            ];
        }

        if (!$hasPermission($permission)) {
            return [
                'allowed' => false,
                'status' => HttpStatus::FORBIDDEN,
                'message' => "Access denied. Required permission: {$permission}",
                'permission' => $permission,
                'reason' => 'forbidden',
            ];
        }

        return [
            'allowed' => true,
            'status' => HttpStatus::OK,
            'message' => '',
            'permission' => $permission,
            'reason' => 'ok',
        ];
    }
}
