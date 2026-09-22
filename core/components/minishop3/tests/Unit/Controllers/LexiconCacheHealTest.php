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
        $onDisk = [
            'ru/manager' => true,
            'ru/product' => true,
            'ru/setting' => true,
        ];

        $result = ms3_heal_stale_lexicon_topics(
            ['ru'],
            ['manager', 'product', 'setting'],
            static fn (string $lang, string $topic): bool => !empty($onDisk["{$lang}/{$topic}"]),
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
            ['ru'],
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

    public function testHealSkipsMissingOnDiskTopicWithoutMarkingIncomplete(): void
    {
        $en = ['ms3_tab_product' => 'Product'];
        $ru = ['ms3_tab_product' => 'Товар'];
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
            ['ru'],
            ['manager', 'missing'],
            static fn (string $lang, string $topic): bool => $topic === 'manager',
            static function (string $lang, string $namespace, string $topic) use ($en, $ru) {
                if ($topic !== 'manager') {
                    return false;
                }

                return $lang === 'en' ? $en : $ru;
            },
            static fn (string $namespace, string $topic, string $lang): string => "lexicon/{$lang}/{$namespace}/{$topic}",
            $cache,
            []
        );

        $this->assertTrue($result['complete']);
        $this->assertSame(0, $result['deleted']);
    }

    public function testListTopicsAndLanguagesFromDisk(): void
    {
        $root = dirname(__DIR__, 3) . '/lexicon';
        $topics = ms3_list_minishop3_lexicon_topics($root . '/en');
        $langs = ms3_list_minishop3_non_en_lexicon_languages($root);

        $this->assertContains('manager', $topics);
        $this->assertContains('product', $topics);
        $this->assertContains('setting', $topics);
        $this->assertContains('vue', $topics);
        $this->assertContains('ru', $langs);
        $this->assertNotContains('en', $langs);
    }

    public function testOptionIsTruthy(): void
    {
        $this->assertTrue(ms3_option_is_truthy(true));
        $this->assertTrue(ms3_option_is_truthy('1'));
        $this->assertTrue(ms3_option_is_truthy(1));
        $this->assertFalse(ms3_option_is_truthy(false));
        $this->assertFalse(ms3_option_is_truthy('0'));
        $this->assertFalse(ms3_option_is_truthy(0));
    }
}
