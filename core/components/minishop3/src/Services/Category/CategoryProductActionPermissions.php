<?php

namespace MiniShop3\Services\Category;

use MiniShop3\Router\HttpStatus;

/** Maps category product bulk/multiple actions to MODX permissions (#378) and document policies (#445). */
final class CategoryProductActionPermissions
{
    /** @var array<string, array{permission: string, document: list<string>}> */
    private const ACTIONS = [
        'publish' => ['permission' => 'msproduct_publish', 'document' => ['publish']],
        'unpublish' => ['permission' => 'msproduct_publish', 'document' => ['save', 'unpublish']],
        'delete' => ['permission' => 'msproduct_delete', 'document' => ['delete']],
        'undelete' => ['permission' => 'msproduct_delete', 'document' => ['save', 'undelete']],
        'show' => ['permission' => 'msproduct_save', 'document' => ['save']],
        'hide' => ['permission' => 'msproduct_save', 'document' => ['save']],
    ];

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
        return self::ACTIONS[$method]['permission'] ?? null;
    }

    /**
     * Resource-level MODX policies required before mutating a product document.
     *
     * @return list<string>|null null when method is unknown
     */
    public static function documentPoliciesForMethod(string $method): ?array
    {
        return self::ACTIONS[$method]['document'] ?? null;
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
     *
     * When denied, `message` is a lexicon key (resolved in CategoryProductsController).
     */
    public static function evaluate(string $method, callable $hasPermission): array
    {
        $permission = self::forMethod($method);
        if ($permission === null) {
            return [
                'allowed' => false,
                'status' => HttpStatus::BAD_REQUEST,
                'message' => 'ms3_err_unknown_method',
                'permission' => null,
                'reason' => 'unknown_method',
            ];
        }

        if (!$hasPermission($permission)) {
            return [
                'allowed' => false,
                'status' => HttpStatus::FORBIDDEN,
                'message' => 'ms3_err_access_denied_permission',
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
