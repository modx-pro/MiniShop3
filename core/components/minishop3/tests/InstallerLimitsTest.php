<?php

/**
 * #1023: installer must not lower existing PHP limits; custom Web API example
 * must use the /api/v1 namespace and token middleware.
 *
 * Run: php tests/InstallerLimitsTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$resolver = (string) file_get_contents($root . '/_build/resolvers/resolver_02_migrations.php');
if (!str_contains($resolver, '$currentMemory !== -1 && $currentMemory < 256 * 1024 ** 2')) {
    $fail('Memory limit guard must skip unlimited/large values (#1023)');
}
if (!str_contains($resolver, '$currentTime > 0 && $currentTime < 300')) {
    $fail('Time limit guard must treat both 0 and -1 as unlimited (#1023)');
}
if (preg_match("/@ini_set\('max_execution_time', 300\)/", $resolver) !== 1) {
    $fail('Resolver must still raise short time limits (#1023)');
}

$example = (string) file_get_contents($root . '/core/components/minishop3/config/routes_web.custom.example.php');
if (str_contains($example, '/api/web/')) {
    $fail('Custom Web API routes must use the /api/v1 namespace');
}
if (!str_contains($example, 'TokenMiddleware')) {
    $fail('Customer examples must use TokenMiddleware, not session middleware');
}
foreach (['AuthMiddleware', 'PermissionMiddleware', 'HttpStatus'] as $wrongImport) {
    if (str_contains($example, $wrongImport)) {
        $fail("Custom Web API example must not use {$wrongImport}");
    }
}

fwrite(STDOUT, "OK InstallerLimitsTest (#1023)\n");
exit(0);
