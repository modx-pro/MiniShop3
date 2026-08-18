<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Product;

use MiniShop3\Services\Product\ProductGalleryPublicSerializer;
use PHPUnit\Framework\TestCase;

final class ProductGalleryPublicSerializerTest extends TestCase
{
    public function testSerializeKeepsPositionOrderAndPreviewFlag(): void
    {
        $items = ProductGalleryPublicSerializer::serializeGallery(
            [
                ['id' => 10, 'url' => '/a.jpg', 'name' => 'Front', 'description' => 'wide', 'position' => 0],
                ['id' => 11, 'url' => '/b.jpg', 'name' => '', 'description' => '', 'position' => 1],
                ['id' => 12, 'url' => '/c.jpg', 'name' => 'Side', 'description' => '', 'position' => 2],
            ],
            [10 => '/a_small.jpg', 12 => '/c_small.jpg'],
            'Kettle',
            12,
        );

        self::assertSame([10, 11, 12], array_column($items, 'id'));
        self::assertSame('/a_small.jpg', $items[0]['thumb']);
        self::assertSame('/b.jpg', $items[1]['thumb']);
        self::assertTrue($items[2]['is_preview']);
        self::assertFalse($items[0]['is_preview']);
        self::assertSame('Front', $items[0]['alt']);
        self::assertSame('Kettle', $items[1]['alt']);
        self::assertSame('wide', $items[0]['description']);
    }

    public function testSerializeSkipsInvalidIdsAndDoesNotUseHash(): void
    {
        $items = ProductGalleryPublicSerializer::serializeGallery(
            [
                ['id' => 0, 'url' => '/skip.jpg', 'hash' => 'abc', 'path' => '/secret'],
                ['id' => 5, 'url' => '/ok.jpg', 'hash' => 'leak', 'path' => '/fs', 'createdby' => 3, 'name' => 'Ok'],
            ],
            [],
            'Tea',
            5,
        );

        self::assertCount(1, $items);
        self::assertSame(['id', 'url', 'thumb', 'name', 'description', 'alt', 'position', 'is_preview'], array_keys($items[0]));
        self::assertArrayNotHasKey('hash', $items[0]);
        self::assertArrayNotHasKey('path', $items[0]);
        self::assertArrayNotHasKey('createdby', $items[0]);
        self::assertTrue($items[0]['is_preview']);
    }

    public function testEmptyOriginalsYieldEmptyGallery(): void
    {
        self::assertSame(
            [],
            ProductGalleryPublicSerializer::serializeGallery([], [], 'Tea', 0)
        );
    }

    public function testWhitelistItemsDropsInternalsAndNonArrays(): void
    {
        $clean = ProductGalleryPublicSerializer::whitelistItems([
            'nope',
            [
                'id' => 1,
                'url' => '/x.jpg',
                'hash' => 'abc',
                'path' => '/fs',
                'createdby' => 9,
                'alt' => 'X',
            ],
        ]);

        self::assertCount(1, $clean);
        self::assertSame(1, $clean[0]['id']);
        self::assertSame('/x.jpg', $clean[0]['url']);
        self::assertArrayNotHasKey('hash', $clean[0]);
        self::assertArrayNotHasKey('path', $clean[0]);
        self::assertArrayNotHasKey('createdby', $clean[0]);
    }
}
