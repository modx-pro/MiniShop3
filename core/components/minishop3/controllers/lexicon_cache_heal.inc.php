<?php

/**
 * Drop a stale non-English MiniShop3 lexicon topic cache that holds English strings (#758).
 *
 * MODX falls back to en when getFileTopic() misses, then caches that array under the
 * requested language key. Later page loads keep English tab titles (Product, Gallery)
 * while other topics (vue) stay translated.
 *
 * Call before prepareLanguage() so connectors/lang.js.php reads a rebuilt cache
 * (file topic + DB lexicon overrides) instead of the poisoned English snapshot.
 */

declare(strict_types=1);

use MODX\Revolution\modX;
use xPDO\xPDO;
use xPDO\Cache\xPDOCacheManager;

/** English sentinel from lexicon/en/manager.inc.php — must not live under ru/… cache. */
const MS3_MANAGER_LEXICON_EN_TAB_PRODUCT = 'Product';

/**
 * @param modX $modx
 */
function ms3_heal_stale_manager_lexicon_cache(modX $modx): void
{
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

    $fromFile = $modx->lexicon->getFileTopic($mgrLang, 'minishop3', 'manager');
    if (!is_array($fromFile) || ($fromFile['ms3_tab_product'] ?? null) === MS3_MANAGER_LEXICON_EN_TAB_PRODUCT) {
        return;
    }

    if (!$modx->cacheManager) {
        $modx->getCacheManager();
    }
    if (!$modx->cacheManager) {
        return;
    }

    $key = $modx->lexicon->getCacheKey('minishop3', 'manager', $mgrLang);
    $cacheOptions = [
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

    $cached = $modx->cacheManager->get($key, $cacheOptions);
    // Cold miss is fine — loadCache() will build from file + DB. Only delete a poisoned hit.
    if (!is_array($cached) || ($cached['ms3_tab_product'] ?? null) !== MS3_MANAGER_LEXICON_EN_TAB_PRODUCT) {
        return;
    }

    $modx->cacheManager->delete($key, $cacheOptions);
}
