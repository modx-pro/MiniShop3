<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\Cart;

use MiniShop3\Model\msProduct;
use MiniShop3\Tests\RecordingMsOrder;
use MiniShop3\Tests\Support\OrderProductSqliteStore;
use MiniShop3\Tests\Support\SqliteDraftCartHarnessTrait;
use MiniShop3\Tests\Support\SqliteDraftCartProduct;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Level-2: Cart facade add/change/remove against SQLite-backed draft product store.
 */
final class CartFacadeDraftStoreTest extends TestCase
{
    use SqliteDraftCartHarnessTrait;

    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }
        require_once dirname(__DIR__, 2) . '/support/OrderProductSqliteStore.php';
        require_once dirname(__DIR__, 2) . '/RecordingMsOrder.php';
    }

    public function testAddChangeRemoveAgainstSqliteDraftStore(): void
    {
        $store = new OrderProductSqliteStore();
        $draft = new RecordingMsOrder(['id' => 42, 'cart_cost' => 0, 'delivery_cost' => 0, 'cost' => 0, 'weight' => 0]);
        $product = new SqliteDraftCartProduct([
            'id' => 11,
            'pagetitle' => 'Shirt',
            'price' => 25.0,
            'old_price' => 30.0,
            'weight' => 0.5,
            'class_key' => msProduct::class,
            'deleted' => 0,
            'published' => 1,
        ]);

        $cart = $this->makeSqliteDraftCart($store, $draft, $product);
        self::assertTrue($cart->initialize('web', 'tok-1'));

        $added = $cart->add(11, 2, ['color' => 'red']);
        self::assertTrue($added['success'], $added['message'] ?? '');
        self::assertArrayHasKey('last_key', $added['data']);
        $key = $added['data']['last_key'];
        self::assertSame(2, $added['data']['cart'][$key]['count']);
        self::assertSame(50.0, $added['data']['cart'][$key]['cost']);
        self::assertSame(1, $store->countAll());
        $row = $store->findOne(['order_id' => 42, 'product_key' => $key]);
        self::assertNotNull($row);
        self::assertSame(['color' => 'red'], $row['options']);
        self::assertSame(2, $row['count']);

        $changed = $cart->change($key, 3);
        self::assertTrue($changed['success'], $changed['message'] ?? '');
        self::assertSame(3, $changed['data']['cart'][$key]['count']);
        self::assertSame(75.0, $changed['data']['cart'][$key]['cost']);
        $row = $store->findOne(['order_id' => 42, 'product_key' => $key]);
        self::assertSame(3, $row['count']);
        self::assertSame(75.0, $row['cost']);

        $removed = $cart->remove($key);
        self::assertTrue($removed['success'], $removed['message'] ?? '');
        self::assertArrayNotHasKey($key, $removed['data']['cart']);
        self::assertSame(0, $store->countAll());
    }

    public function testAddRejectsInvalidCount(): void
    {
        $store = new OrderProductSqliteStore();
        $draft = new RecordingMsOrder(['id' => 42]);
        $product = new SqliteDraftCartProduct([
            'id' => 11,
            'pagetitle' => 'Shirt',
            'price' => 10.0,
            'old_price' => 0,
            'weight' => 1.0,
            'class_key' => msProduct::class,
            'deleted' => 0,
            'published' => 1,
        ]);
        $cart = $this->makeSqliteDraftCart($store, $draft, $product);
        $cart->initialize('web', 'tok-1');

        $result = $cart->add(11, 0);
        self::assertFalse($result['success']);
        self::assertSame('ms3_cart_add_err_count', $result['message']);
        self::assertSame(0, $store->countAll());
    }
}
