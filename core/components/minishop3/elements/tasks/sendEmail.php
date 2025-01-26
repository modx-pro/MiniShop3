<?php

/** @var modX $modx */
/** @var \sFileTask $task */
/** @var \sTaskRun $run */
/** @var array $scriptProperties */

if (empty($scriptProperties['email']) || empty($scriptProperties['subject']) || empty($scriptProperties['body'])) {
    $run->addError('empty required fields');
    $modx->log(1, '[ms3\cli\sendEmail] empty required params');
    return false;
}

/** @var MiniShop3\MiniShop3 $ms3 */
$ms3 = $modx->services->get('ms3');
$ms3->utils->sendEmail($scriptProperties['email'], $scriptProperties['subject'], $scriptProperties['body']);
