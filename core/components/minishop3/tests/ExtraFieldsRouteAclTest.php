<?php

/**
 * Regression #613: /extra-fields read vs write ACL map (no MODX).
 *
 * Запуск: php tests/ExtraFieldsRouteAclTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

/**
 * @return list<array{body: string, perm: string|null}>
 */
$extractRouteGroups = static function (string $src, string $path): array {
    $needle = "\$router->group('{$path}'";
    $groups = [];
    $offset = 0;
    $len = strlen($src);

    while (($pos = strpos($src, $needle, $offset)) !== false) {
        $open = strpos($src, 'function ($router)', $pos);
        if ($open === false) {
            break;
        }
        $braceStart = strpos($src, '{', $open);
        if ($braceStart === false) {
            break;
        }

        $depth = 0;
        $closedAt = null;
        for ($i = $braceStart; $i < $len; $i++) {
            $ch = $src[$i];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $closedAt = $i;
                    break;
                }
            }
        }

        if ($closedAt === null) {
            break;
        }

        $body = substr($src, $braceStart + 1, $closedAt - $braceStart - 1);
        $perm = null;
        if (
            preg_match(
                '/^\}\s*,\s*\[\s*new\s+PermissionMiddleware\(\s*\$modx\s*,\s*\'([^\']+)\'\s*\)/',
                substr($src, $closedAt, 200),
                $permMatch
            )
        ) {
            $perm = $permMatch[1];
        }

        $groups[] = ['body' => $body, 'perm' => $perm];
        $offset = $closedAt + 1;
    }

    return $groups;
};

$routesFile = dirname(__DIR__) . '/config/routes/manager.php';
$src = file_get_contents($routesFile);
if ($src === false || $src === '') {
    $fail('cannot read manager.php routes');
}

$groups = $extractRouteGroups($src, '/extra-fields');
if (count($groups) !== 2) {
    $fail('expected two /extra-fields route groups (read + write), got ' . count($groups));
}

/** @var array<string, string|null> $routePerm */
$routePerm = [];

foreach ($groups as $group) {
    if (
        preg_match_all(
            "/\\\$router->(get|put|post|delete)\(\s*'([^']*)'/",
            $group['body'],
            $routeMatches,
            PREG_SET_ORDER
        )
    ) {
        foreach ($routeMatches as $routeMatch) {
            $key = strtoupper($routeMatch[1]) . ' ' . $routeMatch[2];
            $routePerm[$key] = $group['perm'];
        }
    }
}

$expected = [
    'GET ' => null,
    'GET /{id}' => null,
    'POST ' => 'mssetting_save',
    'PUT /{id}' => 'mssetting_save',
    'DELETE /{id}' => 'mssetting_save',
];

foreach ($expected as $key => $perm) {
    if (!array_key_exists($key, $routePerm)) {
        $fail("missing route {$key}");
    }
    if ($routePerm[$key] !== $perm) {
        $fail("{$key} ACL mismatch");
    }
}

foreach ($routePerm as $key => $perm) {
    if (!array_key_exists($key, $expected)) {
        $fail("unexpected extra-fields route registered: {$key}");
    }
}

fwrite(STDOUT, "OK ExtraFieldsRouteAclTest\n");
exit(0);
