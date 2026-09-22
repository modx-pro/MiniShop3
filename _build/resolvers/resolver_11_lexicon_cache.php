<?php

/**
 * Resolver: clear MiniShop3 lexicon topic cache and arm one-shot heal (#758 / #766).
 *
 * Prevents a one-shot English fallback (getFileTopic miss during copy) from
 * sticking under a non-en language key until the site cache is wiped by hand.
 * Sets ms3_lexicon_cache_heal_pending so the next manager request compares
 * cached topics to getFileTopic() and drops poisoned entries, then clears the flag.
 */

use MODX\Revolution\modSystemSetting;
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

$pendingKey = 'ms3_lexicon_cache_heal_pending';
/** @var modSystemSetting|null $setting */
$setting = $modx->getObject(modSystemSetting::class, ['key' => $pendingKey]);
if (!$setting) {
    $setting = $modx->newObject(modSystemSetting::class);
    $setting->fromArray([
        'key' => $pendingKey,
        'namespace' => 'minishop3',
        'area' => 'ms3_main',
        'xtype' => 'combo-boolean',
        'value' => '1',
    ], '', true, true);
} else {
    $setting->set('value', '1');
}

if ($setting->save()) {
    $modx->setOption($pendingKey, true);
    if ($modx->cacheManager) {
        $modx->cacheManager->refresh(['system_settings' => []]);
    }
    $modx->log(modX::LOG_LEVEL_INFO, '[MiniShop3] Armed lexicon cache heal pending (#766)');
} else {
    $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] Failed to arm ' . $pendingKey);
}

return true;
