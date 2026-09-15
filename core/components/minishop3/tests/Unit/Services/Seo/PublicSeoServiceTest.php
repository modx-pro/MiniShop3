<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Seo;

use MiniShop3\Services\Seo\PublicSeoService;
use MiniShop3\Services\Seo\PublicSeoTvMap;
use MODX\Revolution\modTemplateVar;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class PublicSeoServiceTest extends TestCase
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

    public function testDefaultOnAttachesSeo(): void
    {
        $out = $this->service()->maybeAttachProduct(
            [
                'pagetitle' => 'Kettle',
                'longtitle' => '',
                'uri' => 'catalog/kettle/',
                'image' => '/assets/kettle.jpg',
            ],
            [],
        );

        self::assertArrayHasKey('seo', $out);
        self::assertSame('Kettle', $out['seo']['title']);
        self::assertSame('https://shop.example/catalog/kettle/', $out['seo']['canonical']);
        self::assertSame('https://shop.example/assets/kettle.jpg', $out['seo']['og']['image']);
        self::assertSame('product', $out['seo']['og']['type']);
        self::assertSame('Kettle', $out['pagetitle']);
    }

    public function testCategoryOgTypeIsWebsite(): void
    {
        $out = $this->service()->maybeAttachCategory(['pagetitle' => 'Tea'], []);

        self::assertSame('website', $out['seo']['og']['type']);
        self::assertSame('Tea', $out['seo']['title']);
    }

    public function testIncludeSeoZeroOmitsKey(): void
    {
        $out = $this->service()->maybeAttachProduct(
            ['pagetitle' => 'Kettle'],
            ['include_seo' => 0],
        );

        self::assertArrayNotHasKey('seo', $out);
        self::assertSame('Kettle', $out['pagetitle']);
    }

    public function testHookTitlePatchMirrorsOgTitle(): void
    {
        $out = $this->service(['title' => 'Override', 'jsonld' => '{}'])->maybeAttachProduct(
            ['pagetitle' => 'Kettle', 'longtitle' => 'Electric kettle'],
            [],
        );

        self::assertSame('Override', $out['seo']['title']);
        self::assertSame('Override', $out['seo']['og']['title']);
        self::assertArrayNotHasKey('jsonld', $out['seo']);
    }

    public function testCanonicalUsesContextSiteUrl(): void
    {
        $out = $this->service(null, ['en' => 'https://en.example/'])->maybeAttachProduct(
            [
                'pagetitle' => 'Kettle',
                'uri' => 'catalog/kettle/',
                'context_key' => 'en',
            ],
            [],
        );

        self::assertSame('https://en.example/catalog/kettle/', $out['seo']['canonical']);
    }

    public function testQueryContextOverridesPayloadContext(): void
    {
        $out = $this->service(null, [
            'web' => 'https://shop.example/',
            'en' => 'https://en.example/',
        ])->maybeAttachProduct(
            [
                'id' => 0,
                'pagetitle' => 'Kettle',
                'uri' => 'catalog/kettle/',
                'context_key' => 'web',
            ],
            ['context' => 'en'],
        );

        self::assertSame('https://en.example/catalog/kettle/', $out['seo']['canonical']);
    }

    public function testCanonicalUsesMakeUrlWhenResourceIdPresent(): void
    {
        $out = $this->service(null, [], ['42@web' => 'https://shop.example/index.php?id=42'])->maybeAttachProduct(
            [
                'id' => 42,
                'pagetitle' => 'Kettle',
                'uri' => 'catalog/kettle/',
                'context_key' => 'web',
            ],
            [],
        );

        self::assertSame('https://shop.example/index.php?id=42', $out['seo']['canonical']);
    }

    public function testCanonicalFallsBackToUriWhenMakeUrlEmpty(): void
    {
        $out = $this->service(null, [], [])->maybeAttachProduct(
            [
                'id' => 42,
                'pagetitle' => 'Kettle',
                'uri' => 'catalog/kettle/',
                'context_key' => 'web',
            ],
            [],
        );

        self::assertSame('https://shop.example/catalog/kettle/', $out['seo']['canonical']);
    }

    public function testTvMapFromGetOptionAppliesOverlay(): void
    {
        $out = $this->service(null, [], [], [
            'seo_title' => 'TV meta title',
        ], '{"title":"tv.seo_title"}')->maybeAttachProduct(
            [
                'id' => 42,
                'pagetitle' => 'Kettle',
                'longtitle' => '',
            ],
            [],
        );

        self::assertSame('TV meta title', $out['seo']['title']);
    }

    public function testTvOgTitleSurvivesMirrorOgTextWhenDifferentFromTitle(): void
    {
        $out = $this->service(null, [], [], [
            'seo_title' => 'Page title',
            'og_title' => 'Social headline',
        ], '{"title":"tv.seo_title","og.title":"tv.og_title"}')->maybeAttachProduct(
            [
                'id' => 42,
                'pagetitle' => 'Kettle',
                'longtitle' => '',
            ],
            [],
        );

        self::assertSame('Page title', $out['seo']['title']);
        self::assertSame('Social headline', $out['seo']['og']['title']);
    }

    public function testRelativeTvOgImageBecomesAbsolute(): void
    {
        $out = $this->service(null, [], [], [
            'og_image' => '/assets/social.jpg',
        ], '{"og.image":"tv.og_image"}')->maybeAttachProduct(
            [
                'id' => 42,
                'pagetitle' => 'Kettle',
                'uri' => 'catalog/kettle/',
            ],
            [],
        );

        self::assertSame('https://shop.example/assets/social.jpg', $out['seo']['og']['image']);
    }

    public function testAttachSeoToProductListDefaultsToOmittingSeo(): void
    {
        $items = [['pagetitle' => 'Kettle'], ['pagetitle' => 'Mug']];
        $out = $this->service()->attachSeoToProductList($items, []);

        self::assertArrayNotHasKey('seo', $out[0]);
        self::assertArrayNotHasKey('seo', $out[1]);
    }

    public function testAttachSeoToProductListWithIncludeSeo(): void
    {
        $items = [['pagetitle' => 'Kettle']];
        $out = $this->service()->attachSeoToProductList($items, ['include_seo' => 1]);

        self::assertArrayHasKey('seo', $out[0]);
        self::assertSame('Kettle', $out[0]['seo']['title']);
    }

    public function testAttachSeoToCategoryListDefaultsToOmittingSeo(): void
    {
        $items = [['pagetitle' => 'Tea']];
        $out = $this->service()->attachSeoToCategoryList($items, []);

        self::assertArrayNotHasKey('seo', $out[0]);
    }

    public function testAttachSeoToCategoryListWithIncludeSeo(): void
    {
        $items = [['pagetitle' => 'Tea']];
        $out = $this->service()->attachSeoToCategoryList($items, ['include_seo' => 1]);

        self::assertSame('website', $out[0]['seo']['og']['type']);
    }

    public function testAttachSeoToCategoryTreeDefaultsToOmittingSeo(): void
    {
        $nodes = [
            [
                'pagetitle' => 'Root',
                'children' => [['pagetitle' => 'Child']],
            ],
        ];
        $out = $this->service()->attachSeoToCategoryTree($nodes, []);

        self::assertArrayNotHasKey('seo', $out[0]);
        self::assertArrayNotHasKey('seo', $out[0]['children'][0]);
    }

    public function testAttachSeoToCategoryTreeRecursivelyWithIncludeSeo(): void
    {
        $nodes = [
            [
                'pagetitle' => 'Root',
                'children' => [['pagetitle' => 'Child']],
            ],
        ];
        $out = $this->service()->attachSeoToCategoryTree($nodes, ['include_seo' => 1]);

        self::assertSame('Root', $out[0]['seo']['title']);
        self::assertSame('Child', $out[0]['children'][0]['seo']['title']);
    }

    /**
     * @param array<string, mixed>|null $seoPatch
     * @param array<string, string> $contextUrls
     * @param array<string, string> $makeUrls "id@context" => url
     * @param array<string, string> $tvValues tvName => rendered value
     * @param string|null $tvMapSetting raw ms3_public_seo_tv_map JSON
     */
    private function service(
        ?array $seoPatch = null,
        array $contextUrls = [],
        array $makeUrls = [],
        array $tvValues = [],
        ?string $tvMapSetting = null,
    ): PublicSeoService {
        return new PublicSeoService($this->modx($seoPatch, $contextUrls, $makeUrls, $tvValues, $tvMapSetting));
    }

    /**
     * @param array<string, mixed>|null $seoPatch
     * @param array<string, string> $contextUrls
     * @param array<string, string> $makeUrls
     * @param array<string, string> $tvValues
     */
    private function modx(
        ?array $seoPatch = null,
        array $contextUrls = [],
        array $makeUrls = [],
        array $tvValues = [],
        ?string $tvMapSetting = null,
    ): modX {
        return new class ($seoPatch, $contextUrls, $makeUrls, $tvValues, $tvMapSetting) extends modX {
            public object $event;

            /**
             * @param array<string, mixed>|null $seoPatch
             * @param array<string, string> $contextUrls
             * @param array<string, string> $makeUrls
             * @param array<string, string> $tvValues
             */
            public function __construct(
                private ?array $seoPatch,
                private array $contextUrls,
                private array $makeUrls,
                private array $tvValues,
                private ?string $tvMapSetting,
            ) {
                parent::__construct();
                $this->event = (object) ['returnedValues' => null];
            }

            public function getOption(string $key, $options = null, $default = null)
            {
                if ($key === PublicSeoTvMap::SETTING_KEY) {
                    return $this->tvMapSetting ?? $default;
                }

                return $key === 'site_url' ? 'https://shop.example/' : $default;
            }

            public function getContext($contextKey, $options = null)
            {
                $url = $this->contextUrls[$contextKey] ?? null;
                if ($url === null) {
                    return null;
                }

                return new class ($url) {
                    public function __construct(private string $url)
                    {
                    }

                    public function getOption(string $key, $options = null, $default = null)
                    {
                        return $key === 'site_url' ? $this->url : $default;
                    }
                };
            }

            public function makeUrl($id, $context = '', $args = '', $scheme = 'full', array $options = [])
            {
                $key = (string) $id . '@' . ($context !== '' ? $context : 'web');

                return $this->makeUrls[$key] ?? '';
            }

            public function invokeEvent($eventName, array $params = [])
            {
                if ($this->seoPatch !== null) {
                    $this->event->returnedValues = ['seo' => $this->seoPatch];
                }

                return [];
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
