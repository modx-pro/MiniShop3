<?php
/**
 * Cron script for cleaning up expired customer tokens
 *
 * Deletes expired tokens from ms3_customer_tokens table.
 * Recommended to run once per hour.
 *
 * Crontab setup:
 * ```
 * # Clean up tokens every hour
 * 0 * * * * /usr/bin/php8.3 /path/to/core/components/minishop3/cron/cleanup-tokens.php
 * ```
 *
 * Or via MODX system settings (scheduler addon).
 *
 * @package MiniShop3
 */
if (!defined('MODX_CORE_PATH')) {
    $depth = dirname(__FILE__, 5);
    define('MODX_CORE_PATH', $depth . '/');
}
require_once MODX_CORE_PATH . 'bootstrap.php';

use MODX\Revolution\modX;

$modx = new modX();
$modx->initialize('web');
$modx->getService('error', 'error.modError');

$startTime = microtime(true);

try {
    /** @var \MiniShop3\Services\Customer\AuthManager $authManager */
    $authManager = $modx->services->get('ms3_auth_manager');

    if (!$authManager) {
        $modx->log(
            modX::LOG_LEVEL_ERROR,
            "[Cron: cleanup-tokens] AuthManager service not found"
        );
        exit(1);
    }

    $deletedCount = $authManager->cleanupExpiredTokens();

    $duration = round((microtime(true) - $startTime) * 1000, 2);

    $modx->log(
        modX::LOG_LEVEL_INFO,
        "[Cron: cleanup-tokens] Cleaned up {$deletedCount} expired tokens in {$duration}ms"
    );

    echo "Success: Deleted {$deletedCount} expired tokens\n";
    exit(0);

} catch (Exception $e) {
    $modx->log(
        modX::LOG_LEVEL_ERROR,
        "[Cron: cleanup-tokens] Error: " . $e->getMessage()
    );

    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
