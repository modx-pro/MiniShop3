<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Seo;

use MiniShop3\Services\Seo\PublicSeoTvMap;
use MODX\Revolution\modTemplateVar;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class PublicSeoTvMapTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
        if (!class_exists(modTemplateVar::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModTemplateVarStub.php';
        }
    }

    public function testParseSettingValueAcceptsAllowlistedKeys(): void
    {
        $map = PublicSeoTvMap::parseSettingValue(
            '{"title":"tv.seo_title","robots":"tv.robots","og.image":"tv.og_image"}',
        );

        self::assertSame([
            'title' => 'seo_title',
            'robots' => 'robots',
            'og.image' => 'og_image',
        ], $map);
    }

    public function testParseSettingValueRejectsUnknownKeysAndBadJson(): void
    {
        self::assertSame([], PublicSeoTvMap::parseSettingValue('{not json'));
        self::assertSame([], PublicSeoTvMap::parseSettingValue(''));
        self::assertSame([], PublicSeoTvMap::parseSettingValue('{"jsonld":"tv.leak"}'));

        $map = PublicSeoTvMap::parseSettingValue('{"title":"tv.","robots":"tv.bad name"}');
        self::assertSame([], $map);
    }

    public function testOverlayMergesLoadedTvValues(): void
    {
        $seo = [
            'title' => 'Core',
            'description' => '',
            'canonical' => '',
            'robots' => 'index,follow',
            'og' => ['title' => 'Core', 'description' => '', 'image' => '', 'type' => 'product'],
        ];

        [$overlay] = (new PublicSeoTvMap($this->modx(['seo_title' => 'TV title'])))->overlay(
            $seo,
            42,
            ['title' => 'seo_title'],
        );

        self::assertSame('TV title', $overlay['title']);
        self::assertSame('index,follow', $overlay['robots']);
    }

    public function testOverlaySkipsEmptyTvValues(): void
    {
        $seo = [
            'title' => 'Core',
            'description' => '',
            'canonical' => '',
            'robots' => 'index,follow',
            'og' => ['title' => 'Core', 'description' => '', 'image' => '', 'type' => 'product'],
        ];

        [$overlay] = (new PublicSeoTvMap($this->modx([])))->overlay(
            $seo,
            42,
            ['title' => 'missing_tv'],
        );

        self::assertSame('Core', $overlay['title']);
    }

    public function testOverlayReturnsAppliedKeys(): void
    {
        $seo = [
            'title' => 'Core',
            'description' => '',
            'canonical' => '',
            'robots' => 'index,follow',
            'og' => ['title' => 'Core', 'description' => '', 'image' => '', 'type' => 'product'],
        ];

        [$overlay, $applied] = (new PublicSeoTvMap($this->modx([
            'seo_title' => 'TV title',
            'og_title' => 'TV og title',
        ])))->overlay(
            $seo,
            42,
            ['title' => 'seo_title', 'og.title' => 'og_title'],
        );

        self::assertSame(['title', 'og.title'], $applied);
        self::assertSame('TV og title', $overlay['og']['title']);
    }

    public function testOverlayAbsoluteUrlForCanonicalAndOgImage(): void
    {
        $seo = [
            'title' => 'Core',
            'description' => '',
            'canonical' => '',
            'robots' => 'index,follow',
            'og' => ['title' => 'Core', 'description' => '', 'image' => '', 'type' => 'product'],
        ];

        [$overlay] = (new PublicSeoTvMap($this->modx([
            'seo_canonical' => '/custom/path/',
            'og_image' => '/assets/og.jpg',
        ])))->overlay(
            $seo,
            42,
            ['canonical' => 'seo_canonical', 'og.image' => 'og_image'],
            'https://shop.example/',
        );

        self::assertSame('https://shop.example/custom/path/', $overlay['canonical']);
        self::assertSame('https://shop.example/assets/og.jpg', $overlay['og']['image']);
    }

    /**
     * @param array<string, string> $tvValues tvName => rendered value
     */
    private function modx(array $tvValues): modX
    {
        return new class ($tvValues) extends modX {
            /**
             * @param array<string, string> $tvValues
             */
            public function __construct(private array $tvValues)
            {
                parent::__construct();
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                if ($className !== modTemplateVar::class || !is_array($criteria)) {
                    return null;
                }

                $name = $criteria['name'] ?? null;
                if (!is_string($name) || !array_key_exists($name, $this->tvValues)) {
                    return null;
                }

                return new class ($name, $this->tvValues[$name]) extends modTemplateVar {
                    public function __construct(
                        private string $name,
                        private string $value,
                    ) {
                    }

                    public function renderOutput($resourceId = 0)
                    {
                        return $this->value;
                    }
                };
            }
        };
    }
}
