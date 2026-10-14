<?php

/**
 * Regression #893: права MiniShop3 должны достаться группам с доступом в админку.
 *
 * Без резолвера права лежат только в miniShopManagerPolicy, которая никому не
 * назначена, и администратор без sudo не может сохранить товар или категорию.
 *
 * Запуск: php tests/AccessGrantResolverTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$repoRoot = dirname(__DIR__, 4);

$resolverPath = $repoRoot . '/_build/resolvers/resolver_12_access_permissions.php';
if (!is_readable($resolverPath)) {
    $fail('resolver_12_access_permissions.php missing');
}

$src = file_get_contents($resolverPath);
if ($src === false) {
    $fail('cannot read resolver_12_access_permissions.php');
}

// Существующие сайты получают права только если резолвер отрабатывает и на обновлении.
if (!str_contains($src, 'ACTION_INSTALL') || !str_contains($src, 'ACTION_UPGRADE')) {
    $fail('resolver must run on both ACTION_INSTALL and ACTION_UPGRADE');
}

// Политика выдаётся группам через доступ к контексту mgr.
if (!str_contains($src, 'modAccessContext')) {
    $fail('resolver must grant the policy through modAccessContext');
}
if (!str_contains($src, "'target' => 'mgr'")) {
    $fail('resolver must target the mgr context');
}

// Имя политики должно совпадать с тем, что объявлено в конфиге: иначе резолвер
// молча не найдёт её и права не достанутся никому.
$policyConfig = $repoRoot . '/core/components/minishop3/config/manager_access_policy.php';
if (!is_readable($policyConfig)) {
    $fail('manager_access_policy.php missing');
}

/** @var array<string, array<string, mixed>> $definitions */
$definitions = require $policyConfig;
$policyNames = array_keys($definitions);
if ($policyNames === []) {
    $fail('manager_access_policy.php declares no policies');
}

foreach ($policyNames as $policyName) {
    if (!str_contains($src, (string) $policyName)) {
        $fail("resolver must reference policy '{$policyName}' declared in manager_access_policy.php");
    }
}

// Чужие политики резолвер не правит: права достаются через назначение своей
// политики, а не дописыванием ключей в Administrator и соседние.
if (preg_match('/->set\(\s*[\'"]data[\'"]/', $src)) {
    $fail('resolver must not rewrite data of foreign access policies');
}

// Повторная установка не должна плодить записи доступа.
if (!str_contains($src, 'getCount')) {
    $fail('resolver must check for an existing access row before inserting (idempotency)');
}

// Права пользователя живут в сессии и перечитываются только после
// flushPermissions(): иначе залогиненный администратор их не увидит.
if (!str_contains($src, 'flushPermissions')) {
    $fail('resolver must call flushPermissions() so active sessions reload access');
}

fwrite(STDOUT, "OK AccessGrantResolverTest\n");
exit(0);
