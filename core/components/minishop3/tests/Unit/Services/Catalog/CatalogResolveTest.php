<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Catalog;

use MiniShop3\Services\Catalog\CatalogResolve;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CatalogResolveTest extends TestCase
{
    public function testRequiredWhenNeitherAliasNorUri(): void
    {
        $result = CatalogResolve::parseLookup([]);

        self::assertSame(['ok' => false, 'error' => 'required'], $result);
    }

    public function testRequiredWhenBothEmptyAfterTrim(): void
    {
        $result = CatalogResolve::parseLookup(['alias' => '  ', 'uri' => '']);

        self::assertSame(['ok' => false, 'error' => 'required'], $result);
    }

    public function testConflictWhenBothProvided(): void
    {
        $result = CatalogResolve::parseLookup(['alias' => 'tea', 'uri' => 'catalog/tea']);

        self::assertSame(['ok' => false, 'error' => 'conflict'], $result);
    }

    #[DataProvider('invalidLookupParams')]
    public function testInvalidLookup(array $params): void
    {
        $result = CatalogResolve::parseLookup($params);

        self::assertSame(['ok' => false, 'error' => 'invalid'], $result);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function invalidLookupParams(): iterable
    {
        yield 'uri path traversal' => [['uri' => '../secret']];
        yield 'uri scheme' => [['uri' => 'http://evil']];
        yield 'uri double slash' => [['uri' => 'a//b']];
        yield 'uri nul' => [['uri' => "bad\0uri"]];
        yield 'alias path traversal' => [['alias' => '..']];
        yield 'mgr context' => [['alias' => 'tea', 'context' => 'mgr']];
        yield 'mgr prefix context' => [['alias' => 'tea', 'context' => 'mgrCustom']];
        yield 'invalid context chars' => [['alias' => 'tea', 'context' => 'en us']];
        yield 'alias array param' => [['alias' => ['x']]];
        yield 'uri array param' => [['uri' => ['catalog/tea']]];
        yield 'context array param' => [['alias' => 'tea', 'context' => ['web']]];
        yield 'alias object param' => [['alias' => (object) ['x' => 1]]];
    }

    public function testAliasLookupWithContextFallback(): void
    {
        $result = CatalogResolve::parseLookup(['alias' => 'tea'], 'shop');

        self::assertSame(
            [
                'ok' => true,
                'field' => 'alias',
                'value' => 'tea',
                'context' => 'shop',
            ],
            $result
        );
    }

    public function testUriLookupNormalizesLeadingSlash(): void
    {
        $result = CatalogResolve::parseLookup(['uri' => '/catalog/tea/']);

        self::assertTrue($result['ok']);
        self::assertSame('uri', $result['field']);
        self::assertSame('catalog/tea/', $result['value']);
        self::assertSame('web', $result['context']);
    }

    public function testExplicitContextIsSanitized(): void
    {
        $result = CatalogResolve::parseLookup(['alias' => 'tea', 'context' => ' en ']);

        self::assertTrue($result['ok']);
        self::assertSame('en', $result['context']);
    }

    public function testEmptyContextFallsBack(): void
    {
        $result = CatalogResolve::parseLookup(['alias' => 'tea', 'context' => '   '], 'web');

        self::assertTrue($result['ok']);
        self::assertSame('web', $result['context']);
    }

    public function testMgrFallbackContextFallsBackToWeb(): void
    {
        // Live MODX context key can be mgr; CatalogQuery soft-falls back to web (#665).
        $result = CatalogResolve::parseLookup(['alias' => 'tea'], 'mgr');

        self::assertTrue($result['ok']);
        self::assertSame('web', $result['context']);
    }

    public function testIntAliasIsAccepted(): void
    {
        $result = CatalogResolve::parseLookup(['alias' => 42], 'web');

        self::assertTrue($result['ok']);
        self::assertSame('42', $result['value']);
    }

    public function testNormalizeUriStripsLeadingSlash(): void
    {
        self::assertSame('catalog/tea', CatalogResolve::normalizeUri('/catalog/tea'));
    }

    public function testNormalizeUriRejectsUnsafePatterns(): void
    {
        self::assertNull(CatalogResolve::normalizeUri('../x'));
        self::assertNull(CatalogResolve::normalizeUri('https://x'));
        self::assertNull(CatalogResolve::normalizeUri('a//b'));
    }

    public function testUriLookupVariantsIncludeSlashForms(): void
    {
        self::assertSame(
            ['catalog/tea', 'catalog/tea/'],
            CatalogResolve::uriLookupVariants('catalog/tea')
        );
        self::assertSame(
            ['catalog/tea/', 'catalog/tea'],
            CatalogResolve::uriLookupVariants('catalog/tea/')
        );
    }
}
