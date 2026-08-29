<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Utils;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Documents the #622 version-sync contract used inline in:
 * - _build/resolvers/resolver_09_version.php
 * - elements/plugins/minishop3.php
 *
 * Those call sites must stay disk-independent (no autoload of new MiniShop3 src helpers).
 */
final class VersionSyncContractTest extends TestCase
{
    /**
     * Mirrors xPDOTransport::parseSignature() version half (MODX vendor, used by resolver).
     *
     * @see \xPDO\Transport\xPDOTransport::parseSignature
     */
    private static function extractFromTransportSignature(string $signature): string
    {
        if ($signature === '') {
            return '';
        }

        $exploded = explode('-', $signature);
        $name = current($exploded);
        $version = '';
        $part = next($exploded);
        while ($part !== false) {
            $dotPos = strpos($part, '.');
            if ($dotPos > 0 && is_numeric(substr($part, 0, $dotPos))) {
                $version = $part;
                while (($part = next($exploded)) !== false) {
                    $version .= '-' . $part;
                }
                break;
            }
            $name .= '-' . $part;
            $part = next($exploded);
        }

        return $version;
    }

    /**
     * Mirrors the plugin mismatch predicate: warn only when disk is missing or behind.
     */
    private static function isMismatch(string $diskVersion, string $packageVersion): bool
    {
        return $packageVersion !== ''
            && ($diskVersion === '' || version_compare($diskVersion, $packageVersion, '<'));
    }

    #[DataProvider('extractFromTransportSignatureCases')]
    public function testExtractFromTransportSignature(string $signature, string $expected): void
    {
        self::assertSame($expected, self::extractFromTransportSignature($signature));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function extractFromTransportSignatureCases(): iterable
    {
        yield 'standard beta signature' => ['minishop3-1.13.0-beta1', '1.13.0-beta1'];
        yield 'release without suffix' => ['minishop3-1.0.0', '1.0.0'];
        yield 'hyphenated package name' => ['my-extra-1.2.3-pl', '1.2.3-pl'];
        yield 'empty signature' => ['', ''];
        yield 'no dash separator' => ['minishop3', ''];
        yield 'unknown prefix only' => ['unknown', ''];
    }

    #[DataProvider('isMismatchCases')]
    public function testIsMismatch(string $diskVersion, string $packageVersion, bool $expected): void
    {
        self::assertSame($expected, self::isMismatch($diskVersion, $packageVersion));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: bool}>
     */
    public static function isMismatchCases(): iterable
    {
        yield 'empty package version' => ['1.13.0-beta1', '', false];
        yield 'equal versions' => ['1.13.0-beta1', '1.13.0-beta1', false];
        yield 'disk older than package' => ['1.12.0', '1.13.0-beta1', true];
        yield 'disk empty package set' => ['', '1.13.0-beta1', true];
        yield 'disk newer than package (git/rsync)' => ['1.14.0-dev', '1.13.0-beta1', false];
    }

    public function testPluginAndResolverStayDiskIndependent(): void
    {
        $ms3Root = dirname(__DIR__, 3);
        $repoRoot = dirname($ms3Root, 3);
        $plugin = file_get_contents($ms3Root . '/elements/plugins/minishop3.php');
        $resolver = file_get_contents($repoRoot . '/_build/resolvers/resolver_09_version.php');

        self::assertIsString($plugin);
        self::assertIsString($resolver);

        self::assertStringNotContainsString(
            'use MiniShop3\\Utils\\',
            $plugin,
            'Plugin plugincode is DB-static and must not autoload src helpers (#622).'
        );
        self::assertDoesNotMatchRegularExpression(
            '/^\s*use\s+MiniShop3\\\\Utils\\\\/m',
            $resolver,
            'Resolver must run from transport even when component src was not copied (#622).'
        );

        self::assertStringContainsString("getOption('ms3_version'", $plugin);
        self::assertStringContainsString('ms3-version-mismatch-banner', $plugin);
        self::assertMatchesRegularExpression(
            "/version_compare\\(\\\$diskVersion, \\\$packageVersion, '<'\\)/",
            $plugin,
            'Plugin must warn only when disk lags package (not strict inequality).'
        );
        self::assertStringContainsString('regClientHTMLBlock', $plugin);
        self::assertStringContainsString("'key' => 'ms3_version'", $resolver);
        self::assertStringContainsString('parseSignature', $resolver);
        self::assertStringNotContainsString("explode('-'", $resolver);
    }

    public function testHealthRoutesReferenceMs3VersionWithSkipEmpty(): void
    {
        $ms3Root = dirname(__DIR__, 3);

        foreach (['config/routes/manager.php', 'config/routes/web.php'] as $routeFile) {
            $contents = file_get_contents($ms3Root . '/' . $routeFile);
            self::assertIsString($contents);
            self::assertStringContainsString(
                "getOption('ms3_version', null, '1.0.0', true)",
                $contents
            );
        }
    }

    public function testDiskVersionMatchesBuildConfig(): void
    {
        $ms3Root = dirname(__DIR__, 3);
        $repoRoot = dirname($ms3Root, 3);

        $miniShop3Php = file_get_contents($ms3Root . '/src/MiniShop3.php');
        $buildConfig = file_get_contents($repoRoot . '/_build/config.inc.php');
        self::assertIsString($miniShop3Php);
        self::assertIsString($buildConfig);

        self::assertSame(
            1,
            preg_match("/public\\s+\\\$version\\s*=\\s*'([^']+)'/", $miniShop3Php, $diskMatch),
            'MiniShop3::$version must be a public string property.'
        );
        self::assertSame(
            1,
            preg_match("/'version'\\s*=>\\s*'([^']+)'/", $buildConfig, $versionMatch),
            '_build/config.inc.php must define version.'
        );
        self::assertSame(
            1,
            preg_match("/'release'\\s*=>\\s*'([^']+)'/", $buildConfig, $releaseMatch),
            '_build/config.inc.php must define release.'
        );

        self::assertSame(
            $versionMatch[1] . '-' . $releaseMatch[1],
            $diskMatch[1],
            'MiniShop3::$version must equal config version-release or the mgr banner will false-positive.'
        );
    }
}
