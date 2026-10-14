<?php

declare(strict_types=1);

use MODX\Revolution\modAccessContext;
use MODX\Revolution\modAccessPermission;
use MODX\Revolution\modAccessPolicy;
use MODX\Revolution\modAccessPolicyTemplate;
use MODX\Revolution\modUserGroup;
use MODX\Revolution\modX;
use xPDO\Transport\xPDOTransport;

/**
 * Выдаёт права MiniShop3 существующим политикам доступа (#893).
 *
 * Без этого резолвера права компонента не попадают ни в одну политику: они
 * существуют только в miniShopManagerPolicy, которая никому не назначена.
 * Любой администратор без sudo упирается в «Access denied. Required
 * permission: msproduct_save» и не может сохранить товар или категорию.
 *
 * Права раздаются по признаку, а не по имени группы — так накрываются и
 * кастомные админские политики, которых на боевых сайтах хватает:
 *
 *   - политикам с save_document — ежедневная работа магазина
 *     (товары, категории, галерея, заказы);
 *   - политикам с settings — настройки магазина (редкая и опасная работа).
 *
 * @var xPDOTransport $transport
 * @var array<string, mixed> $options
 */
if (!($transport instanceof xPDOTransport) || !$transport->xpdo) {
    return false;
}

$modx = $transport->xpdo;
$action = $options[xPDOTransport::PACKAGE_ACTION] ?? null;
if (!in_array($action, [xPDOTransport::ACTION_INSTALL, xPDOTransport::ACTION_UPGRADE], true)) {
    return true;
}

/** Ежедневная работа магазина: выдаётся везде, где разрешено сохранять ресурсы. */
$dailyPermissions = [
    'mscategory_save',
    'msproduct_save',
    'msproduct_publish',
    'msproduct_delete',
    'msproductfile_save',
    'msproductfile_generate',
    'msproductfile_list',
    'msorder_save',
    'msorder_view',
    'msorder_list',
    'msorder_remove',
];

/** Настройки магазина: выдаются только там, где есть доступ к системным настройкам. */
$settingsPermissions = [
    'mssetting_save',
    'mssetting_view',
    'mssetting_list',
];

$allPermissions = array_merge($dailyPermissions, $settingsPermissions);

/**
 * Права должны присутствовать в шаблоне политики, иначе администратор не
 * увидит их в списке и не сможет переключить вручную.
 */
$adminTemplate = $modx->getObject(modAccessPolicyTemplate::class, ['name' => 'AdministratorTemplate']);
if ($adminTemplate instanceof modAccessPolicyTemplate) {
    $templateId = (int) $adminTemplate->get('id');
    $added = 0;

    foreach ($allPermissions as $name) {
        $exists = $modx->getCount(modAccessPermission::class, [
            'template' => $templateId,
            'name' => $name,
        ]);
        if ($exists) {
            continue;
        }

        $permission = $modx->newObject(modAccessPermission::class);
        $permission->fromArray([
            'template' => $templateId,
            'name' => $name,
            'description' => 'perm.' . $name . '_desc',
            'value' => true,
        ]);
        if ($permission->save()) {
            $added++;
        }
    }

    if ($added) {
        $modx->log(modX::LOG_LEVEL_INFO, "[MiniShop3] Added {$added} permissions to AdministratorTemplate");
    }
} else {
    $modx->log(modX::LOG_LEVEL_WARN, '[MiniShop3] AdministratorTemplate not found; permissions not registered');
}

/**
 * Раздача прав политикам. Уже присутствующие ключи не трогаем: администратор
 * мог отключить право намеренно, и обновление не должно это переигрывать.
 */
$touched = [];

foreach ($modx->getIterator(modAccessPolicy::class) as $policy) {
    /** @var modAccessPolicy $policy */
    $data = $policy->get('data');
    if (!is_array($data)) {
        $data = json_decode((string) $data, true);
    }
    if (!is_array($data) || $data === []) {
        continue;
    }

    $grant = [];
    if (!empty($data['save_document'])) {
        $grant = $dailyPermissions;
    }
    if (!empty($data['settings'])) {
        $grant = array_merge($grant, $settingsPermissions);
    }
    if ($grant === []) {
        continue;
    }

    $changed = false;
    foreach ($grant as $name) {
        if (array_key_exists($name, $data)) {
            continue;
        }
        $data[$name] = true;
        $changed = true;
    }

    if (!$changed) {
        continue;
    }

    $policy->set('data', $data);
    if ($policy->save()) {
        $touched[] = (string) $policy->get('name');
        continue;
    }

    $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] Failed to update policy ' . $policy->get('name'));
}

if ($touched) {
    $modx->log(
        modX::LOG_LEVEL_INFO,
        '[MiniShop3] Shop permissions granted to policies: ' . implode(', ', $touched)
    );
}

/**
 * Группа для менеджеров магазина: человеку нужен магазин, но не админские
 * права. Группа создаётся пустой — пользователей в неё добавляют сами.
 */
$shopPolicy = $modx->getObject(modAccessPolicy::class, ['name' => 'miniShopManagerPolicy']);
if ($shopPolicy instanceof modAccessPolicy) {
    $groupName = 'MiniShop3 Managers';
    $group = $modx->getObject(modUserGroup::class, ['name' => $groupName]);

    if (!$group instanceof modUserGroup) {
        $group = $modx->newObject(modUserGroup::class);
        $group->set('name', $groupName);
        if (!$group->save()) {
            $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] Failed to create user group ' . $groupName);
            $group = null;
        } else {
            $modx->log(modX::LOG_LEVEL_INFO, '[MiniShop3] Created user group ' . $groupName);
        }
    }

    if ($group instanceof modUserGroup) {
        $hasAccess = $modx->getCount(modAccessContext::class, [
            'target' => 'mgr',
            'principal_class' => modUserGroup::class,
            'principal' => (int) $group->get('id'),
            'policy' => (int) $shopPolicy->get('id'),
        ]);

        if (!$hasAccess) {
            $access = $modx->newObject(modAccessContext::class);
            $access->fromArray([
                'target' => 'mgr',
                'principal_class' => modUserGroup::class,
                'principal' => (int) $group->get('id'),
                'authority' => 0,
                'policy' => (int) $shopPolicy->get('id'),
            ]);
            if ($access->save()) {
                $modx->log(
                    modX::LOG_LEVEL_INFO,
                    '[MiniShop3] Linked miniShopManagerPolicy to ' . $groupName . ' on mgr context'
                );
            } else {
                $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] Failed to link policy to ' . $groupName);
            }
        }
    }
}

/** Без сброса кэша правки политик не вступят в силу до ручной очистки. */
$modx->getCacheManager()->refresh([
    'system_settings' => [],
    'default' => [],
]);

return true;
