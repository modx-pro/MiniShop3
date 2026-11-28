<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Option;

use MiniShop3\Services\Option\OptionKeyValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression #841: reserved product-data keys must not become option keys.
 */
final class OptionKeyValidatorTest extends TestCase
{
    #[DataProvider('reservedKeysProvider')]
    public function testReservedProductKeysAreRejected(string $key): void
    {
        self::assertSame('ms3_option_err_reserved_key', OptionKeyValidator::lexiconErrorFor($key));
        self::assertTrue(OptionKeyValidator::isReserved($key));
    }

    /**
     * @return list<array{0: string}>
     */
    public static function reservedKeysProvider(): array
    {
        return [
            ['color'],
            ['size'],
            ['price'],
            ['article'],
            ['vendor'],
            ['tags'],
            ['pagetitle'],
            ['id'],
            ['stock'],
            ['vendor_id'],
            ['source_id'],
            ['preview_file_id'],
        ];
    }

    #[DataProvider('allowedKeysProvider')]
    public function testCustomOptionKeysAreAllowed(string $key): void
    {
        self::assertNull(OptionKeyValidator::lexiconErrorFor($key));
        self::assertFalse(OptionKeyValidator::isReserved($key));
    }

    /**
     * @return list<array{0: string}>
     */
    public static function allowedKeysProvider(): array
    {
        return [
            ['material'],
            ['length'],
            ['product_color'],
            ['shirt-size'],
        ];
    }

    public function testInvalidKeyFormatIsRejected(): void
    {
        self::assertSame('ms3_option_err_invalid_key', OptionKeyValidator::lexiconErrorFor('1starts_with_digit'));
        self::assertSame('ms3_option_err_invalid_key', OptionKeyValidator::lexiconErrorFor('has space'));
    }
}
