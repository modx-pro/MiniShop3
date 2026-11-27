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

    public function testInvalidJsonLogsOneWarningWithoutChangingResult(): void
    {
        $sink = $this->logSink();
        self::assertSame([], PublicSeoTvMap::parseSettingValue('{not json', $this->modx([], $sink)));

        self::assertCount(1, $sink->logs);
        self::assertSame(modX::LOG_LEVEL_WARN, $sink->logs[0]['level']);
        self::assertStringContainsString(PublicSeoTvMap::SETTING_KEY, $sink->logs[0]['message']);
        self::assertStringContainsString('invalid JSON', $sink->logs[0]['message']);
    }

    public function testValidJsonOfWrongTypeReportsObjectRequirement(): void
    {
        $sink = $this->logSink();
        self::assertSame([], PublicSeoTvMap::parseSettingValue('5', $this->modx([], $sink)));

        self::assertCount(1, $sink->logs);
        self::assertStringContainsString('JSON object', $sink->logs[0]['message']);
        self::assertStringNotContainsString('No error', $sink->logs[0]['message']);
    }

    public function testDroppedPairsAreLoggedOnceWithReasons(): void
    {
        $sink = $this->logSink();
        $map = PublicSeoTvMap::parseSettingValue(
            '{"h1":"tv.h1","title":"","keywords":5,"og.bad":"tv.x","description":"tv.bad name","robots":"tv.robots"}',
            $this->modx([], $sink),
        );

        self::assertSame(['robots' => 'robots'], $map);
        self::assertCount(1, $sink->logs);
        self::assertSame(modX::LOG_LEVEL_WARN, $sink->logs[0]['level']);
        $message = $sink->logs[0]['message'];
        self::assertStringContainsString('h1', $message);
        self::assertStringContainsString('not allowlisted', $message);
        self::assertStringContainsString('empty key or TV reference', $message);
        self::assertStringContainsString('must be strings', $message);
        self::assertStringContainsString('invalid TV reference', $message);
    }

    public function testLogSummarizesMoreThanFiveProblemsInOneWarning(): void
    {
        $sink = $this->logSink();
        $raw = json_encode([
            'h1' => 'tv.h1',
            'keywords' => 'tv.kw',
            'foo' => 'tv.foo',
            'bar' => 'tv.bar',
            'baz' => 'tv.baz',
            'qux' => 'tv.qux',
        ], JSON_THROW_ON_ERROR);

        self::assertSame([], PublicSeoTvMap::parseSettingValue($raw, $this->modx([], $sink)));
        self::assertCount(1, $sink->logs);
        self::assertStringContainsString('+1 more', $sink->logs[0]['message']);
    }

    public function testValidMapAndEmptySettingDoNotLog(): void
    {
        $sink = $this->logSink();
        $modx = $this->modx([], $sink);

        self::assertSame(['title' => 'seo_title'], PublicSeoTvMap::parseSettingValue('{"title":"tv.seo_title"}', $modx));
        self::assertSame([], PublicSeoTvMap::parseSettingValue('', $modx));
        self::assertSame([], $sink->logs);
    }

    public function testNonStringSettingValueIsLogged(): void
    {
        $sink = $this->logSink();
        self::assertSame([], PublicSeoTvMap::parseSettingValue(['title' => 'tv.title'], $this->modx([], $sink)));
        self::assertSame([], PublicSeoTvMap::parseSettingValue('   ', $this->modx([], $sink)));

        self::assertCount(2, $sink->logs);
        self::assertStringContainsString('empty or not a string', $sink->logs[0]['message']);
        self::assertStringContainsString('empty or not a string', $sink->logs[1]['message']);
    }

    public function testMissingTvIsLoggedOncePerName(): void
    {
        $sink = $this->logSink();
        $tvMap = new PublicSeoTvMap($this->modx([], $sink));
        $seo = ['title' => 'Core'];

        $tvMap->overlay($seo, 42, ['title' => 'missing_tv']);
        $tvMap->overlay($seo, 43, ['title' => 'missing_tv']);

        self::assertCount(1, $sink->logs);
        self::assertSame(modX::LOG_LEVEL_WARN, $sink->logs[0]['level']);
        self::assertStringContainsString('missing_tv', $sink->logs[0]['message']);
    }

    private function logSink(): object
    {
        return new class {
            public array $logs = [];
        };
    }

    /**
     * @param array<string, string> $tvValues tvName => rendered value
     * @param object|null $logSink object with a public `logs` array
     */
    private function modx(array $tvValues, ?object $logSink = null): modX
    {
        return new class ($tvValues, $logSink) extends modX {
            /**
             * @param array<string, string> $tvValues
             */
            public function __construct(
                private array $tvValues,
                private ?object $logSink,
            ) {
                parent::__construct();
            }

            public function log($level, $message): void
            {
                if ($this->logSink !== null) {
                    $this->logSink->logs[] = ['level' => $level, 'message' => $message];
                }
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
