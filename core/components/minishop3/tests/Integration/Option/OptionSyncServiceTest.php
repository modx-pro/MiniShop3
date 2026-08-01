<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\Option;

use MiniShop3\Services\Option\OptionSyncService;
use MiniShop3\Tests\Support\ProductOptionSqliteXpdo;
use PHPUnit\Framework\TestCase;

/**
 * Level-2: OptionSyncService against in-memory SQLite (Fake xPDO + real PDOStatement).
 */
final class OptionSyncServiceTest extends TestCase
{
    private ProductOptionSqliteXpdo $xpdo;
    private OptionSyncService $sync;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/support/xpdo_stub.php';
        require_once dirname(__DIR__, 2) . '/support/ProductOptionSqliteXpdo.php';

        $this->xpdo = new ProductOptionSqliteXpdo();
        $this->sync = new OptionSyncService($this->xpdo);
    }

    public function testInsertSingleAndMultiValueOptions(): void
    {
        self::assertTrue($this->sync->saveProductOptions(10, [
            'color' => 'Red',
            'tags' => ['a', 'b'],
        ]));

        self::assertSame(
            [
                'color' => ['Red'],
                'tags' => ['a', 'b'],
            ],
            $this->sync->getForProduct(10)
        );
    }

    public function testUpdateSingleValueAndNoOpWhenUnchanged(): void
    {
        $this->xpdo->seed([
            ['product_id' => 10, 'key' => 'color', 'value' => 'Red'],
        ]);

        $this->sync->saveProductOptions(10, ['color' => 'Blue'], false);
        self::assertSame(['color' => ['Blue']], $this->sync->getForProduct(10));

        $before = $this->xpdo->allRows();
        $this->sync->saveProductOptions(10, ['color' => 'Blue'], false);
        self::assertSame($before, $this->xpdo->allRows());
    }

    public function testEmptyValueRemovesExistingKey(): void
    {
        $this->xpdo->seed([
            ['product_id' => 10, 'key' => 'color', 'value' => 'Red'],
            ['product_id' => 10, 'key' => 'size', 'value' => 'L'],
        ]);

        $this->sync->saveProductOptions(10, [
            'color' => '',
            'size' => 'L',
        ], false);

        self::assertSame(['size' => ['L']], $this->sync->getForProduct(10));
    }

    public function testRemoveOtherPrunesMissingKeys(): void
    {
        $this->xpdo->seed([
            ['product_id' => 10, 'key' => 'color', 'value' => 'Red'],
            ['product_id' => 10, 'key' => 'size', 'value' => 'L'],
            ['product_id' => 10, 'key' => 'material', 'value' => 'cotton'],
        ]);

        $this->sync->saveProductOptions(10, [
            'color' => 'Blue',
        ], true);

        self::assertSame(['color' => ['Blue']], $this->sync->getForProduct(10));
    }

    public function testRemoveOtherFalseKeepsOtherKeys(): void
    {
        $this->xpdo->seed([
            ['product_id' => 10, 'key' => 'color', 'value' => 'Red'],
            ['product_id' => 10, 'key' => 'size', 'value' => 'L'],
        ]);

        $this->sync->saveProductOptions(10, [
            'color' => 'Blue',
        ], false);

        self::assertSame(
            [
                'color' => ['Blue'],
                'size' => ['L'],
            ],
            $this->sync->getForProduct(10)
        );
    }

    public function testEmptyOptionsWithRemoveOtherClearsProduct(): void
    {
        $this->xpdo->seed([
            ['product_id' => 10, 'key' => 'color', 'value' => 'Red'],
        ]);

        $this->sync->saveProductOptions(10, [], true);
        self::assertSame([], $this->sync->getForProduct(10));
    }

    public function testEmptyOptionsWithoutRemoveOtherKeepsRows(): void
    {
        $this->xpdo->seed([
            ['product_id' => 10, 'key' => 'color', 'value' => 'Red'],
        ]);

        $this->sync->saveProductOptions(10, [], false);
        self::assertSame(['color' => ['Red']], $this->sync->getForProduct(10));
    }

    public function testMultiValueSyncAddsAndRemovesMembers(): void
    {
        $this->xpdo->seed([
            ['product_id' => 10, 'key' => 'tags', 'value' => 'a'],
            ['product_id' => 10, 'key' => 'tags', 'value' => 'b'],
        ]);

        $this->sync->saveProductOptions(10, [
            'tags' => ['b', 'c'],
        ], false);

        self::assertSame(['tags' => ['b', 'c']], $this->sync->getForProduct(10));
    }

    public function testCollapseMultipleExistingValuesToSingle(): void
    {
        $this->xpdo->seed([
            ['product_id' => 10, 'key' => 'color', 'value' => 'Red'],
            ['product_id' => 10, 'key' => 'color', 'value' => 'Blue'],
        ]);

        $this->sync->saveProductOptions(10, ['color' => 'Green'], false);
        self::assertSame(['color' => ['Green']], $this->sync->getForProduct(10));
    }

    public function testPrepareOptionValuesTrimsDedupesAndDropsEmpty(): void
    {
        $method = new \ReflectionMethod(OptionSyncService::class, 'prepareOptionValues');
        $method->setAccessible(true);

        self::assertNull($method->invoke($this->sync, null));
        self::assertNull($method->invoke($this->sync, ['', '  ']));
        self::assertSame(['Red', 'Blue'], array_values($method->invoke($this->sync, [' Red ', 'Blue', 'Red'])));
        self::assertSame(['solo'], array_values($method->invoke($this->sync, 'solo')));
    }

    public function testGetForProductFiltersByKeys(): void
    {
        $this->xpdo->seed([
            ['product_id' => 10, 'key' => 'color', 'value' => 'Red'],
            ['product_id' => 10, 'key' => 'size', 'value' => 'L'],
        ]);

        self::assertSame(['color' => ['Red']], $this->sync->getForProduct(10, ['color']));
    }
}
