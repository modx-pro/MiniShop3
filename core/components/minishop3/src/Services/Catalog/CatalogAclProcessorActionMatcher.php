<?php

declare(strict_types=1);

namespace MiniShop3\Services\Catalog;

use MODX\Revolution\modAccessResourceGroup;

/**
 * Whitelist of manager connector actions that mutate resource-group ACL or membership (#717).
 *
 * Verified against MODX Revolution 3 manager modExt widgets and core processors.
 */
final class CatalogAclProcessorActionMatcher
{
    /** @var list<string> */
    private const MUTATING_ACTIONS = [
        'Security/Access/UserGroup/ResourceGroup/Create',
        'Security/Access/UserGroup/ResourceGroup/Update',
        'Security/Access/UserGroup/ResourceGroup/Remove',
        'Security/Access/Flush',
        'Security/ResourceGroup/Create',
        'Security/ResourceGroup/Update',
        'Security/ResourceGroup/Remove',
        'Security/ResourceGroup/RemoveResource',
        'Security/ResourceGroup/UpdateResourcesIn',
        'Security/Group/Create',
        'Resource/ResourceGroup/UpdateFromGrid',
    ];

    /** @var list<string> */
    private const GENERIC_ACL_ACTIONS = [
        'Security/Access/AddAcl',
        'Security/Access/RemoveAcl',
        'Security/Access/UpdateAcl',
    ];

    /**
     * @param array<string, mixed> $requestParams Typically $_REQUEST from connector.php.
     */
    public static function matches(string $action, array $requestParams = []): bool
    {
        $action = trim($action);
        if ($action === '') {
            return false;
        }

        if (in_array($action, self::MUTATING_ACTIONS, true)) {
            return true;
        }

        return in_array($action, self::GENERIC_ACL_ACTIONS, true)
            && self::isResourceGroupAclType($requestParams['type'] ?? null);
    }

    private static function isResourceGroupAclType(mixed $type): bool
    {
        if (!is_string($type) || $type === '') {
            return false;
        }

        return in_array(ltrim($type, '\\'), [modAccessResourceGroup::class, 'modAccessResourceGroup'], true);
    }
}
