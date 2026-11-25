<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Cart;

require_once __DIR__ . '/../../../stubs/ModxStub.php';

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Services\Cart\CartItemManager;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #862: zero and "0" are valid option values, not keys to delete.
 */
final class UpdateItemOptionsZeroTest extends TestCase
{
    public function testZeroOptionsAreKeptAndBlankStringsClearTheKey(): void
    {
        $item = new FakeCartItemForZero([
            'options' => ['floor' => 1, 'keep' => 'yes', 'comment' => 'old'],
        ]);
        $manager = $this->manager($item);

        $newKey = $manager->updateItemOptions($this->createMock(msOrder::class), 'old-key', [
            'floor' => 0,
            'room' => '0',
            'comment' => '   ',
            'keep' => null,
        ]);

        self::assertSame('new-key', $newKey);
        self::assertSame(['floor' => 0, 'room' => '0'], $manager->capturedOptions);
        self::assertTrue($item->saved);
    }

    private function manager(FakeCartItemForZero $item): CartItemManager
    {
        $modx = new class extends modX {
        };

        return new class ($modx, $this->createMock(MiniShop3::class), $item) extends CartItemManager {
            /** @var array<string, mixed> */
            public array $capturedOptions = [];

            public function __construct(modX $modx, MiniShop3 $ms3, private msOrderProduct $fakeItem)
            {
                parent::__construct($modx, $ms3);
            }

            public function getItemByKey(msOrder $draft, string $productKey): ?msOrderProduct
            {
                return $this->fakeItem;
            }

            public function generateProductKey(array $product, array $options = []): string
            {
                $this->capturedOptions = $options;

                return 'new-key';
            }
        };
    }
}

final class FakeCartItemForZero extends msOrderProduct
{
    /** @var array<string, mixed> */
    private array $fields;

    public bool $saved = false;

    /** @param array<string, mixed> $fields */
    public function __construct(array $fields)
    {
        $this->fields = $fields;
    }

    public function get($k, $format = null, $formatTemplate = null)
    {
        return $this->fields[$k] ?? null;
    }

    public function set($k, $value)
    {
        $this->fields[$k] = $value;

        return true;
    }

    public function save()
    {
        $this->saved = true;

        return true;
    }

    public function &getOne($alias, $criteria = null, $cacheFlag = true)
    {
        static $product = null;
        $product = new class {
            /** @return array<string, int> */
            public function toArray(): array
            {
                return ['id' => 1];
            }
        };

        return $product;
    }
}
