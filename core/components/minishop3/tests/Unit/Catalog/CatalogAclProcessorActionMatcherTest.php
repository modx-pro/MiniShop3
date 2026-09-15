<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Catalog;

use MiniShop3\Services\Catalog\CatalogAclProcessorActionMatcher;
use MODX\Revolution\modAccessResourceGroup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CatalogAclProcessorActionMatcherTest extends TestCase
{
    #[DataProvider('mutatingActionsProvider')]
    public function testMatchesDedicatedMutatingActions(string $action): void
    {
        self::assertTrue(CatalogAclProcessorActionMatcher::matches($action, []));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function mutatingActionsProvider(): iterable
    {
        foreach ([
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
        ] as $action) {
            yield $action => [$action];
        }
    }

    #[DataProvider('ignoredActionsProvider')]
    public function testIgnoresListAndUnrelatedActions(string $action, array $params): void
    {
        self::assertFalse(CatalogAclProcessorActionMatcher::matches($action, $params));
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function ignoredActionsProvider(): iterable
    {
        yield 'empty action' => ['', []];
        yield 'resource group get list' => ['Security/ResourceGroup/GetList', []];
        yield 'acl get list' => ['Security/Access/GetList', ['type' => modAccessResourceGroup::class]];
        yield 'context acl add' => ['Security/Access/AddAcl', ['type' => 'MODX\\Revolution\\modAccessContext']];
        yield 'user group get list' => ['Security/Group/GetList', []];
    }

    public function testMatchesGenericAclWhenTypeIsResourceGroup(): void
    {
        self::assertTrue(CatalogAclProcessorActionMatcher::matches('Security/Access/AddAcl', [
            'type' => modAccessResourceGroup::class,
        ]));
        self::assertTrue(CatalogAclProcessorActionMatcher::matches('Security/Access/RemoveAcl', [
            'type' => '\\' . modAccessResourceGroup::class,
        ]));
        self::assertTrue(CatalogAclProcessorActionMatcher::matches('Security/Access/UpdateAcl', [
            'type' => 'modAccessResourceGroup',
        ]));
    }
}
