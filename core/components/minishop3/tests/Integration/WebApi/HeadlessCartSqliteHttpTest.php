<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\WebApi;

use MiniShop3\Model\msProduct;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Tests\Integration\WebApi\Support\JourneyProductCatalog;
use MiniShop3\Tests\RecordingMsOrder;
use MiniShop3\Tests\Support\OrderProductSqliteStore;
use MiniShop3\Tests\Support\SqliteDraftCartHarnessTrait;
use MiniShop3\Tests\Support\SqliteDraftCartProduct;

/**
 * Phase B: cart add/change/change-option/get through Router with SQLite draft store (#574).
 */
final class HeadlessCartSqliteHttpTest extends WebApiTestCase
{
    use SqliteDraftCartHarnessTrait;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/support/OrderProductSqliteStore.php';
        require_once dirname(__DIR__, 2) . '/RecordingMsOrder.php';
    }

    public function testCartMutationsPersistViaSqliteThroughHttpEnvelope(): void
    {
        $store = new OrderProductSqliteStore();
        $draft = new RecordingMsOrder([
            'id' => 42,
            'cart_cost' => 0,
            'delivery_cost' => 0,
            'cost' => 0,
            'weight' => 0,
        ]);
        $product = new SqliteDraftCartProduct([
            'id' => JourneyProductCatalog::FIXTURE_PRODUCT_ID,
            'pagetitle' => 'Shirt',
            'price' => 25.0,
            'old_price' => 30.0,
            'weight' => 0.5,
            'class_key' => msProduct::class,
            'deleted' => 0,
            'published' => 1,
        ]);

        $this->modx->ms3->cart = $this->makeSqliteDraftCart($store, $draft, $product);
        $this->modx->journeyTokens->putToken('sqlite-http-token', 0);

        $add = $this->dispatch(
            'POST',
            '/api/v1/cart/add',
            [],
            [
                'id' => JourneyProductCatalog::FIXTURE_PRODUCT_ID,
                'count' => 2,
                'options' => ['color' => 'red'],
            ],
            [],
            'sqlite-http-token'
        );
        self::assertSame(HttpStatus::OK, $add['status'], $add['message']);
        self::assertTrue($add['success']);
        $key = (string) ($add['data']['last_key'] ?? '');
        self::assertNotSame('', $key);
        self::assertSame(1, $store->countAll());

        $change = $this->dispatch(
            'POST',
            '/api/v1/cart/change',
            [],
            ['product_key' => $key, 'count' => 3],
            [],
            'sqlite-http-token'
        );
        self::assertSame(HttpStatus::OK, $change['status'], $change['message']);
        self::assertTrue($change['success']);
        $row = $store->findOne(['order_id' => 42, 'product_key' => $key]);
        self::assertNotNull($row);
        self::assertSame(3, $row['count']);

        // change-option needs Product relation (getOne); covered on JourneyCart HTTP in Phase A.
        $get = $this->dispatch('GET', '/api/v1/cart/get', [], [], [], 'sqlite-http-token');
        self::assertSame(HttpStatus::OK, $get['status']);
        self::assertTrue($get['success']);
        self::assertSame(3, $get['data']['status']['total_count'] ?? null);
    }
}
