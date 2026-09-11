<?php

/**
 * Append cache-bust query to a Vue module URL.
 *
 * Package version alone does not change when an Extra rebuilds vue-dist, so
 * HTTP caches keep stale plugin tabs (Aura theme, size=small buttons, etc.).
 * When the URL maps under assets_path, filemtime is mixed into `v=`.
 */
function ms3_vue_module_cache_bust_url($modx, string $src, string $packageVersion): string
{
    $version = $packageVersion;
    $path = parse_url($src, PHP_URL_PATH);
    if (is_string($path) && $path !== '') {
        $assetsUrl = rtrim((string) $modx->getOption('assets_url'), '/');
        $assetsPath = rtrim((string) $modx->getOption('assets_path'), '/');
        $fsPath = null;
        if ($assetsUrl !== '' && str_starts_with($path, $assetsUrl)) {
            $fsPath = $assetsPath . substr($path, strlen($assetsUrl));
        } elseif (defined('MODX_ASSETS_PATH') && str_starts_with($path, '/assets/')) {
            $fsPath = rtrim(MODX_ASSETS_PATH, '/') . substr($path, strlen('/assets'));
        }
        if (is_string($fsPath) && is_file($fsPath)) {
            $version .= '.' . filemtime($fsPath);
        }
    }

    $separator = str_contains($src, '?') ? '&' : '?';

    return $src . $separator . 'v=' . rawurlencode($version);
}
