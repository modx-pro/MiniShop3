<?php

/**
 * Drop the system events retired in #844.
 *
 * msOnBeforeUpdateOrder / msOnUpdateOrder were registered by the package but
 * had no emit site: order updates are covered by msOnBeforeSaveOrder /
 * msOnSaveOrder with mode = modSystemEvent::MODE_UPD. Removing a name from
 * _build/elements/events.php does not delete the modEvent row on install or
 * upgrade, so clean the stale rows up here. Plugin bindings are left as they are — MODX
 * behaves the same way when an event is removed in the manager.
 */

use MODX\Revolution\modEvent;
use MODX\Revolution\modX;
use xPDO\Transport\xPDOTransport;

/** @var xPDOTransport $transport */
/** @var array $options */

if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return true;
}

$action = $options[xPDOTransport::PACKAGE_ACTION] ?? null;
if (!in_array($action, [xPDOTransport::ACTION_INSTALL, xPDOTransport::ACTION_UPGRADE], true)) {
    return true;
}

$modx = $transport->xpdo;
$retiredEvents = ['msOnBeforeUpdateOrder', 'msOnUpdateOrder'];

foreach ($retiredEvents as $name) {
    $event = $modx->getObject(modEvent::class, ['name' => $name]);
    if ($event === null) {
        continue;
    }

    // Only our own event vehicles may go: miniShop2 uses similar names, and MODX
    // leaves groupname empty for user-defined events. Every MS3 build sets it.
    $groupname = (string) $event->get('groupname');
    if ($groupname !== 'MiniShop3') {
        $modx->log(
            modX::LOG_LEVEL_WARN,
            "[MiniShop3] Skipped retired system event {$name} (#844): owned by {$groupname}."
        );
        continue;
    }

    if (!$event->remove()) {
        $modx->log(modX::LOG_LEVEL_ERROR, "[MiniShop3] Failed to remove retired system event {$name} (#844).");
        continue;
    }

    $modx->log(
        modX::LOG_LEVEL_INFO,
        "[MiniShop3] Removed retired system event {$name} (#844). Use msOnSaveOrder with mode = modSystemEvent::MODE_UPD for order updates."
    );
}

return true;
