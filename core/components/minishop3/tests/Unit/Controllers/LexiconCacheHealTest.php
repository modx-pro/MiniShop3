<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/controllers/lexicon_cache_heal.inc.php';

/**
 * Behavioral coverage for lexicon cache heal without a live MODX core (#766).
 */
final class LexiconCacheHealTest extends TestCase
{
    public function testPoisonedCacheDetectedWhenEnglishValuesUnderTranslatedKeys(): void
    {
        $en = ['ms3_tab_product' => 'Product', 'ms3_tab_gallery' => 'Gallery'];
        $ru = ['ms3_tab_product' => 'Товар', 'ms3_tab_gallery' => 'Галерея'];

        $this->assertTrue(ms3_is_stale_lexicon_topic_cache($en, $ru, $en));
    }

    public function testHealthyTranslatedCacheIsNotStale(): void
    {
        $en = ['ms3_tab_product' => 'Product'];
        $ru = ['ms3_tab_product' => 'Товар'];

        $this->assertFalse(ms3_is_stale_lexicon_topic_cache($ru, $ru, $en));
    }

    public function testHealthyCacheWithDbOverrideIsNotStale(): void
    {
        $en = ['ms3_tab_product' => 'Product', 'ms3_custom' => 'Custom'];
        $ru = ['ms3_tab_product' => 'Товар', 'ms3_custom' => 'Custom'];
        $cached = ['ms3_tab_product' => 'Товар', 'ms3_custom' => 'DB override'];

        $this->assertFalse(ms3_is_stale_lexicon_topic_cache($cached, $ru, $en));
    }

    public function testColdMissIsNotStale(): void
    {
        $en = ['ms3_tab_product' => 'Product'];
        $ru = ['ms3_tab_product' => 'Товар'];

        $this->assertFalse(ms3_is_stale_lexicon_topic_cache(null, $ru, $en));
        $this->assertFalse(ms3_is_stale_lexicon_topic_cache(false, $ru, $en));
    }

    public function testHealDeletesPoisonedKeepsHealthyAndColdMiss(): void
    {
        $en = ['ms3_tab_product' => 'Product'];
        $ru = ['ms3_tab_product' => 'Товар'];
        $store = [
            'lexicon/ru/minishop3/manager' => $en,
            'lexicon/ru/minishop3/product' => $ru,
        ];

        $cache = new class ($store) {
            /** @param array<string, mixed> $store */
            public function __construct(public array $store)
            {
            }

            public function get(string $key, array $options = []): mixed
            {
                return $this->store[$key] ?? false;
            }

            public function delete(string $key, array $options = []): bool
            {
                unset($this->store[$key]);

                return true;
            }
        };

        $files = [
            'ru/minishop3/manager' => $ru,
            'en/minishop3/manager' => $en,
            'ru/minishop3/product' => $ru,
            'en/minishop3/product' => $en,
            'ru/minishop3/setting' => $ru,
            'en/minishop3/setting' => $en,
        ];

        $result = ms3_heal_stale_lexicon_topics(
            'ru',
            ['manager'],
            static fn (string $lang, string $topic): bool => $topic === 'manager',
            static function (string $lang, string $namespace, string $topic) use ($files) {
                return $files["{$lang}/{$namespace}/{$topic}"] ?? false;
            },
            static fn (string $namespace, string $topic, string $lang): string => "lexicon/{$lang}/{$namespace}/{$topic}",
            $cache,
            []
        );

        $this->assertTrue($result['complete']);
        $this->assertSame(1, $result['deleted']);
        $this->assertArrayNotHasKey('lexicon/ru/minishop3/manager', $cache->store);
        $this->assertSame($ru, $cache->store['lexicon/ru/minishop3/product']);
        $this->assertArrayNotHasKey('lexicon/ru/minishop3/setting', $cache->store);
    }

    public function testHealPassIncompleteWhenFileExistsButGetFileTopicFails(): void
    {
        $cache = new class {
            public function get(string $key, array $options = []): mixed
            {
                return ['ms3_tab_product' => 'Product'];
            }

            public function delete(string $key, array $options = []): bool
            {
                return true;
            }
        };

        $result = ms3_heal_stale_lexicon_topics(
            'ru',
            ['manager'],
            static fn (): bool => true,
            static fn (): false => false,
            static fn (): string => 'lexicon/ru/minishop3/manager',
            $cache,
            []
        );

        $this->assertFalse($result['complete']);
        $this->assertSame(0, $result['deleted']);
    }

    public function testMissingManagerFileKeepsPassIncomplete(): void
    {
        $cache = new class {
            public function get(string $key, array $options = []): mixed
            {
                return false;
            }

            public function delete(string $key, array $options = []): bool
            {
                return true;
            }
        };

        $result = ms3_heal_stale_lexicon_topics(
            'ru',
            ['manager'],
            static fn (): bool => false,
            static fn (): false => false,
            static fn (): string => 'lexicon/ru/minishop3/manager',
            $cache,
            []
        );

        $this->assertFalse($result['complete']);
        $this->assertSame(0, $result['deleted']);
    }

    public function testEnglishLanguageDoesNotCountAsComplete(): void
    {
        $cache = new class {
            public function get(string $key, array $options = []): mixed
            {
                return false;
            }

            public function delete(string $key, array $options = []): bool
            {
                return true;
            }
        };

        $result = ms3_heal_stale_lexicon_topics(
            'en',
            ['manager'],
            static fn (): bool => true,
            static fn (): false => false,
            static fn (): string => 'unused',
            $cache,
            []
        );

        $this->assertFalse($result['complete']);
        $this->assertSame(0, $result['deleted']);
    }

    public function testPendingFlagValues(): void
    {
        $this->assertTrue(ms3_lexicon_heal_is_pending(1));
        $this->assertTrue(ms3_lexicon_heal_is_pending('1'));
        $this->assertTrue(ms3_lexicon_heal_is_pending(true));
        $this->assertFalse(ms3_lexicon_heal_is_pending(false));
        $this->assertFalse(ms3_lexicon_heal_is_pending(null));
        $this->assertFalse(ms3_lexicon_heal_is_pending(0));
    }
}
