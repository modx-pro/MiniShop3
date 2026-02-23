<?php

use MODX\Revolution\modX;
use xPDO\Transport\xPDOTransport;

/** @var xPDOTransport $transport */
/** @var array $options */
/** @var modX $modx */
if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return false;
}

$modx = $transport->xpdo;
$success = true;

switch ($options[xPDOTransport::PACKAGE_ACTION] ?? null) {
    case xPDOTransport::ACTION_UNINSTALL:
        $prefix = $modx->getOption('table_prefix', null, 'modx_');
        $table = $prefix . 'site_content';
        $modResourceClassKey = 'MODX\\Revolution\\modResource';
        $msCategoryClassKey = 'MiniShop3\\Model\\msCategory';
        $msProductClassKey = 'MiniShop3\\Model\\msProduct';

        $sql = "UPDATE {$table} SET class_key = :target WHERE class_key IN (:msCategory, :msProduct)";
        $stmt = $modx->prepare($sql);
        if ($stmt) {
            $stmt->bindValue(':target', $modResourceClassKey);
            $stmt->bindValue(':msCategory', $msCategoryClassKey);
            $stmt->bindValue(':msProduct', $msProductClassKey);
            if ($stmt->execute()) {
                $count = $stmt->rowCount();
                $modx->log(modX::LOG_LEVEL_INFO, "[MiniShop3] Converted {$count} resources to modResource");
            }
            $stmt->closeCursor();
        } else {
            $modx->log(modX::LOG_LEVEL_WARN, '[MiniShop3] Could not prepare statement to convert resources');
        }

        if ($modx->getCacheManager()) {
            $modx->cacheManager->refresh([
                'elements' => ['plugins' => []],
            ]);
            $modx->log(modX::LOG_LEVEL_INFO, '[MiniShop3] Plugin cache cleared');
        }
        break;
}

return $success;
