<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx\Support;

use MODX\Revolution\modAccessContext;
use MODX\Revolution\modAccessPermission;
use MODX\Revolution\modAccessPolicy;
use MODX\Revolution\modAccessPolicyTemplate;
use MODX\Revolution\modUser;
use MODX\Revolution\modUserGroup;
use MODX\Revolution\modUserGroupRole;

/**
 * Grant context ACL permissions to a non-sudo user for live processor checks (#710).
 *
 * Builds an isolated user group + policy on the current `$modx->context` key
 * (testbench boots `web`, not `mgr`).
 *
 * @phpstan-require-extends ExtraTestCase
 */
trait GrantsContextPermissions
{
    /**
     * Attach `$permissions` to `$user` via a dedicated group/policy on the live context.
     *
     * @param non-empty-list<string> $permissions Permission keys (e.g. `mssetting_save`)
     */
    protected function grantContextPermissions(modUser $user, array $permissions): modAccessPolicy
    {
        self::assertNotSame([], $permissions, 'grantContextPermissions requires at least one permission');

        $contextKey = (string) $this->modx->context->get('key');
        self::assertNotSame('', $contextKey, 'Cannot grant context permissions without a context key');

        $suffix = bin2hex(random_bytes(4));
        $role = $this->modx->getObject(modUserGroupRole::class, ['name' => modUserGroupRole::ROLE_MEMBER]);
        self::assertInstanceOf(modUserGroupRole::class, $role, 'Member role is required in testbench');

        $template = $this->modx->newObject(modAccessPolicyTemplate::class);
        $template->fromArray([
            'name' => 'ms3-test-tpl-' . $suffix,
            'description' => 'MiniShop3 live ACL test template',
            'lexicon' => 'permissions',
            'template_group' => 1,
        ], '', true);
        self::assertTrue($template->save(), 'Failed to save ACL test policy template');

        $templateId = (int) $template->get('id');
        foreach ($permissions as $name) {
            $perm = $this->modx->newObject(modAccessPermission::class);
            $perm->fromArray([
                'template' => $templateId,
                'name' => $name,
                'description' => $name,
                'value' => true,
            ], '', true);
            self::assertTrue($perm->save(), 'Failed to save ACL test permission ' . $name);
        }

        $policy = $this->modx->newObject(modAccessPolicy::class);
        $policy->fromArray([
            'name' => 'ms3-test-policy-' . $suffix,
            'description' => 'MiniShop3 live ACL test policy',
            'template' => $templateId,
            'data' => json_encode(array_fill_keys($permissions, true), JSON_THROW_ON_ERROR),
            'lexicon' => 'permissions',
        ], '', true);
        self::assertTrue($policy->save(), 'Failed to save ACL test policy');

        $group = $this->modx->newObject(modUserGroup::class);
        $group->fromArray([
            'name' => 'ms3-test-group-' . $suffix,
            'description' => 'MiniShop3 live ACL test group',
        ], '', true);
        self::assertTrue($group->save(), 'Failed to save ACL test user group');

        $access = $this->modx->newObject(modAccessContext::class);
        $access->fromArray([
            'target' => $contextKey,
            'principal_class' => modUserGroup::class,
            'principal' => (int) $group->get('id'),
            'authority' => 9999,
            'policy' => (int) $policy->get('id'),
        ], '', true);
        self::assertTrue($access->save(), 'Failed to save ACL test modAccessContext');

        self::assertTrue(
            $user->joinGroup((int) $group->get('id'), (int) $role->get('id')),
            'Failed to join user to ACL test group'
        );

        $this->refreshPermissionCaches();

        return $policy;
    }

    /**
     * Strip granted keys from a test policy without emptying `data`.
     *
     * Empty `modAccessPolicy.data` is treat-as-allow-all in MODX `checkPolicy()`.
     * Keep a harmless sentinel (`load`) so revoke still denies the target permission.
     */
    protected function revokeContextPermissions(modAccessPolicy $policy): void
    {
        $policy->set('data', json_encode(['load' => true], JSON_THROW_ON_ERROR));
        self::assertTrue($policy->save(), 'Failed to revoke ACL test policy permissions');
        $this->refreshPermissionCaches();
    }

    private function refreshPermissionCaches(): void
    {
        $this->modx->context->setPolicies([]);
        $this->clearUserAttributeSessionCache();
        if ($this->modx->user instanceof modUser) {
            $this->modx->user->getAttributes([], '', true);
        }
    }
}
