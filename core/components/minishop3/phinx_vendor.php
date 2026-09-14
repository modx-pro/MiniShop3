<?php

/**
 * Ensure MiniShop3 migrations resolve Phinx from this component's vendor (#719).
 *
 * Other extras (mSearch, ms3promocode, …) also ship robmorgan/phinx. Composer
 * ClassLoaders prepend on register(true), so late Phinx classes can load from
 * whichever vendor registered last. This helper requires MiniShop3's autoload
 * and re-prepends its ClassLoader when safe. If Phinx\\Config\\Config is already
 * loaded from another vendor, it logs and returns without requiring MiniShop3
 * autoload (Composer register(true) would otherwise mix loaders).
 */

declare(strict_types=1);

/**
 * Normalize path separators for prefix comparison (Laragon / Windows).
 */
function ms3PhinxNormalizePath(string $path): string
{
    $normalized = str_replace('\\', '/', $path);
    $normalized = preg_replace('#/+#', '/', $normalized) ?? $normalized;

    return rtrim($normalized, '/');
}

/**
 * Expected filesystem root for MiniShop3's Phinx package (no trailing slash).
 */
function ms3PhinxExpectedPhinxRoot(string $componentPath): string
{
    return ms3PhinxNormalizePath($componentPath) . '/vendor/robmorgan/phinx';
}

/**
 * Whether $filePath is the expected root or a file under it.
 */
function ms3PhinxPathIsUnder(string $filePath, string $expectedRoot): bool
{
    $file = ms3PhinxNormalizePath($filePath);
    $root = ms3PhinxNormalizePath($expectedRoot);

    $realFile = realpath($filePath);
    if ($realFile !== false) {
        $file = ms3PhinxNormalizePath($realFile);
    }

    $realRoot = realpath($expectedRoot);
    if ($realRoot !== false) {
        $root = ms3PhinxNormalizePath($realRoot);
    }

    return $file === $root || str_starts_with($file, $root . '/');
}

/**
 * @return list<\Composer\Autoload\ClassLoader>
 */
function ms3PhinxRegisteredClassLoaders(): array
{
    $loaders = [];
    foreach (spl_autoload_functions() ?: [] as $callable) {
        if (is_array($callable) && $callable[0] instanceof \Composer\Autoload\ClassLoader) {
            $loaders[] = $callable[0];
        }
    }

    return $loaders;
}

/**
 * Path that would supply $class via the first matching registered ClassLoader.
 */
function ms3PhinxFirstAutoloadFile(string $class): ?string
{
    foreach (ms3PhinxRegisteredClassLoaders() as $loader) {
        $found = $loader->findFile($class);
        if (is_string($found) && $found !== '') {
            return $found;
        }
    }

    return null;
}

/**
 * Locate Composer ClassLoader that maps Phinx\\Config\\Config under $expectedRoot.
 */
function ms3PhinxFindOwnedClassLoader(string $expectedRoot): ?\Composer\Autoload\ClassLoader
{
    foreach (ms3PhinxRegisteredClassLoaders() as $loader) {
        $found = $loader->findFile('Phinx\\Config\\Config');
        if (is_string($found) && ms3PhinxPathIsUnder($found, $expectedRoot)) {
            return $loader;
        }
    }

    return null;
}

/**
 * Require MiniShop3 vendor/autoload.php and return its ClassLoader when possible.
 *
 * require_once returns true on a second call, so we fall back to scanning
 * registered loaders for the MiniShop3 Phinx path.
 */
function ms3PhinxResolveClassLoader(string $componentPath): ?\Composer\Autoload\ClassLoader
{
    $autoload = ms3PhinxNormalizePath($componentPath) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return null;
    }

    $result = require $autoload;
    if ($result instanceof \Composer\Autoload\ClassLoader) {
        return $result;
    }

    return ms3PhinxFindOwnedClassLoader(ms3PhinxExpectedPhinxRoot($componentPath));
}

/**
 * Bootstrap MiniShop3 vendor for Phinx migrations.
 *
 * @param callable(string):void|null $log Error/warn sink (receives message without [MiniShop3] prefix)
 * @return bool true when late Phinx classes are expected from MiniShop3 vendor
 */
function ms3PhinxBootstrapVendor(string $componentPath, ?callable $log = null): bool
{
    $expectedRoot = ms3PhinxExpectedPhinxRoot($componentPath);

    // Must run BEFORE require vendor/autoload.php: Composer registers with register(true).
    if (class_exists(\Phinx\Config\Config::class, false)) {
        $configFile = (new ReflectionClass(\Phinx\Config\Config::class))->getFileName();
        if (!is_string($configFile) || !ms3PhinxPathIsUnder($configFile, $expectedRoot)) {
            if ($log !== null) {
                $actual = is_string($configFile) ? $configFile : '(unknown)';
                $log(
                    'Phinx\\Config\\Config loaded from ' . $actual
                    . ', expected under ' . $expectedRoot
                    . '. MiniShop3 migrations will use the already-loaded Phinx; '
                    . 'align Phinx versions across extras or load MiniShop3 vendor first.'
                );
            }

            // Do not require/prepend: avoid mixing foreign Config with MiniShop3 Environment.
            return false;
        }
    }

    $loader = ms3PhinxResolveClassLoader($componentPath);
    if ($loader === null) {
        if ($log !== null) {
            $log('Phinx vendor/autoload.php not available under ' . $componentPath);
        }

        return false;
    }

    $loader->unregister();
    $loader->register(true);

    $environmentFile = $loader->findFile('Phinx\\Migration\\Manager\\Environment');
    if (!is_string($environmentFile) || $environmentFile === '') {
        $environmentFile = ms3PhinxFirstAutoloadFile('Phinx\\Migration\\Manager\\Environment');
    }

    if (!is_string($environmentFile) || !ms3PhinxPathIsUnder($environmentFile, $expectedRoot)) {
        if ($log !== null) {
            $actual = is_string($environmentFile) ? $environmentFile : '(not found)';
            $log(
                'Phinx\\Migration\\Manager\\Environment resolves to ' . $actual
                . ', expected under ' . $expectedRoot
            );
        }

        return false;
    }

    return true;
}
