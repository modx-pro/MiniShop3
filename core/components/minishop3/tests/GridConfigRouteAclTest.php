<?php

/**
 * Regression #417: /grid-config write ACL map (no MODX).
 *
 * Запуск: php tests/GridConfigRouteAclTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$routesFile = dirname(__DIR__) . '/config/routes/manager.php';
$src = file_get_contents($routesFile);
if ($src === false || $src === '') {
    $fail('cannot read manager.php routes');
}

$gridConfigGroupPattern =
    "/\\\$router->group\(\s*'\/grid-config'\s*,\s*function\s*\([^)]*\)\s*use\s*\([^)]*\)"
    . "\s*\{(.*?)\}\s*,\s*\[\s*new\s+PermissionMiddleware\(\s*\\\$modx\s*,\s*'([^']+)'\s*\)/s";

if (
    !preg_match_all(
        $gridConfigGroupPattern,
        $src,
        $matches,
        PREG_SET_ORDER
    )
) {
    $fail('no /grid-config route groups found');
}

/** @var array<string, string> $routePerm method|path => permission */
$routePerm = [];

foreach ($matches as $match) {
    $body = $match[1];
    $perm = $match[2];

    if (
        preg_match_all(
            "/\\\$router->(get|put|post|delete)\(\s*'([^']+)'/",
            $body,
            $routeMatches,
            PREG_SET_ORDER
        )
    ) {
        foreach ($routeMatches as $routeMatch) {
            $key = strtoupper($routeMatch[1]) . ' ' . $routeMatch[2];
            if (isset($routePerm[$key]) && $routePerm[$key] !== $perm) {
                $fail("conflicting permissions for {$key}: {$routePerm[$key]} vs {$perm}");
            }
            $routePerm[$key] = $perm;
        }
    }
}

$expected = [
    'GET /{grid_key}' => 'view_document',
    'PUT /{grid_key}' => 'mssetting_save',
    'POST /{grid_key}/field' => 'mssetting_save',
    'PUT /{grid_key}/field/{field_name}' => 'mssetting_save',
    'DELETE /{grid_key}/{field_name}' => 'mssetting_save',
];

foreach ($expected as $key => $perm) {
    if (($routePerm[$key] ?? null) !== $perm) {
        $fail("{$key} must require {$perm}, got: " . var_export($routePerm[$key] ?? null, true));
    }
}

foreach ($routePerm as $key => $perm) {
    if (!isset($expected[$key])) {
        $fail("unexpected grid-config route registered: {$key} ({$perm})");
    }
}

fwrite(STDOUT, "OK GridConfigRouteAclTest\n");
exit(0);
