<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services;

use MiniShop3\Services\Api\WebApiContextResolver;
use MiniShop3\Services\Catalog\CatalogContextException;
use MiniShop3\Services\Catalog\CatalogQuery;
use MiniShop3\Services\ContextKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Same charset/length/mgr rules for catalog and api.php context (#722).
 */
final class ContextKeySharedRulesTest extends TestCase
{
    public function testMaxLengthConstant(): void
    {
        self::assertSame(100, ContextKey::MAX_LENGTH);
    }

    #[DataProvider('acceptedKeys')]
    public function testAcceptedKeysPassBothFacades(string $key): void
    {
        self::assertTrue(ContextKey::isValid($key), 'predicate');
        self::assertSame($key, CatalogQuery::sanitizeContext($key));
        self::assertSame($key, WebApiContextResolver::resolve($key));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function acceptedKeys(): iterable
    {
        yield 'web' => ['web'];
        yield 'en' => ['en'];
        yield 'shop' => ['shop'];
        yield 'shop-v2' => ['shop-v2'];
        yield 'underscores' => ['shop_v2'];
        yield 'max length' => [str_repeat('a', ContextKey::MAX_LENGTH)];
    }

    #[DataProvider('rejectedKeys')]
    public function testRejectedKeysFailBothFacades(string $key): void
    {
        self::assertFalse(ContextKey::isValid($key), 'predicate');

        try {
            CatalogQuery::sanitizeContext($key);
            self::fail('CatalogQuery must throw CatalogContextException for ' . $key);
        } catch (CatalogContextException) {
            // expected — catalog rejects with 400 path
        }

        self::assertSame(
            WebApiContextResolver::DEFAULT_CONTEXT,
            WebApiContextResolver::resolve($key),
            'WebApiContextResolver falls back to web for ' . $key
        );
        self::assertNotSame($key, WebApiContextResolver::resolve($key));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function rejectedKeys(): iterable
    {
        yield 'mgr' => ['mgr'];
        yield 'Mgr mixed' => ['Mgr'];
        yield 'mgrCustom' => ['mgrCustom'];
        yield 'too long' => [str_repeat('a', ContextKey::MAX_LENGTH + 1)];
        yield 'bad chars' => ['bad chars!'];
        yield 'path' => ['../web'];
        yield 'spaces' => ['en us'];
    }
}
