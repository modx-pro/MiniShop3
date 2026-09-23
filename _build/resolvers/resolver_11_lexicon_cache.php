<?php

/**
 * Resolver: clear MiniShop3 lexicon topic cache and arm a one-shot heal (#758 / #766).
 *
 * The marker lives in the minishop3 cache partition, not in System Settings, so it
 * is not a checkbox in the manager. The next non-English resource form compares
 * minishop3:manager to getFileTopic() and drops a poisoned English snapshot.
 */

use MODX\Revolution\modX;
use xPDO\Transport\xPDOTransport;
use xPDO\xPDO;

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

if (!$modx->cacheManager) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] Cannot arm lexicon cache heal: cache manager missing');

    return true;
}

$modx->cacheManager->refresh([
    'lexicon_topics' => [],
]);
$modx->log(modX::LOG_LEVEL_INFO, '[MiniShop3] Cleared lexicon topic cache (#758)');

$pending = 1;
$armed = $modx->cacheManager->set('lexicon_heal_pending', $pending, 0, [
    xPDO::OPT_CACHE_KEY => 'minishop3',
]);
if ($armed) {
    $modx->log(modX::LOG_LEVEL_INFO, '[MiniShop3] Armed lexicon cache heal pending (#766)');
} else {
    $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] Failed to arm lexicon_heal_pending');
}

return true;
