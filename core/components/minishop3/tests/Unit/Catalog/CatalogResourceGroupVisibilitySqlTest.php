<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Catalog;

use MiniShop3\Services\Catalog\CatalogResourceGroupVisibility;
use MiniShop3\Services\Catalog\CatalogSortbyQualifier;
use MODX\Revolution\modUserGroup;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Execute anonymous RG visibility SQL against in-memory tables (#666 review).
 *
 * Cases: no group; group without ACL; ACL for another user group only;
 * explicit anonymous (principal=0) grant; multi-group OR (restricted + anon);
 * restricted + membership without ACL; anon grant only in another context.
 */
final class CatalogResourceGroupVisibilitySqlTest extends TestCase
{
    private PDO $pdo;

    private string $predicate;

    private string $quotedPrincipalClass;

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

        $quoted = $this->pdo->quote(modUserGroup::class);
        self::assertIsString($quoted);
        $this->quotedPrincipalClass = $quoted;

        $sql = CatalogResourceGroupVisibility::buildNotExistsSql(
            'document_groups',
            'access_resource_groups',
            'msProduct',
            "'web'",
            $this->quotedPrincipalClass,
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

    public function testRestrictedPlusGroupWithoutAclIsHidden(): void
    {
        // Closed group A + membership in group B with no ACL → still hidden (#666 review).
        $this->seedProduct(8);
        $this->link(8, 90);
        $this->link(8, 91);
        $this->acl(90, 1, 'web');
        self::assertFalse($this->isVisible(8));
    }

    public function testAnonymousGrantInOtherContextDoesNotOpenWeb(): void
    {
        // Restricted in web; principal=0 only for mgr → hidden on web (#666 review).
        $this->seedProduct(9);
        $this->link(9, 100);
        $this->acl(100, 1, 'web');
        $this->acl(100, 0, 'mgr');
        self::assertFalse($this->isVisible(9));
    }

    public function testGeneratedSqlUsesQuotedPrincipalEquality(): void
    {
        $sql = CatalogResourceGroupVisibility::buildNotExistsSql(
            '`dg`',
            '`arg`',
            'msProduct',
            "'web'",
            $this->quotedPrincipalClass,
        );
        self::assertStringContainsString('principal` <> 0', $sql);
        self::assertStringContainsString('principal` = 0', $sql);
        self::assertStringContainsString('principal_class` = ' . $this->quotedPrincipalClass, $sql);
        self::assertStringNotContainsString('principal_class` IN (', $sql);
        self::assertStringNotContainsString("'modUserGroup'", $sql);
        self::assertStringContainsString('dg_anon.`document`', $sql);
        self::assertStringContainsString('NOT EXISTS', $sql);
    }

    public function testAllowedResourceGroupOpensClosedMembership(): void
    {
        // Closed A; customer allowed for A → visible (#669 / #677 review).
        $this->seedProduct(10);
        $this->link(10, 110);
        $this->acl(110, 1, 'web');
        self::assertFalse($this->isVisible(10));
        self::assertTrue($this->isVisibleWithAllowed(10, [110]));
    }

    public function testAllowedGroupPlusClosedOtherMembershipIsVisible(): void
    {
        // Closed A + membership in allowed F → visible for customer (#677 review).
        $this->seedProduct(11);
        $this->link(11, 120);
        $this->link(11, 121);
        $this->acl(120, 1, 'web');
        self::assertFalse($this->isVisible(11));
        self::assertTrue($this->isVisibleWithAllowed(11, [121]));
    }

    public function testAllowedGroupDoesNotOpenUnrelatedClosedResource(): void
    {
        $this->seedProduct(12);
        $this->link(12, 130);
        $this->acl(130, 1, 'web');
        self::assertFalse($this->isVisibleWithAllowed(12, [999]));
    }

    public function testSelfJoinSiteContentMakesUnqualifiedMultiColumnOrderByAmbiguous(): void
    {
        $pdo = $this->listingPdo();
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/ambiguous/i');
        $pdo->query(
            'SELECT msProduct.id FROM site_content msProduct
             INNER JOIN site_content ms3RgVisibility ON ms3RgVisibility.id = msProduct.id
             ORDER BY pagetitle DESC, publishedon'
        );
    }

    public function testQualifiedSortbyWorksWithSiteContentSelfJoinAndOrphanProduct(): void
    {
        $pdo = $this->listingPdo();
        $predicate = str_replace('`', '', CatalogResourceGroupVisibility::buildNotExistsSql(
            'document_groups',
            'access_resource_groups',
            'msProduct',
            "'web'",
            $this->quotedPrincipalClass,
        ));
        $orderBy = CatalogSortbyQualifier::qualifyUnaliasedResourceFields(
            'pagetitle DESC, publishedon',
            ['pagetitle', 'publishedon'],
        );
        self::assertSame('msProduct.pagetitle DESC, msProduct.publishedon', $orderBy);

        $rows = $pdo->query(
            'SELECT msProduct.id FROM site_content msProduct
             LEFT JOIN ms3_products Data ON Data.id = msProduct.id
             INNER JOIN site_content ms3RgVisibility
                ON ms3RgVisibility.id = msProduct.id AND ' . $predicate . '
             ORDER BY ' . $orderBy
        );
        self::assertInstanceOf(\PDOStatement::class, $rows);
        $ids = $rows->fetchAll(PDO::FETCH_COLUMN);
        // id 2 is RG-hidden; id 4 has no ms3_products row but must remain (#742 review).
        self::assertSame([4, 3, 1], $ids);
    }

    private function listingPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE site_content (
                id INTEGER PRIMARY KEY,
                pagetitle TEXT NOT NULL,
                publishedon INTEGER NOT NULL
            )'
        );
        $pdo->exec('CREATE TABLE ms3_products (id INTEGER PRIMARY KEY)');
        $pdo->exec(
            'CREATE TABLE document_groups (
                document INTEGER NOT NULL,
                document_group INTEGER NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE access_resource_groups (
                target INTEGER NOT NULL,
                principal_class TEXT NOT NULL,
                principal INTEGER NOT NULL,
                context_key TEXT NULL
            )'
        );
        $pdo->exec("INSERT INTO site_content (id, pagetitle, publishedon) VALUES
            (1, 'B', 20),
            (2, 'A', 30),
            (3, 'C', 10),
            (4, 'D', 40)");
        $pdo->exec('INSERT INTO ms3_products (id) VALUES (1), (2), (3)');
        $pdo->exec('INSERT INTO document_groups (document, document_group) VALUES (2, 20)');
        $pdo->exec(
            'INSERT INTO access_resource_groups (target, principal_class, principal, context_key)
             VALUES (20, ' . $this->quotedPrincipalClass . ', 1, \'web\')'
        );

        return $pdo;
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
        $stmt->execute([$target, modUserGroup::class, $principal, $contextKey]);
    }

    private function isVisible(int $id): bool
    {
        return $this->isVisibleWithAllowed($id, []);
    }

    /**
     * @param list<int> $allowedIds
     */
    private function isVisibleWithAllowed(int $id, array $allowedIds): bool
    {
        $sql = CatalogResourceGroupVisibility::buildVisibilitySql(
            'document_groups',
            'access_resource_groups',
            'msProduct',
            "'web'",
            $this->quotedPrincipalClass,
            $allowedIds,
        );
        $predicate = str_replace('`', '', $sql);
        $count = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM msProduct WHERE id = ' . $id . ' AND ' . $predicate
        )->fetchColumn();

        return $count === 1;
    }
}
