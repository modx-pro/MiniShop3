<?php
/**
 * Scheduler task for cleaning up expired customer tokens
 *
 * Deletes expired tokens from ms3_customer_tokens table.
 * Should be scheduled to run periodically (e.g., every hour).
 *
 * @var modX $modx
 * @var sFileTask $task
 * @var sTaskRun $run
 * @var array $scriptProperties
 */

use MODX\Revolution\modX;

// Get AuthManager service
/** @var \MiniShop3\Services\Customer\AuthManager $authManager */
$authManager = $modx->services->get('ms3_auth_manager');

if (!$authManager) {
    $modx->log(
        modX::LOG_LEVEL_ERROR,
        "[cleanupTokens] AuthManager service not found"
    );
    return false;
}

$deletedCount = $authManager->cleanupExpiredTokens();

$modx->log(
    modX::LOG_LEVEL_INFO,
    "[cleanupTokens] Cleaned up {$deletedCount} expired tokens"
);

return true;
