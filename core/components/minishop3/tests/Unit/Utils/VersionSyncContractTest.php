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
 * Those call sites must stay disk-independent (no autoload of new src helpers).
 */
final class VersionSyncContractTest extends TestCase
{
    /**
     * Mirrors resolver_09_version.php signature parsing.
     */
    private static function extractFromTransportSignature(string $signature): string
    {
        if ($signature === '') {
            return '';
        }

        $parts = explode('-', $signature, 2);

        return $parts[1] ?? '';
    }

    /**
     * Mirrors the plugin mismatch predicate.
     */
    private static function isMismatch(string $diskVersion, string $packageVersion): bool
    {
        return $packageVersion !== '' && $diskVersion !== $packageVersion;
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
        yield 'different versions' => ['1.12.0', '1.13.0-beta1', true];
        yield 'disk empty package set' => ['', '1.13.0-beta1', true];
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
        self::assertStringContainsString("'key' => 'ms3_version'", $resolver);
        self::assertStringContainsString("explode('-'", $resolver);
    }

    public function testHealthRoutesReferenceMs3Version(): void
    {
        $ms3Root = dirname(__DIR__, 3);

        foreach (['config/routes/manager.php', 'config/routes/web.php'] as $routeFile) {
            $contents = file_get_contents($ms3Root . '/' . $routeFile);
            self::assertIsString($contents);
            self::assertStringContainsString('ms3_version', $contents);
        }
    }
}
