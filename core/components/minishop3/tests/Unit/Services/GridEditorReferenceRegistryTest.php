<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services;

use MiniShop3\Services\GridEditorReferenceRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GridEditorReferenceRegistryTest extends TestCase
{
    #[DataProvider('endpointCases')]
    public function testIsAllowlistedEndpoint(bool $expected, string $url): void
    {
        self::assertSame($expected, GridEditorReferenceRegistry::isAllowlistedEndpoint($url));
    }

    /**
     * @return iterable<string, array{0: bool, 1: string}>
     */
    public static function endpointCases(): iterable
    {
        yield 'allowlisted path' => [true, '/api/mgr/references/vendors'];
        yield 'allowlisted with query' => [true, '/api/mgr/references/vendors?q=1'];
        yield 'empty' => [false, ''];
        yield 'relative without slash' => [false, 'api/mgr/references/vendors'];
        yield 'ssrf absolute http' => [false, 'https://evil.example/api/mgr/references/vendors'];
        yield 'wrong prefix' => [false, '/api/mgr/orders'];
        yield 'path traversal style' => [false, '/api/mgr/../mgr/references/vendors'];
    }

    public function testPathForKnownReference(): void
    {
        self::assertSame('/api/mgr/references/vendors', GridEditorReferenceRegistry::pathFor('vendors'));
        self::assertNull(GridEditorReferenceRegistry::pathFor('unknown'));
    }
}
