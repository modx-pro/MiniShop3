<?php
/**
 * Resolver for registering Scheduler tasks
 *
 * Registers MiniShop3 tasks in Scheduler component if it's installed.
 * Recurring tasks require Scheduler 1.8.0+ (graceful degradation for older versions).
 * Tasks are not removed on uninstall to preserve scheduled runs history.
 */

use MODX\Revolution\modX;
use MODX\Revolution\Transport\modTransportPackage;
use xPDO\Transport\xPDOTransport;

/** @var xPDOTransport $transport */
/** @var array $options */
/** @var modX $modx */

if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return false;
}

$modx = $transport->xpdo;

// Minimum Scheduler version for recurring tasks support
$minRecurringVersion = '1.8.0';

/**
 * Check if installed Scheduler version supports recurring tasks
 *
 * @param modX $modx
 * @param string $minVersion
 * @return bool
 */
$supportsRecurring = function ($modx, $minVersion) {
    // Find installed Scheduler package
    $package = $modx->getObject(modTransportPackage::class, [
        'package_name' => 'scheduler',
        'installed:IS NOT' => null,
    ]);

    if (!$package) {
        return false;
    }

    // Get version from signature (e.g., "scheduler-1.8.0-pl")
    $signature = $package->get('signature');
    if (preg_match('/scheduler-(\d+\.\d+\.\d+)/', $signature, $matches)) {
        $installedVersion = $matches[1];
        return version_compare($installedVersion, $minVersion, '>=');
    }

    return false;
};

// Task definitions
// recurring=true tasks will auto-reschedule after successful execution (requires Scheduler 1.8.0+)
$tasks = [
    [
        'reference' => 'ms3_send_notification',
        'content' => 'elements/tasks/sendNotification.php',
        'description' => 'Send queued MiniShop3 notification',
        'recurring' => false,
        'interval' => '',
    ],
    [
        'reference' => 'ms3_csv_import',
        'content' => 'elements/tasks/csvImport.php',
        'description' => 'Process CSV import for MiniShop3 products',
        'recurring' => false,
        'interval' => '',
    ],
    [
        'reference' => 'ms3_cleanup_drafts',
        'content' => 'elements/tasks/cleanupDrafts.php',
        'description' => 'Clean up old draft orders in MiniShop3',
        'recurring' => true,
        'interval' => '+1 day',
    ],
    [
        'reference' => 'ms3_cleanup_tokens',
        'content' => 'elements/tasks/cleanupTokens.php',
        'description' => 'Clean up expired customer authentication tokens',
        'recurring' => true,
        'interval' => '+1 week',
    ],
];

$success = false;

switch ($options[xPDOTransport::PACKAGE_ACTION]) {
    case xPDOTransport::ACTION_INSTALL:
    case xPDOTransport::ACTION_UPGRADE:
        // Check if Scheduler is available
        if (!$modx->services->has('scheduler')) {
            $modx->log(
                modX::LOG_LEVEL_INFO,
                '[MiniShop3] Scheduler not installed, skipping task registration. ' .
                'Install Scheduler component to enable background task processing.'
            );
            $success = true;
            break;
        }

        /** @var \Scheduler $scheduler */
        $scheduler = $modx->services->get('scheduler');

        // Check if Scheduler version supports recurring tasks
        $recurringEnabled = $supportsRecurring($modx, $minRecurringVersion);

        if (!$recurringEnabled) {
            $modx->log(
                modX::LOG_LEVEL_WARN,
                "[MiniShop3] Scheduler version < {$minRecurringVersion} detected. " .
                "Recurring tasks will be disabled. Please upgrade Scheduler for auto-rescheduling support."
            );
        }

        $registered = 0;
        $updated = 0;

        foreach ($tasks as $taskData) {
            // Disable recurring for old Scheduler versions
            $recurring = $recurringEnabled ? $taskData['recurring'] : false;
            $interval = $recurringEnabled ? $taskData['interval'] : '';

            // Check if task already exists
            $task = $scheduler->getTask('minishop3', $taskData['reference']);

            if ($task) {
                // Update existing task
                $task->set('content', $taskData['content']);
                $task->set('description', $taskData['description']);
                $task->set('recurring', $recurring);
                $task->set('interval', $interval);
                if ($task->save()) {
                    $updated++;
                }
            } else {
                // Create new task
                $task = $modx->newObject('sTask');
                $task->fromArray([
                    'class_key' => 'sFileTask',
                    'namespace' => 'minishop3',
                    'reference' => $taskData['reference'],
                    'content' => $taskData['content'],
                    'description' => $taskData['description'],
                    'recurring' => $recurring,
                    'interval' => $interval,
                ]);
                if ($task->save()) {
                    $registered++;

                    // Schedule first run for recurring tasks
                    if ($recurring && !empty($interval)) {
                        $task->schedule('+1 minute');
                        $modx->log(
                            modX::LOG_LEVEL_INFO,
                            "[MiniShop3] Scheduled first run for recurring task '{$taskData['reference']}'"
                        );
                    }
                }
            }
        }

        if ($registered > 0 || $updated > 0) {
            $modx->log(
                modX::LOG_LEVEL_INFO,
                "[MiniShop3] Scheduler tasks: {$registered} registered, {$updated} updated"
            );
        }

        // Note about recurring tasks
        if ($recurringEnabled) {
            $modx->log(
                modX::LOG_LEVEL_INFO,
                '[MiniShop3] Recurring tasks enabled: ' .
                'ms3_cleanup_drafts (daily), ms3_cleanup_tokens (weekly).'
            );
        }

        $success = true;
        break;

    case xPDOTransport::ACTION_UNINSTALL:
        // We don't remove tasks on uninstall to preserve history
        // Admin can manually remove them if needed
        $modx->log(
            modX::LOG_LEVEL_INFO,
            '[MiniShop3] Scheduler tasks were not removed. ' .
            'Remove them manually in Scheduler admin if needed.'
        );
        $success = true;
        break;
}

return $success;
