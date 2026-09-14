<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx;

use MiniShop3\Model\msProduct;
use MiniShop3\Services\Catalog\CatalogResourceGroupVisibility;
use MiniShop3\Tests\Modx\Support\ExtraTestCase;
use MODX\Revolution\modAccessResourceGroup;
use MODX\Revolution\modResourceGroup;
use MODX\Revolution\modResourceGroupResource;
use MODX\Revolution\modUserGroup;

/**
 * Live MySQL: ACL rows use principal_class = modUserGroup::class as MODX 3 writes them.
 * Guards connection quoting of backslashes (#666 review / #659 leak).
 */
final class CatalogResourceGroupVisibilityLiveTest extends ExtraTestCase
{
    public function testForeignUserGroupAclHidesAnonymousCatalogProduct(): void
    {
        $this->modx->setOption(CatalogResourceGroupVisibility::SETTING_KEY, true);
        $this->modx->setOption('access_resource_group_enabled', true);

        $suffix = bin2hex(random_bytes(3));
        $product = $this->createPublishedProduct('tb-rg-hidden-' . $suffix);
        $group = $this->createResourceGroup('TB Closed ' . $suffix);
        $this->linkProductToGroup((int) $product->get('id'), (int) $group->get('id'));
        $this->createAcl(
            (int) $group->get('id'),
            principal: 1,
            contextKey: 'web',
        );

        self::assertFalse(
            $this->isProductVisibleViaFilter((int) $product->get('id'), 'web'),
            'Product in RG with foreign user-group ACL must be hidden for anonymous web'
        );
    }

    public function testAnonymousPrincipalGrantKeepsProductVisible(): void
    {
        $this->modx->setOption(CatalogResourceGroupVisibility::SETTING_KEY, true);
        $this->modx->setOption('access_resource_group_enabled', true);

        $suffix = bin2hex(random_bytes(3));
        $product = $this->createPublishedProduct('tb-rg-anon-' . $suffix);
        $group = $this->createResourceGroup('TB Anon ' . $suffix);
        $this->linkProductToGroup((int) $product->get('id'), (int) $group->get('id'));
        $this->createAcl(
            (int) $group->get('id'),
            principal: 1,
            contextKey: 'web',
        );
        $this->createAcl(
            (int) $group->get('id'),
            principal: 0,
            contextKey: 'web',
        );

        self::assertTrue(
            $this->isProductVisibleViaFilter((int) $product->get('id'), 'web'),
            'Explicit principal=0 grant must keep product visible'
        );
    }

    public function testRestrictedPlusAnonymousMembershipIsVisible(): void
    {
        $this->modx->setOption(CatalogResourceGroupVisibility::SETTING_KEY, true);
        $this->modx->setOption('access_resource_group_enabled', true);

        $suffix = bin2hex(random_bytes(3));
        $product = $this->createPublishedProduct('tb-rg-or-' . $suffix);
        $closed = $this->createResourceGroup('TB Closed OR ' . $suffix);
        $open = $this->createResourceGroup('TB Open OR ' . $suffix);
        $this->linkProductToGroup((int) $product->get('id'), (int) $closed->get('id'));
        $this->linkProductToGroup((int) $product->get('id'), (int) $open->get('id'));
        $this->createAcl((int) $closed->get('id'), principal: 1, contextKey: 'web');
        $this->createAcl((int) $open->get('id'), principal: 0, contextKey: 'web');

        self::assertTrue(
            $this->isProductVisibleViaFilter((int) $product->get('id'), 'web'),
            'OR across groups: anonymous grant on any membership keeps product visible'
        );
    }

    public function testApplySqlUsesConnectionQuotedFqcn(): void
    {
        $quoted = $this->modx->quote(modUserGroup::class);
        self::assertIsString($quoted);
        self::assertStringContainsString('MODX', $quoted);

        $sql = CatalogResourceGroupVisibility::buildNotExistsSql(
            $this->modx->getTableName(modResourceGroupResource::class),
            $this->modx->getTableName(modAccessResourceGroup::class),
            'msProduct',
            $this->modx->quote('web'),
            $quoted,
        );

        self::assertStringContainsString('principal_class` = ' . $quoted, $sql);
        // MySQL quote() doubles backslashes so the literal survives the server parser.
        $dbtype = strtolower((string) ($this->modx->config['dbtype'] ?? ''));
        if (str_contains($dbtype, 'mysql')) {
            self::assertStringContainsString('\\\\', $quoted);
        }
    }

    private function createPublishedProduct(string $alias): msProduct
    {
        /** @var msProduct $product */
        $product = $this->modx->newObject(msProduct::class);
        $product->fromArray([
            'pagetitle' => 'TB RG ' . $alias,
            'alias' => $alias,
            'published' => true,
            'deleted' => false,
            'class_key' => msProduct::class,
            'context_key' => 'web',
            'parent' => 0,
            'template' => 0,
        ]);
        self::assertTrue($product->save(), 'Failed to save msProduct for RG visibility test');

        return $product;
    }

    private function createResourceGroup(string $name): modResourceGroup
    {
        /** @var modResourceGroup $group */
        $group = $this->modx->newObject(modResourceGroup::class);
        $group->set('name', $name);
        self::assertTrue($group->save(), 'Failed to save modResourceGroup');

        return $group;
    }

    private function linkProductToGroup(int $productId, int $groupId): void
    {
        /** @var modResourceGroupResource $link */
        $link = $this->modx->newObject(modResourceGroupResource::class);
        $link->fromArray([
            'document' => $productId,
            'document_group' => $groupId,
        ], '', true);
        self::assertTrue($link->save(), 'Failed to save modResourceGroupResource');
    }

    private function createAcl(int $targetGroupId, int $principal, string $contextKey): void
    {
        /** @var modAccessResourceGroup $acl */
        $acl = $this->modx->newObject(modAccessResourceGroup::class);
        $acl->fromArray([
            'target' => $targetGroupId,
            'principal_class' => modUserGroup::class,
            'principal' => $principal,
            'authority' => 9999,
            'policy' => 0,
            'context_key' => $contextKey,
        ], '', true);
        self::assertTrue($acl->save(), 'Failed to save modAccessResourceGroup');
        self::assertSame(
            modUserGroup::class,
            (string) $acl->get('principal_class'),
            'ACL must persist FQCN principal_class like MODX 3 processors'
        );
    }

    private function isProductVisibleViaFilter(int $productId, string $contextKey): bool
    {
        $c = $this->modx->newQuery(msProduct::class);
        $c->where([
            'id' => $productId,
            'class_key' => msProduct::class,
            'published' => 1,
            'deleted' => 0,
        ]);
        (new CatalogResourceGroupVisibility($this->modx))->apply($c, 'msProduct', $contextKey);

        return $this->modx->getObject(msProduct::class, $c) !== null;
    }
}
