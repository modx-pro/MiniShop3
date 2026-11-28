<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Utils;

use MiniShop3\Utils\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * #885: "no vendor" can arrive as null, '', '0', 0 or whitespace. All of them
 * must clear msProductData.vendor_id, not create a phantom msVendor row.
 */
final class VendorIdClearTest extends TestCase
{
    #[DataProvider('emptyValues')]
    public function testEmptyVendorValues(mixed $value): void
    {
        self::assertTrue(Utils::isEmptyVendorValue($value));
    }

    #[DataProvider('keptValues')]
    public function testNonEmptyVendorValues(mixed $value): void
    {
        self::assertFalse(Utils::isEmptyVendorValue($value));
    }

    public function testAllWritePathsNormalizeVendorId(): void
    {
        $root = dirname(__DIR__, 6);
        foreach ([
            '/core/components/minishop3/src/Processors/Product/Create.php',
            '/core/components/minishop3/src/Processors/Product/Update.php',
            '/core/components/minishop3/src/Processors/Product/ProductDataPayloadTrait.php',
            '/core/components/minishop3/src/Services/Product/ProductDataService.php',
        ] as $relative) {
            $source = (string) file_get_contents($root . $relative);
            self::assertStringContainsString(
                'isEmptyVendorValue',
                $source,
                $relative . ' must normalize cleared vendor_id (#885)'
            );
        }
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function emptyValues(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'zero string' => ['0'];
        yield 'zero padded' => ['00'];
        yield 'zero float string' => ['0.0'];
        yield 'zero int' => [0];
        yield 'zero float' => [0.0];
        yield 'whitespace' => ['   '];
        yield 'false' => [false];
        yield 'true' => [true];
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function keptValues(): iterable
    {
        yield 'id' => [5];
        yield 'id string' => ['12'];
        yield 'vendor name' => ['Acme'];
    }
}
