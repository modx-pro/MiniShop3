<?php

/**
 * Keep require-dev packages out of the transport package (#779).
 *
 * core/components/minishop3/vendor/ is gitignored, so the package ships whatever
 * the build machine happens to have installed. A dev install drags PHPUnit, PHPStan
 * and the testbench (~38 MB) onto every production site, and every change to that
 * package set renames Composer's autoloader class — which is what broke upgrades to
 * 1.14.0-beta1.
 */

declare(strict_types=1);

/**
 * @param string $corePath core/components/minishop3/ with a trailing slash
 * @return string|null human-readable reason to abort the build, null when vendor is clean
 */
function ms3BuildVendorProblem(string $corePath): ?string
{
    $installed = $corePath . 'vendor/composer/installed.json';
    if (!file_exists($installed)) {
        return 'vendor/composer/installed.json not found. Run "composer install --no-dev" in ' . $corePath;
    }

    $data = json_decode((string)file_get_contents($installed), true);
    if (!is_array($data)) {
        return 'Could not read ' . $installed;
    }

    $devPackages = $data['dev-package-names'] ?? [];
    if (empty($data['dev']) && $devPackages === []) {
        return null;
    }

    return 'vendor/ contains require-dev packages (' . count($devPackages) . '). Run in ' . $corePath . ':'
        . PHP_EOL . '  composer install --no-dev --optimize-autoloader'
        . PHP_EOL . 'then build, then restore your tools with "composer install".';
}
