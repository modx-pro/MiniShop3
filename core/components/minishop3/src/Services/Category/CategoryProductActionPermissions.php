<?php

namespace MiniShop3\Services\Category;

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
}
