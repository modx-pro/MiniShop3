<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Inventory;

use MiniShop3\Services\Inventory\InventoryKey;
use MiniShop3\Services\Inventory\OrderInventoryCoordinator;
use MiniShop3\Services\Inventory\ProductStockInventory;
use MiniShop3\Tests\RecordingMsOrder;
use MiniShop3\Tests\Support\InMemoryInventoryStockStore;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Regression #1001: variant keys split the ledger per SKU.
 *
 * Without addons every line resolves to a product key (variantKey = null).
 * An addon answering `msOnInventoryResolveKey` splits one product into
 * separate ledger rows; core product stock is not decremented for variants.
 */
final class InventoryVariantKeyTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
        require_once dirname(__DIR__, 3) . '/RecordingMsOrder.php';
    }

    public function testInventoryKeyNormalizesVariant(): void
    {
        self::assertNull((new InventoryKey(7, ''))->variantKey);
        self::assertNull((new InventoryKey(7, '  '))->variantKey);
        self::assertSame('sku-1', (new InventoryKey(7, 'sku-1'))->variantKey);
        self::assertSame('7', (new InventoryKey(7))->identity());
        self::assertSame('7|sku-1', (new InventoryKey(7, 'sku-1'))->identity());
        self::assertSame('', (new InventoryKey(7))->ledgerVariantKey());
        self::assertTrue((new InventoryKey(7, 'sku-1'))->isVariant());
        self::assertFalse((new InventoryKey(7))->isVariant());
    }

    public function testInMemoryStoreKeepsVariantRowsSeparate(): void
    {
        $store = new InMemoryInventoryStockStore();
        $red = new InventoryKey(7, 'sku-red');
        $blue = new InventoryKey(7, 'sku-blue');
        $product = new InventoryKey(7);

        self::assertTrue($store->insertReservation(40, $red, 1.0, 'reserved'));
        self::assertTrue($store->insertReservation(40, $blue, 3.0, 'reserved'));
        self::assertTrue($store->insertReservation(40, $product, 2.0, 'reserved'));
        self::assertFalse($store->insertReservation(40, $red, 9.0, 'reserved'));

        self::assertSame(1.0, $store->findReservation(40, $red)['qty']);
        self::assertSame(3.0, $store->findReservation(40, $blue)['qty']);
        self::assertSame(2.0, $store->findReservation(40, $product)['qty']);
        self::assertCount(3, $store->listReservations(40));
    }

    public function testVariantReserveDoesNotTouchCoreStock(): void
    {
        $store = new InMemoryInventoryStockStore();
        $store->seedStock(7, 0);
        // Variants borrow no product stock: availability must come from SKU addon.
        $inventory = new ProductStockInventory($store, $this->ms3WithAvailability(5.0));
        $key = new InventoryKey(7, 'sku-red');

        $inventory->reserve($key, 5.0, new \MiniShop3\Services\Inventory\InventoryContext(40), false);

        self::assertSame(0.0, $store->getAvailable(new InventoryKey(7)));
        $rows = $store->listReservations(40);
        self::assertCount(1, $rows);
        self::assertSame('sku-red', $rows[0]['variant_key']);
        self::assertSame(5.0, $rows[0]['qty']);
    }

    /**
     * Variant stock comes from `msOnInventoryAvailable` returnedValues.
     */
    private function ms3WithAvailability(float $available): \MiniShop3\MiniShop3
    {
        $modx = new class ($available) extends modX {
            public $event;

            public function __construct(private float $available)
            {
                parent::__construct();
            }

            public function invokeEvent($eventName, array $params = [])
            {
                $value = $this->available;
                $this->event = new class ($value) {
                    public $returnedValues;

                    public function __construct(float $available)
                    {
                        $this->returnedValues = ['available' => $available];
                    }
                };

                return [true];
            }
        };
        $ms3 = $this->getMockBuilder(\MiniShop3\MiniShop3::class)
            ->disableOriginalConstructor()
            ->getMock();
        $ms3->modx = $modx;

        return $ms3;
    }
}
