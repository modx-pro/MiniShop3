<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\ExtraFields;

use InvalidArgumentException;
use MiniShop3\Services\ExtraFields\RepeaterFieldService;
use MODX\Revolution\modX;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RepeaterFieldServiceTest extends TestCase
{
    private RepeaterFieldService $service;

    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }

        $this->service = new RepeaterFieldService(new modX());
    }

    #[DataProvider('repeaterXtypeCases')]
    public function testIsRepeaterXtype(bool $expected, ?string $xtype): void
    {
        self::assertSame($expected, $this->service->isRepeaterXtype($xtype));
    }

    /**
     * @return iterable<string, array{0: bool, 1: ?string}>
     */
    public static function repeaterXtypeCases(): iterable
    {
        yield 'repeater xtype' => [true, RepeaterFieldService::XTYPE];
        yield 'textfield' => [false, 'textfield'];
        yield 'null' => [false, null];
    }

    public function testParseConfigMergesDefaultsAndNormalizesRankField(): void
    {
        $config = $this->service->parseConfig([
            'columns' => [['key' => 'title']],
            'minRows' => 1,
            'rankField' => '',
        ]);

        self::assertSame([['key' => 'title']], $config['columns']);
        self::assertSame(1, $config['minRows']);
        self::assertSame('rank', $config['rankField']);
        self::assertTrue($config['sortable']);
    }

    public function testParseConfigAcceptsJsonString(): void
    {
        $config = $this->service->parseConfig('{"columns":[{"key":"sku"}],"maxRows":2}');

        self::assertSame([['key' => 'sku']], $config['columns']);
        self::assertSame(2, $config['maxRows']);
    }

    public function testParseConfigFallsBackOnMalformedJson(): void
    {
        $config = $this->service->parseConfig('{not-json', 'specs');

        self::assertSame([], $config['columns']);
        self::assertSame('rank', $config['rankField']);
    }

    public function testValidateConfigSchemaRejectsEmptyColumns(): void
    {
        $result = $this->service->validateConfigSchema(['columns' => []]);

        self::assertFalse($result['success']);
    }

    public function testValidateConfigSchemaRejectsDuplicateColumnKeys(): void
    {
        $result = $this->service->validateConfigSchema([
            'columns' => [
                ['key' => 'title'],
                ['key' => 'title'],
            ],
        ]);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Duplicate', $result['message']);
    }

    public function testValidateConfigSchemaAcceptsUniqueColumns(): void
    {
        $result = $this->service->validateConfigSchema([
            'columns' => [
                ['key' => 'title'],
                ['key' => 'qty', 'xtype' => 'numberfield', 'required' => true],
            ],
        ]);

        self::assertTrue($result['success']);
        self::assertIsArray($result['config']);
    }

    public function testEncodeConfigRoundTripsUnicode(): void
    {
        $encoded = $this->service->encodeConfig(['columns' => [['key' => 'название']]]);

        self::assertSame('{"columns":[{"key":"название"}]}', $encoded);
    }

    #[DataProvider('decodeValueCases')]
    public function testDecodeValue(array $expected, mixed $value): void
    {
        self::assertSame($expected, $this->service->decodeValue($value));
    }

    /**
     * @return iterable<string, array{0: list<array<string, mixed>>, 1: mixed}>
     */
    public static function decodeValueCases(): iterable
    {
        yield 'null' => [[], null];
        yield 'empty string' => [[], ''];
        yield 'json array' => [[['a' => 1]], '[{"a":1}]'];
        yield 'php array' => [[['a' => 1], ['b' => 2]], [['a' => 1], ['b' => 2]]];
    }

    #[DataProvider('decodeValueRejectCases')]
    public function testDecodeValueRejectsInvalidPayload(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->decodeValue($value);
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function decodeValueRejectCases(): iterable
    {
        yield 'malformed json' => ['not-json'];
        yield 'non-array scalar' => [42];
    }

    public function testNormalizeRowsDropsEmptyStripsUnknownAndAppliesMaxRows(): void
    {
        $normalized = $this->service->normalizeRows(
            [
                ['title' => '', 'qty' => '', 'extra' => 'x'],
                ['title' => 'A', 'qty' => '1', 'extra' => 'drop-me', 'rank' => 99],
                ['title' => 'B', 'qty' => '2'],
                ['title' => 'C', 'qty' => '3'],
            ],
            [
                'columns' => [
                    ['key' => 'title'],
                    ['key' => 'qty'],
                ],
                'rankField' => 'rank',
                'maxRows' => 2,
            ]
        );

        self::assertSame(
            [
                ['title' => 'A', 'qty' => '1', 'rank' => 0],
                ['title' => 'B', 'qty' => '2', 'rank' => 1],
            ],
            $normalized
        );
    }

    public function testValidateRowsFailsOnMinRequiredAndNonNumeric(): void
    {
        $result = $this->service->validateRows(
            [['title' => '', 'qty' => 'x']],
            [
                'columns' => [
                    ['key' => 'title', 'required' => true],
                    ['key' => 'qty', 'xtype' => 'numberfield'],
                ],
                'minRows' => 2,
            ]
        );

        self::assertFalse($result['ok']);
        $errors = implode(' ', $result['errors']);
        self::assertStringContainsString('Minimum', $errors);
        self::assertStringContainsString('required', $errors);
        self::assertStringContainsString('numeric', $errors);
    }

    public function testValidateRowsAcceptsValidRows(): void
    {
        $result = $this->service->validateRows(
            [
                ['title' => 'A', 'qty' => 1],
                ['title' => 'B', 'qty' => '2'],
            ],
            [
                'columns' => [
                    ['key' => 'title', 'required' => true],
                    ['key' => 'qty', 'xtype' => 'numberfield'],
                ],
                'minRows' => 2,
            ]
        );

        self::assertTrue($result['ok']);
    }

    public function testProcessValueNormalizesAndValidates(): void
    {
        $rows = $this->service->processValue(
            [['title' => 'One', 'noise' => 1], ['title' => '']],
            [
                'columns' => [
                    ['key' => 'title', 'required' => true],
                ],
                'minRows' => 1,
                'rankField' => 'rank',
            ]
        );

        self::assertSame([['title' => 'One', 'rank' => 0]], $rows);
    }

    public function testProcessValueThrowsOnValidationFailure(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->processValue([], [
            'columns' => [['key' => 'title', 'required' => true]],
            'minRows' => 1,
        ]);
    }
}
