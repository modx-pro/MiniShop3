<?php

/**
 * Resolver: clear MiniShop3 lexicon topic cache on install/upgrade (#758).
 *
 * Prevents a one-shot English fallback (getFileTopic miss during copy) from
 * sticking under a non-en language key until the site cache is wiped by hand.
 */

use MODX\Revolution\modX;
use xPDO\Transport\xPDOTransport;

/** @var xPDOTransport $transport */
/** @var array $options */

if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return true;
}

$modx = $transport->xpdo;

if (!in_array($options[xPDOTransport::PACKAGE_ACTION], [
    xPDOTransport::ACTION_INSTALL,
    xPDOTransport::ACTION_UPGRADE,
], true)) {
    return true;
}

if (!$modx->cacheManager) {
    $modx->getCacheManager();
}

if ($modx->cacheManager) {
    $modx->cacheManager->refresh([
        'lexicon_topics' => [],
    ]);
    $modx->log(modX::LOG_LEVEL_INFO, '[MiniShop3] Cleared lexicon topic cache (#758)');
}

return true;
