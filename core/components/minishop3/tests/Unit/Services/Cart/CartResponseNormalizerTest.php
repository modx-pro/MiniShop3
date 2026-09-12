<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Cart;

use MiniShop3\Services\Cart\CartResponseNormalizer;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class CartResponseNormalizerTest extends TestCase
{
    private CartResponseNormalizer $normalizer;

    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }

        $this->normalizer = new CartResponseNormalizer(new modX());
    }

    public function testEmptyCartIsObjectAndItemsAreArray(): void
    {
        $out = $this->normalizer->normalize([
            'cart' => [],
            'status' => [],
        ]);

        self::assertInstanceOf(\stdClass::class, $out['cart']);
        self::assertSame('{}', json_encode($out['cart']));
        self::assertSame([], $out['items']);
        self::assertSame('[]', json_encode($out['items']));
        self::assertSame(0, $out['status']['total_positions']);
        self::assertSame(0, $out['status']['total_count']);
        self::assertSame(0.0, $out['status']['total_cost']);
        self::assertSame(0.0, $out['status']['total_weight']);
        self::assertSame(0.0, $out['status']['total_discount']);
    }

    public function testNonEmptyItemsMatchPositionsAndHideInternalFields(): void
    {
        $out = $this->normalizer->normalize([
            'cart' => [
                'ms-b' => [
                    'id' => 20,
                    'order_id' => 99,
                    'hash' => 'secret',
                    'product_id' => 2,
                    'name' => 'Later',
                    'count' => 1,
                    'price' => 50,
                    'cost' => 50,
                    'weight' => 0.1,
                    'options' => [],
                    'properties' => [],
                ],
                'ms-a' => [
                    'id' => 10,
                    'order_id' => 99,
                    'product_id' => 1,
                    'name' => 'First',
                    'count' => 2,
                    'price' => 100.456,
                    'cost' => 200.456,
                    'weight' => 0.1234,
                    'options' => '{"color":"red"}',
                    'properties' => [
                        'old_price' => 120,
                        'discount_price' => 20,
                        'discount_cost' => 40,
                    ],
                ],
            ],
            'status' => [
                'total_positions' => 2,
                'total_count' => 3,
                'total_cost' => 250.456,
                'total_weight' => 0.3468,
                'total_discount' => 40,
            ],
        ]);

        self::assertCount(2, $out['items']);
        self::assertSame(2, $out['status']['total_positions']);
        self::assertSame('ms-a', $out['items'][0]['product_key']);
        self::assertSame('ms-b', $out['items'][1]['product_key']);
        self::assertSame(100.46, $out['items'][0]['price']);
        self::assertSame(200.46, $out['items'][0]['cost']);
        self::assertSame(0.123, $out['items'][0]['weight']);
        self::assertSame(['color' => 'red'], $out['items'][0]['options']);
        self::assertInstanceOf(\stdClass::class, $out['items'][1]['options']);
        self::assertSame('{}', json_encode($out['items'][1]['options']));
        self::assertSame(120.0, $out['items'][0]['old_price']);
        self::assertSame(20.0, $out['items'][0]['discount_price']);
        self::assertSame(40.0, $out['items'][0]['discount_cost']);
        self::assertArrayNotHasKey('thumb', $out['items'][0]);
        self::assertArrayNotHasKey('order_id', $out['items'][0]);
        self::assertArrayNotHasKey('hash', $out['items'][0]);
        self::assertArrayNotHasKey('id', $out['items'][0]);
        self::assertIsArray($out['cart']);
        self::assertArrayHasKey('ms-a', $out['cart']);
        self::assertSame(250.46, $out['status']['total_cost']);
        self::assertSame(0.347, $out['status']['total_weight']);
        self::assertSame(40.0, $out['status']['total_discount']);
    }

    public function testProjectStatusRoundsBinaryFloatArtifacts(): void
    {
        // 0.1 + 0.2 style residue must match cart/get and order/cost merge.
        $status = CartResponseNormalizer::projectStatus([
            'total_positions' => 1,
            'total_count' => 3,
            'total_cost' => 0.1 + 0.2,
            'total_weight' => 0.1 + 0.2,
            'total_discount' => 0.1 + 0.2,
        ]);

        self::assertSame(0.3, $status['total_cost']);
        self::assertSame(0.3, $status['total_weight']);
        self::assertSame(0.3, $status['total_discount']);
        self::assertSame(
            $status,
            $this->normalizer->normalize([
                'cart' => [],
                'status' => [
                    'total_positions' => 1,
                    'total_count' => 3,
                    'total_cost' => 0.1 + 0.2,
                    'total_weight' => 0.1 + 0.2,
                    'total_discount' => 0.1 + 0.2,
                ],
            ])['status']
        );
    }

    public function testDiscountCostFallsBackFromDiscountPriceTimesCount(): void
    {
        $out = $this->normalizer->normalize([
            'cart' => [
                'ms-x' => [
                    'product_id' => 3,
                    'name' => 'Tea',
                    'count' => 2,
                    'price' => 100,
                    'cost' => 200,
                    'weight' => 0.2,
                    'properties' => '{"old_price":120,"discount_price":20}',
                ],
            ],
            'status' => [],
        ]);

        $item = $out['items'][0];
        self::assertSame(120.0, $item['old_price']);
        self::assertSame(20.0, $item['discount_price']);
        self::assertSame(40.0, $item['discount_cost']);
    }

    public function testIncludeThumbsAddsUrlOrNull(): void
    {
        $normalizer = new class (new modX()) extends CartResponseNormalizer {
            protected function lookupThumbs(array $productIds): array
            {
                return [12 => '/assets/small.jpg'];
            }
        };

        $out = $normalizer->normalize(
            [
                'cart' => [
                    'ms-12' => [
                        'id' => 1,
                        'product_id' => 12,
                        'name' => 'Tea',
                        'count' => 1,
                        'price' => 10,
                        'cost' => 10,
                        'weight' => 0,
                    ],
                    'ms-13' => [
                        'id' => 2,
                        'product_id' => 13,
                        'name' => 'Mug',
                        'count' => 1,
                        'price' => 5,
                        'cost' => 5,
                        'weight' => 0,
                    ],
                ],
                'status' => ['total_positions' => 2, 'total_count' => 2],
            ],
            true
        );

        self::assertSame('/assets/small.jpg', $out['items'][0]['thumb']);
        self::assertNull($out['items'][1]['thumb']);
    }

    public function testWithoutThumbsFlagDoesNotQuery(): void
    {
        $normalizer = new class (new modX()) extends CartResponseNormalizer {
            protected function lookupThumbs(array $productIds): array
            {
                throw new \RuntimeException('thumbs must not load without include_thumbs');
            }
        };

        $out = $normalizer->normalize([
            'cart' => [
                'ms-12' => [
                    'id' => 1,
                    'product_id' => 12,
                    'name' => 'Tea',
                    'count' => 1,
                    'price' => 10,
                    'cost' => 10,
                    'weight' => 0,
                ],
            ],
            'status' => ['total_positions' => 1, 'total_count' => 1],
        ]);

        self::assertArrayNotHasKey('thumb', $out['items'][0]);
    }
}
