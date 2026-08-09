<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Utils;

use MiniShop3\Utils\Format;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NormalizeCurrencySymbolTest extends TestCase
{
    #[DataProvider('symbolCases')]
    public function testNormalizeCurrencySymbol(mixed $input, string $expected): void
    {
        self::assertSame($expected, Format::normalizeCurrencySymbol($input));
    }

    /**
     * @return iterable<string, array{0: mixed, 1: string}>
     */
    public static function symbolCases(): iterable
    {
        yield 'null uses default ruble' => [null, Format::DEFAULT_CURRENCY_SYMBOL];
        yield 'non-string uses default ruble' => [false, Format::DEFAULT_CURRENCY_SYMBOL];
        yield 'mojibake question mark' => ['?', Format::DEFAULT_CURRENCY_SYMBOL];
        yield 'keeps intentional dollar' => ['$', '$'];
        yield 'keeps ruble' => [Format::DEFAULT_CURRENCY_SYMBOL, Format::DEFAULT_CURRENCY_SYMBOL];
        yield 'keeps empty string' => ['', ''];
    }

    public function testDefaultCurrencySymbolIsRubleCodepoint(): void
    {
        self::assertSame("\u{20BD}", Format::DEFAULT_CURRENCY_SYMBOL);
        self::assertSame(mb_chr(0x20BD, 'UTF-8'), Format::DEFAULT_CURRENCY_SYMBOL);
    }
}
