<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Seo;

use MiniShop3\Services\Seo\PublicSeoService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class PublicSeoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
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
                'pagetitle' => 'Kettle',
                'uri' => 'catalog/kettle/',
                'context_key' => 'web',
            ],
            ['context' => 'en'],
        );

        self::assertSame('https://en.example/catalog/kettle/', $out['seo']['canonical']);
    }

    /**
     * @param array<string, mixed>|null $seoPatch
     * @param array<string, string> $contextUrls
     */
    private function service(?array $seoPatch = null, array $contextUrls = []): PublicSeoService
    {
        return new PublicSeoService($this->modx($seoPatch, $contextUrls));
    }

    /**
     * @param array<string, mixed>|null $seoPatch
     * @param array<string, string> $contextUrls
     */
    private function modx(?array $seoPatch = null, array $contextUrls = []): modX
    {
        return new class ($seoPatch, $contextUrls) extends modX {
            public object $event;

            /**
             * @param array<string, mixed>|null $seoPatch
             * @param array<string, string> $contextUrls
             */
            public function __construct(
                private ?array $seoPatch,
                private array $contextUrls,
            ) {
                parent::__construct();
                $this->event = (object) ['returnedValues' => null];
            }

            public function getOption(string $key, $options = null, $default = null)
            {
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

            public function invokeEvent($eventName, array $params = [])
            {
                if ($this->seoPatch !== null) {
                    $this->event->returnedValues = ['seo' => $this->seoPatch];
                }

                return [];
            }
        };
    }
}
