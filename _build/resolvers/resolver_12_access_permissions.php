<?php

declare(strict_types=1);

use MODX\Revolution\modAccessContext;
use MODX\Revolution\modAccessPolicy;
use MODX\Revolution\modCacheManager;
use MODX\Revolution\modUserGroup;
use MODX\Revolution\modX;
use xPDO\Transport\xPDOTransport;

/**
 * Выдаёт политику miniShopManagerPolicy группам, которые уже работают в
 * админке (#893).
 *
 * Без этого права компонента не достаются никому: они лежат только в
 * miniShopManagerPolicy, а та не назначена ни одной группе. Любой
 * администратор без sudo упирается в «Access denied. Required permission:
 * msproduct_save» и не может сохранить товар или категорию. Первый
 * администратор сайта проблемы не видит: sudo обходит проверки прав.
 *
 * В miniShop2 это делал resolve.policy.php — выдавал политику группе
 * Administrator. При переносе в MiniShop3 часть потерялась: resolver_09
 * привязывает политику к шаблону, но никому её не назначает.
 *
 * Здесь то же самое, только политика достаётся не одной штатной группе, а
 * всем, у кого уже есть доступ к контексту mgr: на боевых сайтах
 * администраторы нередко сидят в своей группе, а не в Administrator. Кому
 * входить в админку — решает владелец сайта; мы лишь добавляем магазин
 * тем, кто уже внутри. Чужие политики при этом не меняются.
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

/** @var modAccessPolicy|null $policy */
$policy = $modx->getObject(modAccessPolicy::class, ['name' => 'miniShopManagerPolicy']);
if (!$policy instanceof modAccessPolicy) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] miniShopManagerPolicy not found; shop permissions not granted');

    return false;
}

$policyId = (int) $policy->get('id');

/**
 * Группы с доступом в админку. Ту же политику второй раз не выдаём, иначе
 * обновление плодило бы дубли.
 */
$groupIds = [];
$query = $modx->newQuery(modAccessContext::class);
$query->where([
    'target' => 'mgr',
    'principal_class' => modUserGroup::class,
    'policy:!=' => $policyId,
]);

foreach ($modx->getIterator(modAccessContext::class, $query) as $access) {
    /** @var modAccessContext $access */
    $groupId = (int) $access->get('principal');
    if ($groupId > 0 && !in_array($groupId, $groupIds, true)) {
        $groupIds[] = $groupId;
    }
}

if ($groupIds === []) {
    $modx->log(
        modX::LOG_LEVEL_WARN,
        '[MiniShop3] No user groups with mgr access found; grant miniShopManagerPolicy manually'
    );

    return true;
}

$granted = [];

foreach ($groupIds as $groupId) {
    $exists = $modx->getCount(modAccessContext::class, [
        'target' => 'mgr',
        'principal_class' => modUserGroup::class,
        'principal' => $groupId,
        'policy' => $policyId,
    ]);
    if ($exists) {
        continue;
    }

    $access = $modx->newObject(modAccessContext::class);
    $access->fromArray([
        'target' => 'mgr',
        'principal_class' => modUserGroup::class,
        'principal' => $groupId,
        // Добавочная политика: собственные права группы остаются главнее.
        'authority' => 9999,
        'policy' => $policyId,
    ]);

    if (!$access->save()) {
        $modx->log(modX::LOG_LEVEL_ERROR, "[MiniShop3] Failed to grant shop policy to user group {$groupId}");
        continue;
    }

    $group = $modx->getObject(modUserGroup::class, $groupId);
    $granted[] = $group instanceof modUserGroup ? (string) $group->get('name') : (string) $groupId;
}

if ($granted === []) {
    return true;
}

$modx->log(
    modX::LOG_LEVEL_INFO,
    '[MiniShop3] Shop policy granted to user groups: ' . implode(', ', $granted)
);

/**
 * Права пользователя лежат в его сессии и перечитываются, только когда
 * контекст помечен в session_stale. Обычный refresh() кэша сессий не
 * касается, поэтому у того, кто уже залогинен, новые права не появятся до
 * перелогина. Штатные процессоры политик (Security\Access\Policy\Create,
 * Update, Remove) по этой же причине зовут flushPermissions().
 *
 * Кэш после установки пакета сбрасывает сам процессор установки
 * (Workspace\Packages\Install), так что refresh() здесь не нужен.
 */
$cacheManager = $modx->cacheManager;
if ($cacheManager instanceof modCacheManager) {
    $cacheManager->flushPermissions();
    $modx->log(modX::LOG_LEVEL_INFO, '[MiniShop3] Permissions flushed; active sessions will reload access');
}

return true;
