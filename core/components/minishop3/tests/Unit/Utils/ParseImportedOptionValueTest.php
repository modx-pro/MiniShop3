<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Utils;

use MiniShop3\Utils\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParseImportedOptionValueTest extends TestCase
{
    #[DataProvider('cases')]
    public function testParseImportedOptionValue(mixed $expected, mixed $value, bool $isMulti): void
    {
        self::assertSame($expected, Utils::parseImportedOptionValue($value, $isMulti));
    }

    /**
     * @return iterable<string, array{0: mixed, 1: mixed, 2: bool}>
     */
    public static function cases(): iterable
    {
        yield 'multi comma-separated' => [['flour', 'water', 'salt'], 'flour, water, salt', true];
        yield 'multi JSON array' => [['flour', 'water'], '["flour","water"]', true];
        yield 'single scalar with comma' => ['flour, water', 'flour, water', false];
        yield 'empty multi' => [[], '', true];
        yield 'empty single' => ['', '', false];
    }
}
