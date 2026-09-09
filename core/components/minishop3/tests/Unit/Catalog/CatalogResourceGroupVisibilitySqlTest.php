<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Catalog;

use MiniShop3\Services\Catalog\CatalogResourceGroupVisibility;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Execute anonymous RG visibility SQL against in-memory tables (#666 review).
 *
 * Cases: no group; group without ACL; ACL for another user group only;
 * explicit anonymous (principal=0) grant; multi-group OR (restricted + anon).
 */
final class CatalogResourceGroupVisibilitySqlTest extends TestCase
{
    private PDO $pdo;

    private string $predicate;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE msProduct (id INTEGER PRIMARY KEY)');
        $this->pdo->exec(
            'CREATE TABLE document_groups (
                document INTEGER NOT NULL,
                document_group INTEGER NOT NULL
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE access_resource_groups (
                target INTEGER NOT NULL,
                principal_class TEXT NOT NULL,
                principal INTEGER NOT NULL,
                context_key TEXT NULL
            )'
        );

        $sql = CatalogResourceGroupVisibility::buildNotExistsSql(
            'document_groups',
            'access_resource_groups',
            'msProduct',
            "'web'",
        );
        $this->predicate = str_replace('`', '', $sql);
    }

    public function testResourceWithoutGroupIsVisible(): void
    {
        $this->seedProduct(1);
        self::assertTrue($this->isVisible(1));
    }

    public function testGroupWithoutAclIsVisible(): void
    {
        $this->seedProduct(2);
        $this->link(2, 10);
        self::assertTrue($this->isVisible(2));
    }

    public function testGroupWithForeignUserGroupAclIsHidden(): void
    {
        $this->seedProduct(3);
        $this->link(3, 20);
        $this->acl(20, 1, 'web');
        self::assertFalse($this->isVisible(3));
    }

    public function testExplicitAnonymousGrantKeepsResourceVisible(): void
    {
        $this->seedProduct(4);
        $this->link(4, 30);
        $this->acl(30, 1, 'web');
        $this->acl(30, 0, 'web');
        self::assertTrue($this->isVisible(4));
    }

    public function testOnlyAnonymousGrantIsVisible(): void
    {
        $this->seedProduct(5);
        $this->link(5, 40);
        $this->acl(40, 0, 'web');
        self::assertTrue($this->isVisible(5));
    }

    public function testMultiGroupRestrictedPlusAnonymousIsVisible(): void
    {
        // Resource in closed group A and open (anon) group B — core OR semantics (#666).
        $this->seedProduct(6);
        $this->link(6, 50);
        $this->link(6, 60);
        $this->acl(50, 1, 'web');
        $this->acl(60, 0, 'web');
        self::assertTrue($this->isVisible(6));
    }

    public function testMultiGroupTwoRestrictedIsHidden(): void
    {
        $this->seedProduct(7);
        $this->link(7, 70);
        $this->link(7, 80);
        $this->acl(70, 1, 'web');
        $this->acl(80, 2, 'web');
        self::assertFalse($this->isVisible(7));
    }

    public function testGeneratedSqlMentionsAnonymousPrincipalAndClass(): void
    {
        $sql = CatalogResourceGroupVisibility::buildNotExistsSql(
            '`dg`',
            '`arg`',
            'msProduct',
            "'web'",
        );
        self::assertStringContainsString('principal` <> 0', $sql);
        self::assertStringContainsString('principal` = 0', $sql);
        self::assertStringContainsString('principal_class` IN (', $sql);
        self::assertStringContainsString('modUserGroup', $sql);
        self::assertStringContainsString('dg_anon.`document`', $sql);
        self::assertStringContainsString('NOT EXISTS', $sql);
    }

    private function seedProduct(int $id): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO msProduct (id) VALUES (?)');
        $stmt->execute([$id]);
    }

    private function link(int $documentId, int $groupId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO document_groups (document, document_group) VALUES (?, ?)'
        );
        $stmt->execute([$documentId, $groupId]);
    }

    private function acl(int $target, int $principal, ?string $contextKey): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO access_resource_groups (target, principal_class, principal, context_key)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$target, 'MODX\\Revolution\\modUserGroup', $principal, $contextKey]);
    }

    private function isVisible(int $id): bool
    {
        $sql = 'SELECT COUNT(*) FROM msProduct WHERE id = ' . $id . ' AND ' . $this->predicate;
        $count = (int) $this->pdo->query($sql)->fetchColumn();

        return $count === 1;
    }
}
