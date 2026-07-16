<?php

/**
 * Runs standalone smoke tests under tests/*Test.php (no MODX/MySQL).
 *
 * Each *Test.php is a self-contained script that exits with a status code.
 * This runner executes them in subprocesses; do not require() them here.
 *
 * Usage:
 *   php tests/run-smoke.php
 *   composer test:smoke
 *   composer ci:php
 */

declare(strict_types=1);

$testsDir = __DIR__;
$pattern = $testsDir . DIRECTORY_SEPARATOR . '*Test.php';
$files = glob($pattern) ?: [];
sort($files, SORT_STRING);

if ($files === []) {
    fwrite(STDERR, "No smoke tests matched {$pattern}\n");
    exit(1);
}

$failed = 0;

foreach ($files as $file) {
    $relative = 'tests/' . basename($file);
    fwrite(STDOUT, "→ {$relative}\n");

    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file);
    passthru($command, $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, "FAIL {$relative} (exit {$exitCode})\n");
        $failed++;
    }
}

if ($failed > 0) {
    fwrite(STDERR, "Smoke tests failed: {$failed}\n");
    exit(1);
}

fwrite(STDOUT, 'OK smoke tests (' . count($files) . ")\n");
exit(0);
