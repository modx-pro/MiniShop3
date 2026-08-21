<?php

/**
 * Resolver: persist installed transport package version in ms3_version system setting.
 *
 * Used to detect when DB/settings were updated but component files on disk were not copied.
 * Signature parsing stays inline: this resolver must run even when component files
 * failed to copy onto disk (#622).
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

// Transport signature looks like "minishop3-1.13.0-beta1" → "1.13.0-beta1"
$packageVersion = '';
if (!empty($transport->signature)) {
    $parts = explode('-', (string)$transport->signature, 2);
    if (isset($parts[1])) {
        $packageVersion = $parts[1];
    }
}

if ($packageVersion === '') {
    $modx->log(modX::LOG_LEVEL_WARN, '[MiniShop3] Could not determine package version from transport signature.');
    return true;
}

/** @var modSystemSetting|null $setting */
$setting = $modx->getObject(modSystemSetting::class, ['key' => 'ms3_version']);
if (!$setting) {
    $setting = $modx->newObject(modSystemSetting::class);
    $setting->fromArray([
        'key' => 'ms3_version',
        'namespace' => 'minishop3',
        'area' => 'ms3_main',
        'xtype' => 'textfield',
        'value' => '',
    ], '', true, true);
}

$setting->set('value', $packageVersion);
$setting->save();

$modx->log(modX::LOG_LEVEL_INFO, '[MiniShop3] Set ms3_version system setting to ' . $packageVersion);

return true;
