<?php

use MiniShop3\Services\Product\Import\ProductImportService;

/** @var \MODX\Revolution\modX $modx */
/** @var sFileTask $task */
/** @var sTaskRun $run */
/** @var array $scriptProperties */

/** @var ProductImportService $importService */
$importService = $modx->services->get('ms3_product_import');
$result = $importService->process($scriptProperties);

if (!is_array($result) || empty($result['success'])) {
    $errorMessage = is_array($result) && !empty($result['message'])
        ? $result['message']
        : 'CSV import error';
    $run->addError($errorMessage);
}
