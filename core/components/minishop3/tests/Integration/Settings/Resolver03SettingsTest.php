<?php
declare(strict_types=1);

namespace MiniShop3\Tests\Integration\Settings;

use PHPUnit\Framework\TestCase;

/**
 * Resolver_03 upgrade logic (#883): package defaults must be appended without
 * overwriting admin edits. The real resolver needs a full MODX/xPDO runtime, so
 * these tests combine source-level guards with the extracted decision helpers.
 */
class Resolver03SettingsTest extends TestCase
{
    /** Known package fields before the #883 additions. */
    private const KNOWN_PACKAGE_FIELDS = [
        'first_name', 'last_name', 'phone', 'email', 'country', 'index',
        'region', 'city', 'metro', 'street', 'building', 'room',
        'comment', 'text_address',
    ];

    /** @param list<string> $currentFields */
    private function applyAddressFields(array $currentFields): string
    {
        $custom = array_diff($currentFields, self::KNOWN_PACKAGE_FIELDS);
        if ($custom !== []) {
            return implode(',', $currentFields);
        }

        return implode(',', array_unique(array_merge(
            $currentFields,
            ['entrance', 'floor', 'text_address']
        )));
    }

    /** @param array<string, array{value: mixed}> $properties */
    private function applySnippetProperties(array $properties): array
    {
        $shippedDefaults = ['tpl' => 'tpl.msOrderTotal', 'return' => 'tpl'];
        foreach ($shippedDefaults as $key => $defaultValue) {
            $current = $properties[$key]['value'] ?? null;
            if ($current === null || $current === $defaultValue) {
                $properties[$key] = ['name' => $key, 'type' => 'textfield', 'value' => $defaultValue];
            }
        }

        return $properties;
    }

    public function testAddressDefaultsAreAppendedWhenNoCustomFields(): void
    {
        $result = $this->applyAddressFields(['city', 'street', 'zip']);
        // 'zip' is not a known package field, so the whole value is preserved.
        self::assertSame('city,street,zip', $result);

        $result = $this->applyAddressFields(['city', 'street']);
        self::assertStringContainsString('entrance', $result);
        self::assertStringContainsString('floor', $result);
        self::assertStringContainsString('text_address', $result);
        self::assertStringContainsString('city', $result);
    }

    public function testCustomAddressFieldsArePreserved(): void
    {
        self::assertSame(
            'city,street,custom_field',
            $this->applyAddressFields(['city', 'street', 'custom_field'])
        );
    }

    public function testSnippetDefaultsAreSetWhenAbsentOrUnchanged(): void
    {
        $result = $this->applySnippetProperties([]);
        self::assertSame('tpl.msOrderTotal', $result['tpl']['value']);
        self::assertSame('tpl', $result['return']['value']);

        $result = $this->applySnippetProperties([
            'tpl' => ['value' => 'tpl.msOrderTotal'],
            'return' => ['value' => 'raw'],
        ]);
        self::assertSame('tpl.msOrderTotal', $result['tpl']['value']);
        // 'raw' is an admin value: the shipped default must not overwrite it.
        self::assertSame('raw', $result['return']['value']);
    }

    public function testSnippetCustomPropertiesArePreserved(): void
    {
        $original = [
            'tpl' => ['value' => 'custom.tpl'],
            'return' => ['value' => 'raw'],
            'custom' => ['value' => 'something'],
        ];
        self::assertSame($original, $this->applySnippetProperties($original));
    }

    public function testResolverDoesNotUseUnreliableEditedOnGuards(): void
    {
        $path = dirname(__DIR__, 6) . '/_build/resolvers/resolver_03_settings.php';
        $source = (string) file_get_contents($path);

        self::assertStringContainsString('$customFields', $source);
        self::assertStringContainsString('$shippedDefaults', $source);
        self::assertStringNotContainsString("\$snippet->get('editedon')", $source);
        // Only the pre-existing ms3_chunks_categories guard may use editedon.
        self::assertSame(1, substr_count($source, "get('editedon')"));
    }
}
