<?php

/**
 * Regression #415: /references requires view_document (customer PII).
 *
 * Run: php tests/ReferencesRouteAclTest.php
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

$start = strpos($src, "\$router->group('/references'");
if ($start === false) {
    $fail('/references group not found');
}

$nextGroup = strpos($src, "\$router->group('/extra-fields'", $start);
if ($nextGroup === false) {
    $fail('anchor after /references (/extra-fields) not found');
}

$block = substr($src, $start, $nextGroup - $start);

if (!str_contains($block, "searchCustomers")) {
    $fail('/references must still expose GET customers search');
}

if (
    !preg_match(
        "/\}\s*,\s*\[[\s\S]*PermissionMiddleware\(\s*\\\$modx\s*,\s*'view_document'\s*\)/",
        $block
    )
) {
    $fail('/references must require view_document via PermissionMiddleware');
}

fwrite(STDOUT, "OK ReferencesRouteAclTest\n");
exit(0);
