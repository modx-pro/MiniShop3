<?php

namespace MiniShop3\Services\Category;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msProduct;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * Resource-level MODX ACL (checkPolicy) for category product Manager API (#445).
 *
 * Complements global msproduct_* / view_document route permissions (#378).
 */
final class CategoryProductDocumentPolicy
{
    public const POLICY_VIEW = 'view';
    public const POLICY_SAVE = 'save';
    public const POLICY_PUBLISH = 'publish';
    public const POLICY_DELETE = 'delete';
    public const POLICY_UNPUBLISH = 'unpublish';
    public const POLICY_UNDELETE = 'undelete';

    public static function categoryViewPolicy(): string
    {
        return self::POLICY_VIEW;
    }

    /** @return list<string> */
    public static function sortPolicies(): array
    {
        return [self::POLICY_SAVE];
    }

    /** @return list<string> */
    public static function policiesForPublish(bool $published): array
    {
        return $published
            ? [self::POLICY_PUBLISH]
            : [self::POLICY_SAVE, self::POLICY_UNPUBLISH];
    }

    public static function isAllowed(object $resource, string $policy): bool
    {
        return self::evaluate($resource, $policy) === null;
    }

    /** @param list<string> $policies */
    public static function isAllowedAll(object $resource, array $policies): bool
    {
        return self::evaluateAll($resource, $policies) === null;
    }

    /**
     * @return array{status: int, message: string}|null null when allowed
     */
    public static function evaluate(object $resource, string $policy): ?array
    {
        if (method_exists($resource, 'checkPolicy') && $resource->checkPolicy($policy)) {
            return null;
        }

        return [
            'status' => HttpStatus::FORBIDDEN,
            'message' => self::messageForPolicy($policy),
        ];
    }

    /**
     * @param list<string> $policies
     * @return array{status: int, message: string}|null null when allowed
     */
    public static function evaluateAll(object $resource, array $policies): ?array
    {
        foreach ($policies as $policy) {
            $denied = self::evaluate($resource, $policy);
            if ($denied !== null) {
                return $denied;
            }
        }

        return null;
    }

    public static function denialResponse(object $resource, string $policy): ?array
    {
        return self::toErrorResponse(self::evaluate($resource, $policy));
    }

    /** @param list<string> $policies */
    public static function denialResponseAll(object $resource, array $policies): ?array
    {
        return self::toErrorResponse(self::evaluateAll($resource, $policies));
    }

    public static function canViewInCategoryGrid(modX $modx, msProduct $product, bool $nested): bool
    {
        if (!self::isAllowed($product, self::POLICY_VIEW)) {
            return false;
        }

        if (!$nested) {
            return true;
        }

        $parentId = (int) $product->get('parent');
        if ($parentId <= 0) {
            return false;
        }

        $parent = $modx->getObject(msCategory::class, $parentId);
        if (!$parent instanceof msCategory) {
            return false;
        }

        return self::isAllowed($parent, self::POLICY_VIEW);
    }

    /**
     * @param array<int, msCategory> $parentsById
     */
    public static function canViewInCategoryGridCached(
        msProduct $product,
        bool $nested,
        array $parentsById,
    ): bool {
        if (!self::isAllowed($product, self::POLICY_VIEW)) {
            return false;
        }

        if (!$nested) {
            return true;
        }

        $parentId = (int) $product->get('parent');
        if ($parentId <= 0) {
            return false;
        }

        $parent = $parentsById[$parentId] ?? null;
        if (!$parent instanceof msCategory) {
            return false;
        }

        return self::isAllowed($parent, self::POLICY_VIEW);
    }

    public static function toErrorResponse(?array $evaluation): ?array
    {
        if ($evaluation === null) {
            return null;
        }

        return Response::error(
            $evaluation['message'],
            $evaluation['status']
        )->getData();
    }

    private static function messageForPolicy(string $policy): string
    {
        return match ($policy) {
            self::POLICY_VIEW => 'View permission denied for this document',
            self::POLICY_SAVE => 'Save permission denied for this document',
            self::POLICY_PUBLISH => 'Publish permission denied for this document',
            self::POLICY_DELETE => 'Delete permission denied for this document',
            self::POLICY_UNPUBLISH => 'Unpublish permission denied for this document',
            self::POLICY_UNDELETE => 'Undelete permission denied for this document',
            default => 'Access denied for this document',
        };
    }
}
