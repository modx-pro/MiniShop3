<?php

/**
 * Drop a stale non-English minishop3:manager lexicon cache that holds English strings (#758 / #766).
 *
 * MODX falls back to en when getFileTopic() misses, then caches that array under the
 * requested language key. Later form loads keep English tab titles.
 *
 * A cache flag (not a system setting) is set on install/upgrade. The next non-English
 * resource create/update form compares the manager topic cache to getFileTopic() and
 * deletes a poisoned entry, then clears the flag. Healthy opens after that skip the
 * file include and the lexicon cache read.
 */

declare(strict_types=1);

use MODX\Revolution\modX;
use xPDO\Cache\xPDOCacheManager;
use xPDO\xPDO;

const MS3_LEXICON_HEAL_PENDING_KEY = 'lexicon_heal_pending';

const MS3_LEXICON_HEAL_TOPIC = 'manager';

/**
 * True when a cached topic looks like an English snapshot under a non-English language key.
 *
 * Compares per-key against getFileTopic() for the requested language and English.
 * Cold miss and healthy translated or merged caches return false.
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
 * Partition for the one-shot flag. Separate from lexicon_topics so a topic refresh
 * does not drop the marker, and the flag never appears in System Settings.
 *
 * @return array<string, mixed>
 */
function ms3_lexicon_heal_flag_options(): array
{
    return [
        xPDO::OPT_CACHE_KEY => 'minishop3',
    ];
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
 * Inspect one language and the given topics.
 *
 * If a file exists but getFileTopic() fails, the pass is incomplete and the caller
 * must keep the pending flag.
 *
 * @param list<string> $topics
 * @param callable(string, string): bool $topicFileExists function (lang, topic): bool
 * @param callable(string, string, string): (array<string, mixed>|false) $getFileTopic
 * @param callable(string, string, string): string $getCacheKey
 * @param object{get: callable, delete: callable} $cacheManager
 * @param array<string, mixed> $cacheOptions
 * @return array{deleted: int, complete: bool}
 */
function ms3_heal_stale_lexicon_topics(
    string $lang,
    array $topics,
    callable $topicFileExists,
    callable $getFileTopic,
    callable $getCacheKey,
    object $cacheManager,
    array $cacheOptions,
    string $namespace = 'minishop3'
): array {
    if ($lang === '' || $lang === 'en') {
        return ['deleted' => 0, 'complete' => false];
    }

    $deleted = 0;
    $complete = true;

    foreach ($topics as $topic) {
        if (!$topicFileExists($lang, $topic)) {
            $complete = false;
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

    return ['deleted' => $deleted, 'complete' => $complete];
}

function ms3_lexicon_heal_is_pending(mixed $value): bool
{
    return $value === 1 || $value === '1' || $value === true;
}

/**
 * One-shot heal of minishop3:manager for the current manager language.
 *
 * English sessions leave the flag in place so a later non-English resource form
 * can still drop a poisoned ru (or other) cache.
 */
function ms3_heal_stale_manager_lexicon_cache(modX $modx): void
{
    if (!$modx->cacheManager) {
        $modx->getCacheManager();
    }
    if (!$modx->cacheManager) {
        return;
    }

    $flagOptions = ms3_lexicon_heal_flag_options();
    if (!ms3_lexicon_heal_is_pending($modx->cacheManager->get(MS3_LEXICON_HEAL_PENDING_KEY, $flagOptions))) {
        return;
    }

    $mgrLang = (string) $modx->getOption(
        'manager_language',
        $_SESSION ?? [],
        (string) $modx->getOption('cultureKey', null, 'en')
    );
    if ($mgrLang === '' || $mgrLang === 'en') {
        return;
    }

    if (!isset($modx->lexicon) || !is_object($modx->lexicon)) {
        return;
    }

    $lexiconRoot = dirname(__DIR__) . '/lexicon';
    $lexicon = $modx->lexicon;
    $result = ms3_heal_stale_lexicon_topics(
        $mgrLang,
        [MS3_LEXICON_HEAL_TOPIC],
        static fn (string $lang, string $topic): bool => is_file(
            $lexiconRoot . '/' . $lang . '/' . $topic . '.inc.php'
        ),
        static fn (string $lang, string $namespace, string $topic) => $lexicon->getFileTopic($lang, $namespace, $topic),
        static fn (string $namespace, string $topic, string $lang) => $lexicon->getCacheKey($namespace, $topic, $lang),
        $modx->cacheManager,
        ms3_lexicon_topic_cache_options($modx)
    );

    if ($result['complete']) {
        $modx->cacheManager->delete(MS3_LEXICON_HEAL_PENDING_KEY, $flagOptions);
    }
}
