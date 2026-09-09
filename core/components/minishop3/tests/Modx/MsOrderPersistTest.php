<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Tests\Modx\Support\ExtraTestCase;

final class MsOrderPersistTest extends ExtraTestCase
{
    public function testOrderAndProductPersist(): void
    {
        $order = $this->modx->newObject(msOrder::class);
        $order->fromArray([
            'num' => 'TB-ORDER-1',
            'cost' => 150.0,
            'cart_cost' => 150.0,
            'context' => 'web',
        ]);

        self::assertTrue($order->save());
        $this->assertObjectExists(msOrder::class, ['num' => 'TB-ORDER-1']);

        $product = $this->modx->newObject(msOrderProduct::class);
        $product->fromArray([
            'order_id' => $order->get('id'),
            'product_id' => 0,
            'product_key' => substr(md5('tb-order-1-widget'), 0, 32),
            'name' => 'Widget',
            'count' => 2,
            'price' => 75.0,
            'cost' => 150.0,
        ]);

        self::assertTrue($product->save());
        $this->assertObjectExists(msOrderProduct::class, [
            'order_id' => $order->get('id'),
            'name' => 'Widget',
        ]);
    }
}
