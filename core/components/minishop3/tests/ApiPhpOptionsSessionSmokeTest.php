<?php

/**
 * Smoke: api.php skips MODX DB session on OPTIONS without poisoning context cache (#720).
 *
 * Run: php tests/ApiPhpOptionsSessionSmokeTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$repoRoot = dirname(__DIR__, 4);
$apiPhpPath = $repoRoot . '/assets/components/minishop3/api.php';
$apiPhp = file_get_contents($apiPhpPath);
if ($apiPhp === false) {
    $fail('api.php not readable');
}

if (!str_contains($apiPhp, 'WebApiModxBootstrap::prepareForInitialize')) {
    $fail('api.php must call WebApiModxBootstrap::prepareForInitialize before initialize');
}

if (!str_contains($apiPhp, "\$modx->initialize('web')")) {
    $fail("api.php must call initialize('web') with no options (context-cache safe)");
}

if (preg_match('/\$modx->initialize\(\s*[\'"]web[\'"]\s*,/', $apiPhp) === 1) {
    $fail('api.php must not pass a second argument to initialize (session_enabled poisons context cache)');
}

$bootstrap = file_get_contents(
    dirname(__DIR__) . '/src/Services/Api/WebApiModxBootstrap.php'
);
if ($bootstrap === false) {
    $fail('WebApiModxBootstrap.php not readable');
}

if (preg_match("/['\"]session_enabled['\"]\\s*=>/", $bootstrap) === 1) {
    $fail('WebApiModxBootstrap must not pass session_enabled into initialize options');
}

if (!str_contains($bootstrap, '$_SESSION = []')) {
    $fail('WebApiModxBootstrap must set $_SESSION = [] for OPTIONS');
}

fwrite(STDOUT, "OK: ApiPhpOptionsSessionSmokeTest\n");
