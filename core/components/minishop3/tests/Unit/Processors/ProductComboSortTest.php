<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Processors;

use MiniShop3\Processors\Product\ProductComboSort;
use PHPUnit\Framework\TestCase;

final class ProductComboSortTest extends TestCase
{
    public function testMenuindexKeepsParentThenMenuindexOrder(): void
    {
        self::assertSame(
            ['msProduct.parent DESC, msProduct.menuindex', 'DESC'],
            ProductComboSort::resolve('menuindex', 'desc')
        );
        self::assertSame(
            ['msProduct.parent ASC, msProduct.menuindex', 'ASC'],
            ProductComboSort::resolve('menuindex', 'ASC')
        );
    }

    public function testAllowlistedColumnIsQualifiedAndBackticked(): void
    {
        self::assertSame(
            ['msProduct.`pagetitle`', 'ASC'],
            ProductComboSort::resolve('pagetitle', 'asc')
        );
        self::assertSame(
            ['msProduct.`createdon`', 'DESC'],
            ProductComboSort::resolve('createdon', 'DESC')
        );
    }

    public function testUnknownSortAndDirectionDoNotReachTheExpression(): void
    {
        $sort = 'id,(select 1)';
        $dir = 'ASC, msProduct.id';

        [$expression, $direction] = ProductComboSort::resolve($sort, $dir);

        self::assertSame(['msProduct.parent ASC, msProduct.menuindex', 'ASC'], [$expression, $direction]);
        self::assertStringNotContainsString($sort, $expression);
        self::assertStringNotContainsString($dir, $expression);
        self::assertStringNotContainsString($dir, $direction);
    }

    public function testDirectionIsCaseInsensitiveAndAnythingElseIsAsc(): void
    {
        self::assertSame(
            ['msProduct.`id`', 'DESC'],
            ProductComboSort::resolve('id', 'Desc')
        );
        self::assertSame(
            ['msProduct.`id`', 'ASC'],
            ProductComboSort::resolve('id', '')
        );
        self::assertSame(
            ['msProduct.`id`', 'ASC'],
            ProductComboSort::resolve('id', 'descending')
        );
    }

    public function testGetListDoesNotPassCallerSortIntoSql(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/Processors/Product/GetList.php');
        self::assertIsString($source);
        self::assertStringContainsString('ProductComboSort::resolve(', $source);
        self::assertStringNotContainsString('getSelectColumns(', $source);
        self::assertDoesNotMatchRegularExpression(
            "/setProperty\\('sort',\\s*'msProduct\\.parent '/",
            $source
        );
    }

    public function testXpdoSortbyKeepsMenuindexAndBacktickedColumns(): void
    {
        if (!$this->loadXpdo()) {
            self::markTestSkipped('xPDO sources are not available. Run composer stan:prepare or set MS3_XPDO_SRC.');
        }

        self::assertStringContainsString(
            'ORDER BY msProduct.parent DESC, msProduct.menuindex DESC',
            $this->xpdoSql('menuindex', 'desc')
        );
        self::assertStringContainsString(
            'ORDER BY msProduct.`pagetitle` ASC',
            $this->xpdoSql('pagetitle', 'asc')
        );
    }

    private function xpdoSql(string $sort, string $dir): string
    {
        $xpdo = new class {
            public function log($level, $message, $target = '', $def = '', $file = '', $line = ''): void
            {
            }

            public function escape($value): string
            {
                return '`' . str_replace('`', '``', (string) $value) . '`';
            }
        };

        $class = new \ReflectionClass(\xPDO\Om\mysql\xPDOQuery::class);
        $query = $class->newInstanceWithoutConstructor();
        $class->getProperty('xpdo')->setValue($query, $xpdo);
        $class->getProperty('query')->setValue($query, [
            'command' => 'SELECT',
            'distinct' => '',
            'priority' => '',
            'columns' => ['msProduct.id'],
            'from' => ['tables' => [], 'joins' => []],
            'set' => [],
            'where' => [],
            'groupby' => [],
            'having' => [],
            'sortby' => [],
            'offset' => '',
            'limit' => '',
        ]);

        [$column, $direction] = ProductComboSort::resolve($sort, $dir);
        $query->sortby($column, $direction);
        self::assertTrue($query->construct());

        return (string) $query->sql;
    }

    private function loadXpdo(): bool
    {
        if (class_exists(\xPDO\Om\mysql\xPDOQuery::class, false)) {
            return true;
        }

        $src = $this->xpdoSrc();
        if ($src === null) {
            return false;
        }

        spl_autoload_register(static function (string $class) use ($src): void {
            if (!str_starts_with($class, 'xPDO\\')) {
                return;
            }
            $path = $src . '/' . str_replace('\\', '/', $class) . '.php';
            if (is_file($path)) {
                require_once $path;
            }
        });

        return class_exists(\xPDO\Om\mysql\xPDOQuery::class);
    }

    private function xpdoSrc(): ?string
    {
        $candidates = [];
        $env = getenv('MS3_XPDO_SRC');
        if (is_string($env) && $env !== '') {
            $candidates[] = $env;
        }
        $candidates[] = dirname(__DIR__, 6) . '/.phpstan-deps/modx/core/vendor/xpdo/xpdo/src';

        foreach ($candidates as $src) {
            if (is_file($src . '/xPDO/Om/mysql/xPDOQuery.php')) {
                return $src;
            }
        }

        return null;
    }
}
