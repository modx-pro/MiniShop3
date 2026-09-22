<?php

/**
 * Drop stale non-English MiniShop3 lexicon topic caches that hold English strings (#758 / #766).
 *
 * MODX falls back to en when getFileTopic() misses, then caches that array under the
 * requested language key. Later loads keep English strings while other topics stay translated.
 *
 * Heal runs only while system setting ms3_lexicon_cache_heal_pending is truthy (set on
 * install/upgrade). One pass inspects every non-English lexicon language directory (not just
 * the current manager_language), then clears the flag so hot manager paths stay cheap.
 *
 * Call before prepareLanguage() / early on manager pages so lang.js.php and API lexicon
 * loads rebuild from file + DB instead of a poisoned English snapshot.
 */

declare(strict_types=1);

use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modX;
use xPDO\Cache\xPDOCacheManager;
use xPDO\xPDO;

const MS3_LEXICON_CACHE_HEAL_PENDING_SETTING = 'ms3_lexicon_cache_heal_pending';

/**
 * True when a cached topic looks like an English snapshot under a non-English language key.
 *
 * Compares per-key against getFileTopic() for the requested language and English — no
 * hard-coded sentinel strings. Cold miss (non-array cache) and healthy translated/merged
 * caches return false.
 *
 * @param array<string, mixed>|null|false $cached
 * @param array<string, mixed>|null|false $fromFile Topic for the manager language
 * @param array<string, mixed>|null|false $enFile   Topic for English
 */
function ms3_is_stale_lexicon_topic_cache($cached, $fromFile, $enFile): bool
{
    if (!is_array($cached) || !is_array($fromFile) || $fromFile === [] || !is_array($enFile) || $enFile === []) {
        return false;
    }

    foreach ($fromFile as $key => $translated) {
        if (!is_string($key) || !array_key_exists($key, $enFile)) {
            continue;
        }
        $english = $enFile[$key];
        if ($translated === $english) {
            continue;
        }
        if (($cached[$key] ?? null) === $english) {
            return true;
        }
    }

    return false;
}

/**
 * @return list<string>
 */
function ms3_list_minishop3_lexicon_topics(string $lexiconEnDir): array
{
    if (!is_dir($lexiconEnDir)) {
        return [];
    }

    $topics = [];
    foreach (scandir($lexiconEnDir) ?: [] as $entry) {
        if (!str_ends_with($entry, '.inc.php')) {
            continue;
        }
        $topics[] = substr($entry, 0, -strlen('.inc.php'));
    }
    sort($topics);

    return $topics;
}

/**
 * Non-English language codes that ship lexicon files under the component.
 *
 * @return list<string>
 */
function ms3_list_minishop3_non_en_lexicon_languages(string $lexiconRoot): array
{
    if (!is_dir($lexiconRoot)) {
        return [];
    }

    $langs = [];
    foreach (scandir($lexiconRoot) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === 'en') {
            continue;
        }
        if (!is_dir($lexiconRoot . '/' . $entry)) {
            continue;
        }
        $langs[] = $entry;
    }
    sort($langs);

    return $langs;
}

/**
 * @return array<string, mixed>
 */
function ms3_lexicon_topic_cache_options(modX $modx): array
{
    return [
        xPDO::OPT_CACHE_KEY => $modx->getOption(
            'cache_lexicon_topics_key',
            null,
            'lexicon_topics'
        ),
        xPDO::OPT_CACHE_HANDLER => $modx->getOption(
            'cache_lexicon_topics_handler',
            null,
            $modx->getOption(xPDO::OPT_CACHE_HANDLER)
        ),
        xPDO::OPT_CACHE_FORMAT => (int) $modx->getOption(
            'cache_lexicon_topics_format',
            null,
            $modx->getOption(xPDO::OPT_CACHE_FORMAT, null, xPDOCacheManager::CACHE_PHP)
        ),
    ];
}

/**
 * Inspect and heal topics for every listed language.
 *
 * Skips topic+lang pairs with no on-disk file. If a file exists but getFileTopic() fails,
 * the pass is incomplete and the caller must keep the pending flag.
 *
 * @param list<string> $languages
 * @param list<string> $topics
 * @param callable(string, string): bool $topicFileExists function (lang, topic): bool
 * @param callable(string, string, string): (array<string, mixed>|false) $getFileTopic
 * @param callable(string, string, string): string $getCacheKey
 * @param object{get: callable, delete: callable} $cacheManager
 * @param array<string, mixed> $cacheOptions
 * @return array{deleted: int, complete: bool}
 */
function ms3_heal_stale_lexicon_topics(
    array $languages,
    array $topics,
    callable $topicFileExists,
    callable $getFileTopic,
    callable $getCacheKey,
    object $cacheManager,
    array $cacheOptions,
    string $namespace = 'minishop3'
): array {
    $deleted = 0;
    $complete = true;

    foreach ($languages as $lang) {
        if ($lang === '' || $lang === 'en') {
            continue;
        }
        foreach ($topics as $topic) {
            if (!$topicFileExists($lang, $topic)) {
                continue;
            }

            $fromFile = $getFileTopic($lang, $namespace, $topic);
            $enFile = $getFileTopic('en', $namespace, $topic);
            if (!is_array($fromFile) || $fromFile === [] || !is_array($enFile) || $enFile === []) {
                $complete = false;
                continue;
            }

            $key = $getCacheKey($namespace, $topic, $lang);
            $cached = $cacheManager->get($key, $cacheOptions);
            if (!ms3_is_stale_lexicon_topic_cache($cached, $fromFile, $enFile)) {
                continue;
            }

            $cacheManager->delete($key, $cacheOptions);
            $deleted++;
        }
    }

    return ['deleted' => $deleted, 'complete' => $complete];
}

function ms3_option_is_truthy(mixed $value): bool
{
    return $value === true
        || $value === 1
        || $value === '1'
        || filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function ms3_is_lexicon_cache_heal_pending(modX $modx): bool
{
    return ms3_option_is_truthy($modx->getOption(MS3_LEXICON_CACHE_HEAL_PENDING_SETTING, null, false));
}

function ms3_clear_lexicon_cache_heal_pending(modX $modx): void
{
    /** @var modSystemSetting|null $setting */
    $setting = $modx->getObject(modSystemSetting::class, ['key' => MS3_LEXICON_CACHE_HEAL_PENDING_SETTING]);
    if (!$setting) {
        $modx->setOption(MS3_LEXICON_CACHE_HEAL_PENDING_SETTING, false);

        return;
    }

    if (!ms3_option_is_truthy($setting->get('value'))) {
        $modx->setOption(MS3_LEXICON_CACHE_HEAL_PENDING_SETTING, false);

        return;
    }

    $setting->set('value', '0');
    if (!$setting->save()) {
        $modx->log(
            modX::LOG_LEVEL_ERROR,
            '[MiniShop3] Failed to clear ' . MS3_LEXICON_CACHE_HEAL_PENDING_SETTING
        );

        return;
    }

    $modx->setOption(MS3_LEXICON_CACHE_HEAL_PENDING_SETTING, false);

    if (!$modx->cacheManager) {
        $modx->getCacheManager();
    }
    $modx->cacheManager?->refresh(['system_settings' => []]);
}

/**
 * One-shot heal for all non-English MiniShop3 lexicon languages/topics.
 */
function ms3_heal_stale_minishop3_lexicon_cache(modX $modx): void
{
    if (!ms3_is_lexicon_cache_heal_pending($modx)) {
        return;
    }

    if (!isset($modx->lexicon) || !is_object($modx->lexicon)) {
        return;
    }

    if (!$modx->cacheManager) {
        $modx->getCacheManager();
    }
    if (!$modx->cacheManager) {
        return;
    }

    $lexiconRoot = dirname(__DIR__) . '/lexicon';
    $topics = ms3_list_minishop3_lexicon_topics($lexiconRoot . '/en');
    if ($topics === []) {
        return;
    }

    $languages = ms3_list_minishop3_non_en_lexicon_languages($lexiconRoot);
    if ($languages === []) {
        // Package ships English only — nothing to heal.
        ms3_clear_lexicon_cache_heal_pending($modx);

        return;
    }

    $lexicon = $modx->lexicon;
    $result = ms3_heal_stale_lexicon_topics(
        $languages,
        $topics,
        static fn (string $lang, string $topic): bool => is_file(
            $lexiconRoot . '/' . $lang . '/' . $topic . '.inc.php'
        ),
        static fn (string $lang, string $namespace, string $topic) => $lexicon->getFileTopic($lang, $namespace, $topic),
        static fn (string $namespace, string $topic, string $lang) => $lexicon->getCacheKey($namespace, $topic, $lang),
        $modx->cacheManager,
        ms3_lexicon_topic_cache_options($modx)
    );

    if ($result['complete']) {
        ms3_clear_lexicon_cache_heal_pending($modx);
    }
}

/**
 * @deprecated Use ms3_heal_stale_minishop3_lexicon_cache(); kept for external callers of #758.
 */
function ms3_heal_stale_manager_lexicon_cache(modX $modx): void
{
    ms3_heal_stale_minishop3_lexicon_cache($modx);
}
