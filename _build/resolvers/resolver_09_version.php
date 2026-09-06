<?php

/**
 * Resolver: persist installed transport package version in ms3_version system setting.
 *
 * Used to detect when DB/settings were updated but component files on disk were not copied.
 * Uses xPDOTransport::parseSignature (MODX vendor, always available) — no MiniShop3 src
 * autoload, so this still runs after a failed file copy (#622).
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

$packageVersion = '';
if (!empty($transport->signature)) {
    [, $packageVersion] = xPDOTransport::parseSignature((string)$transport->signature);
    $packageVersion = (string)$packageVersion;
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
if (!$setting->save()) {
    $modx->log(
        modX::LOG_LEVEL_ERROR,
        '[MiniShop3] Failed to save ms3_version system setting to ' . $packageVersion
    );

    return true;
}

$modx->log(modX::LOG_LEVEL_INFO, '[MiniShop3] Set ms3_version system setting to ' . $packageVersion);

return true;
