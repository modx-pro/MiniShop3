<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Seo;

use MiniShop3\Services\Seo\PublicSeoBuilder;
use PHPUnit\Framework\TestCase;

final class PublicSeoBuilderTest extends TestCase
{
    public function testAbsoluteUrlEmptyPathYieldsEmptyString(): void
    {
        self::assertSame('', PublicSeoBuilder::absoluteUrl('https://shop.example/', ''));
        self::assertSame('', PublicSeoBuilder::absoluteUrl('https://shop.example/', '  '));
    }

    public function testAbsoluteUrlJoinsWithoutDoubleSlash(): void
    {
        self::assertSame(
            'https://shop.example/catalog/kettle/',
            PublicSeoBuilder::absoluteUrl('https://shop.example/', '/catalog/kettle/'),
        );
        self::assertSame(
            'https://shop.example/catalog/kettle/',
            PublicSeoBuilder::absoluteUrl('https://shop.example', 'catalog/kettle/'),
        );
    }

    public function testAbsoluteUrlKeepsAbsoluteAndProtocolRelative(): void
    {
        self::assertSame(
            'https://cdn.example/img.jpg',
            PublicSeoBuilder::absoluteUrl('https://shop.example/', 'https://cdn.example/img.jpg'),
        );
        self::assertSame(
            '//cdn.example/img.jpg',
            PublicSeoBuilder::absoluteUrl('https://shop.example/', '//cdn.example/img.jpg'),
        );
    }

    public function testAbsoluteUrlEmptyBaseKeepsRootRelativePath(): void
    {
        self::assertSame('/catalog/kettle/', PublicSeoBuilder::absoluteUrl('', '/catalog/kettle/'));
    }

    public function testTitleFallsBackToPagetitle(): void
    {
        $seo = PublicSeoBuilder::build(
            ['pagetitle' => 'Kettle', 'longtitle' => ''],
            'https://shop.example/',
            PublicSeoBuilder::OG_TYPE_PRODUCT,
        );

        self::assertSame('Kettle', $seo['title']);
        self::assertSame('Kettle', $seo['og']['title']);
    }

    public function testPrefersLongtitleWhenSet(): void
    {
        $seo = PublicSeoBuilder::build(
            ['pagetitle' => 'Kettle', 'longtitle' => 'Electric kettle'],
            'https://shop.example/',
            PublicSeoBuilder::OG_TYPE_PRODUCT,
        );

        self::assertSame('Electric kettle', $seo['title']);
    }

    public function testDescriptionFallsBackToIntrotext(): void
    {
        $seo = PublicSeoBuilder::build(
            ['description' => '', 'introtext' => 'Short blurb'],
            'https://shop.example/',
            PublicSeoBuilder::OG_TYPE_PRODUCT,
        );

        self::assertSame('Short blurb', $seo['description']);
        self::assertSame('Short blurb', $seo['og']['description']);
    }

    public function testCanonicalJoinsSiteUrlAndUri(): void
    {
        $seo = PublicSeoBuilder::build(
            ['uri' => 'catalog/kettle/'],
            'https://shop.example/',
            PublicSeoBuilder::OG_TYPE_PRODUCT,
        );

        self::assertSame('https://shop.example/catalog/kettle/', $seo['canonical']);
    }

    public function testOgImagePrefersImageThenThumb(): void
    {
        $fromImage = PublicSeoBuilder::build(
            ['image' => '/assets/kettle.jpg', 'thumb' => '/assets/kettle_small.jpg'],
            'https://shop.example/',
            PublicSeoBuilder::OG_TYPE_PRODUCT,
        );
        self::assertSame('https://shop.example/assets/kettle.jpg', $fromImage['og']['image']);

        $fromThumb = PublicSeoBuilder::build(
            ['image' => '', 'thumb' => '/assets/kettle_small.jpg'],
            'https://shop.example/',
            PublicSeoBuilder::OG_TYPE_PRODUCT,
        );
        self::assertSame('https://shop.example/assets/kettle_small.jpg', $fromThumb['og']['image']);

        $empty = PublicSeoBuilder::build(
            ['image' => '', 'thumb' => ''],
            'https://shop.example/',
            PublicSeoBuilder::OG_TYPE_PRODUCT,
        );
        self::assertSame('', $empty['og']['image']);
    }

    public function testOgTypeAndDefaultRobots(): void
    {
        $product = PublicSeoBuilder::build([], 'https://shop.example/', PublicSeoBuilder::OG_TYPE_PRODUCT);
        self::assertSame('product', $product['og']['type']);
        self::assertSame('index,follow', $product['robots']);

        $category = PublicSeoBuilder::build([], 'https://shop.example/', PublicSeoBuilder::OG_TYPE_CATEGORY);
        self::assertSame('website', $category['og']['type']);
    }

    public function testWhitelistDropsUnknownKeys(): void
    {
        $clean = PublicSeoBuilder::whitelist([
            'title' => 'A',
            'description' => 'B',
            'canonical' => 'https://shop.example/',
            'robots' => 'index,follow',
            'jsonld' => '{"leak":true}',
            'og' => [
                'title' => 'A',
                'description' => 'B',
                'image' => '',
                'type' => 'product',
                'extra' => 'nope',
            ],
        ]);

        self::assertSame(['title', 'description', 'canonical', 'robots', 'og'], array_keys($clean));
        self::assertSame(['title', 'description', 'image', 'type'], array_keys($clean['og']));
        self::assertArrayNotHasKey('jsonld', $clean);
        self::assertArrayNotHasKey('extra', $clean['og']);
    }

    public function testWhitelistDropsNonArrayOg(): void
    {
        $clean = PublicSeoBuilder::whitelist([
            'title' => 'A',
            'og' => 'https://evil.example/',
        ]);

        self::assertSame('A', $clean['title']);
        self::assertArrayNotHasKey('og', $clean);
    }

    public function testWhitelistDropsNonScalarTitle(): void
    {
        $clean = PublicSeoBuilder::whitelist([
            'title' => ['leak'],
            'robots' => 'index,follow',
        ]);

        self::assertArrayNotHasKey('title', $clean);
        self::assertSame('index,follow', $clean['robots']);
    }
}
