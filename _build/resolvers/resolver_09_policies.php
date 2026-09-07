<?php

declare(strict_types=1);

use MODX\Revolution\modAccessPolicy;
use MODX\Revolution\modAccessPolicyTemplate;
use MODX\Revolution\modX;
use xPDO\Transport\xPDOTransport;

/** @var xPDOTransport $transport */
/** @var array<string, mixed> $options */
if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return false;
}

$modx = $transport->xpdo;
$action = $options[xPDOTransport::PACKAGE_ACTION] ?? null;
if (!in_array($action, [xPDOTransport::ACTION_INSTALL, xPDOTransport::ACTION_UPGRADE], true)) {
    return true;
}

$definitionsFile = MODX_CORE_PATH . 'components/minishop3/config/manager_access_policy.php';
if (!is_readable($definitionsFile)) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] manager_access_policy.php not found');

    return false;
}

/** @var array<string, array<string, mixed>> $definitions */
$definitions = require $definitionsFile;
$template = $modx->getObject(modAccessPolicyTemplate::class, ['name' => 'miniShopManagerPolicyTemplate']);
if (!$template instanceof modAccessPolicyTemplate) {
    $modx->log(modX::LOG_LEVEL_WARN, '[MiniShop3] miniShopManagerPolicyTemplate not found; skip policy repair');

    return true;
}

$templateId = (int) $template->get('id');

foreach ($definitions as $name => $data) {
    /** @var modAccessPolicy|null $policy */
    $policy = $modx->getObject(modAccessPolicy::class, ['name' => $name]);
    if ($policy instanceof modAccessPolicy) {
        if ((int) $policy->get('template') !== $templateId) {
            $policy->set('template', $templateId);
            if (!$policy->save()) {
                $modx->log(modX::LOG_LEVEL_ERROR, "[MiniShop3] Failed to link policy {$name} to template");

                return false;
            }
            $modx->log(modX::LOG_LEVEL_INFO, "[MiniShop3] Linked existing policy {$name} to template");
        }
        // Existing policy data is left untouched by this resolver (no fromArray overwrite).
        // Note: transport update.policies=true may still rewrite policy data on upgrade via xPDOObjectVehicle.
        continue;
    }

    $payload = $data;
    if (isset($payload['data']) && is_array($payload['data'])) {
        $payload['data'] = json_encode($payload['data']);
    }

    $policy = $modx->newObject(modAccessPolicy::class);
    $policy->fromArray(array_merge([
        'name' => $name,
        'lexicon' => 'minishop3:permissions',
        'template' => $templateId,
    ], $payload), '', true, true);

    if ($policy->save()) {
        $modx->log(modX::LOG_LEVEL_INFO, "[MiniShop3] Created access policy {$name}");
        continue;
    }

    $modx->log(modX::LOG_LEVEL_ERROR, "[MiniShop3] Failed to create access policy {$name}");

    return false;
}

return true;
