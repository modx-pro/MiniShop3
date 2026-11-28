<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * #991 review contract: ms3_orders.customer_id has an FK to ms3_customers.id,
 * so guest orders must be NULL (never 0) and the migration must never silently
 * detach historical orders or pass the constraint name as a column to Phinx.
 *
 * Source assertions are used because the unit suite has no MySQL/modX harness.
 */
final class OrderCustomerFkContractTest extends TestCase
{
    private const MIGRATION = 'migrations/20261115120000_add_fk_order_customer.php';

    private static function source(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function testModelMapDeclaresNullableCustomerIdWithNullDefault(): void
    {
        $map = self::source('src/Model/mysql/msOrder.php');

        self::assertStringContainsString("'customer_id' => null,", $map);
        self::assertStringNotContainsString("'customer_id' => 0,", $map);
        self::assertMatchesRegularExpression(
            "/'customer_id'\s*=>\s*\[[\s\S]*?'null'\s*=>\s*true,[\s\S]*?'default'\s*=>\s*null,/",
            $map
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: list<string>}>
     */
    public static function orderWriteFiles(): iterable
    {
        yield 'submit handler' => [
            'src/Services/Order/OrderSubmitHandler.php',
            ["!empty(\$customerId) ? (int) \$customerId : null"],
        ];
        yield 'manager create/update' => [
            'src/Services/Order/ManagerOrderMutationService.php',
            ["!empty(\$params['customer_id']) && (int) \$params['customer_id'] > 0", '$customerId === null'],
        ];
        yield 'programmatic orders' => [
            'src/Services/Order/ProgrammaticOrderService.php',
            ["\$input['customer_id'] ?? null"],
        ];
        yield 'drafts' => [
            'src/Services/Order/OrderDraftManager.php',
            ["!empty(\$data['customer_id']) && (int) \$data['customer_id'] > 0"],
        ];
    }

    /**
     * @param list<string> $requiredSnippets
     */
    #[DataProvider('orderWriteFiles')]
    public function testOrderWritesNeverUseZeroSentinel(string $relativePath, array $requiredSnippets): void
    {
        $source = self::source($relativePath);

        self::assertDoesNotMatchRegularExpression(
            "/['\"]customer_id['\"]\s*=>\s*0\b/",
            $source,
            $relativePath . ' writes customer_id = 0'
        );
        self::assertDoesNotMatchRegularExpression(
            "/set\(\s*['\"]customer_id['\"]\s*,\s*0\s*\)/",
            $source,
            $relativePath . ' sets customer_id to 0'
        );
        self::assertDoesNotMatchRegularExpression(
            "/\\$(data|input|params)\[\s*['\"]customer_id['\"]\s*\]\s*\?\?\s*0/",
            $source,
            $relativePath . ' defaults customer_id to 0'
        );

        foreach ($requiredSnippets as $snippet) {
            self::assertStringContainsString($snippet, $source, $relativePath . ' must normalize guests to NULL');
        }
    }

    public function testMigrationGuardsForeignKeyAndUsesRestrict(): void
    {
        $migration = self::source(self::MIGRATION);

        self::assertStringContainsString('declare(strict_types=1);', $migration);
        self::assertStringContainsString('final class AddFkOrderCustomer', $migration);
        self::assertStringContainsString('public function up(): void', $migration);
        self::assertStringContainsString('public function down(): void', $migration);
        self::assertSame(2, substr_count($migration, "hasForeignKey('customer_id', self::CONSTRAINT)"));
        self::assertStringContainsString("'delete' => 'RESTRICT'", $migration);
        self::assertStringNotContainsString("'delete' => 'SET NULL'", $migration);
        self::assertStringContainsString("dropForeignKey('customer_id', self::CONSTRAINT)", $migration);
        self::assertDoesNotMatchRegularExpression(
            "/dropForeignKey\(\s*['\"]fk_orders_customer['\"]\s*\)/",
            $migration,
            'constraint name must not be passed as the column argument'
        );
        self::assertStringContainsString('ALTER TABLE `{$orders}` MODIFY COLUMN `customer_id`', $migration);
        self::assertSame(0, preg_match('/[ \t]+$/m', $migration), 'no trailing whitespace');
        self::assertStringEndsWith("\n", $migration);
    }

    public function testMigrationReportsOrphansInsteadOfDetachingThem(): void
    {
        $migration = self::source(self::MIGRATION);

        self::assertStringContainsString('LEFT JOIN `{$customers}`', $migration);
        self::assertStringContainsString('AND c.`id` IS NULL', $migration);
        self::assertStringContainsString('throw new RuntimeException', $migration);
        self::assertStringContainsString('orphan ms3_orders.customer_id values must be fixed first', $migration);
        self::assertStringNotContainsString('SET `customer_id` = NULL WHERE `customer_id` IS NOT NULL', $migration);
        self::assertStringContainsString('NULLing legacy ms3_orders.customer_id=0', $migration);
        self::assertStringContainsString('NULLed customer_id values are not restored', $migration);
    }

    public function testSchemaAndGeneratedMapAgreeOnNullableCustomerId(): void
    {
        $schema = self::source('schema/minishop3.mysql.schema.xml');

        self::assertStringContainsString(
            '<field key="customer_id" dbtype="int" precision="10" attributes="unsigned" phptype="integer" null="true"/>',
            $schema
        );
        self::assertMatchesRegularExpression(
            '/<index alias="customer_id"[\s\S]{0,200}?<column key="customer_id"[^>]*null="true"\/>/',
            $schema
        );

        $map = self::source('src/Model/mysql/msOrder.php');
        self::assertMatchesRegularExpression(
            "/'customer_id'\s*=>\s*\[[\s\S]*?'null'\s*=>\s*true,[\s\S]*?'default'\s*=>\s*null,/",
            $map
        );
        self::assertMatchesRegularExpression(
            "/'customer_id'\s*=>\s*\[[\s\S]*?'collation'\s*=>\s*'A',[\s\S]*?'null'\s*=>\s*true,/",
            $map
        );
    }
}
