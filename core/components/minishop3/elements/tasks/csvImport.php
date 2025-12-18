<?php

use MiniShop3\Utils\ImportCSV;

/** @var modX $modx */
/** @var sFileTask $task */
/** @var sTaskRun $run */
/** @var array $scriptProperties */

$importCSV = new ImportCSV($modx);
$result = $importCSV->process($scriptProperties);

if (!is_array($result) || empty($result['success'])) {
    $errorMessage = is_array($result) && !empty($result['message'])
        ? $result['message']
        : 'CSV import error';
    $run->addError($errorMessage);
}
