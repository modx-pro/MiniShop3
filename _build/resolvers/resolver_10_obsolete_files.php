<?php

/**
 * Remove leftover Extra files that ACTION_UPGRADE does not delete (#704, #688).
 *
 * File vehicles only copy the new tree. Connector still autoloads processors
 * that remain on disk after they left the package.
 */

use MiniShop3\Utils\ObsoletePackageFiles;
use MODX\Revolution\modX;
use xPDO\Transport\xPDOTransport;

/** @var xPDOTransport $transport */
/** @var array $options */

if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return true;
}

$modx = $transport->xpdo;
$action = $options[xPDOTransport::PACKAGE_ACTION] ?? null;
if (!in_array($action, [xPDOTransport::ACTION_INSTALL, xPDOTransport::ACTION_UPGRADE], true)) {
    return true;
}

$coreRoot = MODX_CORE_PATH . 'components/minishop3/';
$config = $coreRoot . 'config/obsolete_package_files.php';
$helper = $coreRoot . 'src/Utils/ObsoletePackageFiles.php';

if (!is_file($config) || !is_file($helper)) {
    $modx->log(modX::LOG_LEVEL_WARN, '[MiniShop3] Obsolete file list is missing, skip leftover purge.');

    return true;
}

require_once $helper;

$list = ObsoletePackageFiles::load($config);
$targets = [
    'core' => $coreRoot,
    'assets' => MODX_ASSETS_PATH . 'components/minishop3/',
];

$assetsUrl = MODX_ASSETS_URL . 'components/minishop3/';
$frontendAssets = (string) $modx->getOption('ms3_frontend_assets', null, '');
$frontendPlaceholders = [
    'jsUrl' => $assetsUrl . 'js/',
    'cssUrl' => $assetsUrl . 'css/',
    'assetsUrl' => $assetsUrl,
];

foreach ($targets as $bucket => $root) {
    $keep = [];
    if ($bucket === 'assets' && $frontendAssets !== '') {
        foreach ($list['assets'] as $relative) {
            if (ObsoletePackageFiles::isReferencedByFrontendAssets(
                $relative,
                $frontendAssets,
                $frontendPlaceholders,
            )) {
                $keep[] = $relative;
            }
        }
    }

    $result = ObsoletePackageFiles::purge($root, $list[$bucket], $keep);
    foreach ($result['removed'] as $relative) {
        $modx->log(modX::LOG_LEVEL_INFO, "[MiniShop3] Removed leftover {$bucket} file: {$relative}");
    }
    foreach ($result['kept'] as $relative) {
        $modx->log(
            modX::LOG_LEVEL_WARN,
            "[MiniShop3] Kept leftover {$bucket} file still listed in ms3_frontend_assets: {$relative}"
        );
    }
    foreach ($result['rejected'] as $relative) {
        $modx->log(modX::LOG_LEVEL_WARN, "[MiniShop3] Rejected leftover {$bucket} path: {$relative}");
    }
}

return true;
