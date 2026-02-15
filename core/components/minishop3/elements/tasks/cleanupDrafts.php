<?php
/**
 * Scheduler task for cleaning up old draft orders
 *
 * Deletes draft orders older than the time specified in ms3_delete_drafts_after setting.
 * Should be scheduled to run periodically (e.g., every 30 minutes).
 *
 * @var modX $modx
 * @var sFileTask $task
 * @var sTaskRun $run
 * @var array $scriptProperties
 */

use MiniShop3\Model\msOrder;
use MODX\Revolution\modX;

// Get settings
$deleteAfter = $modx->getOption('ms3_delete_drafts_after', null, '');
if (empty($deleteAfter)) {
    return true; // Nothing to do, setting is not configured
}

$threshold = strtotime($deleteAfter);
if (!$threshold) {
    $modx->log(
        modX::LOG_LEVEL_ERROR,
        "[cleanupDrafts] Invalid strtotime value: {$deleteAfter}"
    );
    return false;
}

$statusDraft = (int) $modx->getOption('ms3_status_draft', null, 1) ?: 1;
$thresholdDate = date('Y-m-d H:i:s', $threshold);

// Find and delete old drafts
$deleted = 0;
$errors = 0;

$criteria = [
    'status_id' => $statusDraft,
    'createdon:<' => $thresholdDate
];

foreach ($modx->getIterator(msOrder::class, $criteria) as $order) {
    $orderId = $order->get('id');
    if ($order->remove()) {
        $deleted++;
        $modx->log(
            modX::LOG_LEVEL_INFO,
            "[cleanupDrafts] Deleted draft order #{$orderId}"
        );
    } else {
        $errors++;
        $modx->log(
            modX::LOG_LEVEL_ERROR,
            "[cleanupDrafts] Failed to delete draft order #{$orderId}"
        );
    }
}

if ($deleted > 0 || $errors > 0) {
    $modx->log(
        modX::LOG_LEVEL_INFO,
        "[cleanupDrafts] Completed: {$deleted} deleted, {$errors} errors (threshold: {$thresholdDate})"
    );
}

return $errors === 0;
