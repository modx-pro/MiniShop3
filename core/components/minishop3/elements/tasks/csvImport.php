<?php

use MiniShop3\Utils\ImportCSV;

/** @var modX $modx */
/** @var sFileTask $task */
/** @var sTaskRun $run */
/** @var array $scriptProperties */

$importCSV = new ImportCSV($modx);
$result = $importCSV->process($scriptProperties);

if (!$result) {
    $run->addError('csv import error');
}
