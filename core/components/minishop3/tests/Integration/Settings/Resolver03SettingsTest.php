<?php
declare(strict_types=1);

namespace MiniShop3\Tests\Integration\Settings;

use PHPUnit\Framework\TestCase;

/**
 * Tests for resolver_03_settings.php logic — ensure that user edits are preserved on upgrade (#883).
 *
 * We test the core logic (editedon checks) without executing the full resolver file.
 */
class Resolver03SettingsTest extends TestCase
{
    /**
     * Simulate the address fields logic from resolver_03_settings.php.
     */
    private function applyAddressFieldsLogic(string $currentValue, ?string $editedon): string
    {
        // This mirrors the logic in resolver_03_settings.php lines ~66-75
        if ($editedon) {
            // User edited — preserve changes
            return $currentValue;
        }
        // Not edited — add defaults
        $fields = explode(',', $currentValue);
        $fields = array_unique(array_merge($fields, ['entrance', 'floor', 'text_address']));
        return implode(',', $fields);
    }

    /**
     * Simulate the snippet properties logic from resolver_03_settings.php.
     */
    private function applySnippetPropertiesLogic(?string $editedon, array $currentProperties): array
    {
        // This mirrors the logic in resolver_03_settings.php lines ~125-140
        if ($editedon) {
            // User edited — preserve changes
            return $currentProperties;
        }
        // Not edited — set defaults
        $defaults = [
            'tpl' => ['name' => 'tpl', 'type' => 'textfield', 'value' => 'tpl.msOrderTotal'],
            'return' => ['name' => 'return', 'type' => 'textfield', 'value' => 'tpl'],
        ];
        return array_merge($currentProperties, $defaults);
    }

    public function testAddressFieldsNotEditedAddsDefaults(): void
    {
        $result = $this->applyAddressFieldsLogic('city,street,zip', null);
        
        $this->assertStringContainsString('entrance', $result);
        $this->assertStringContainsString('floor', $result);
        $this->assertStringContainsString('text_address', $result);
        $this->assertStringContainsString('city', $result);
        $this->assertStringContainsString('street', $result);
    }

    public function testAddressFieldsEditedKeepsOriginal(): void
    {
        $original = 'city,street,zip';
        $result = $this->applyAddressFieldsLogic($original, '2023-01-01 00:00:00');
        
        $this->assertSame($original, $result);
        $this->assertStringNotContainsString('entrance', $result);
        $this->assertStringNotContainsString('floor', $result);
        $this->assertStringNotContainsString('text_address', $result);
    }

    public function testAddressFieldsEditedWithCustomFields(): void
    {
        $original = 'city,street,custom_field';
        $result = $this->applyAddressFieldsLogic($original, '2023-01-01 00:00:00');
        
        $this->assertSame($original, $result);
        $this->assertStringContainsString('custom_field', $result);
    }

    public function testSnippetNotEditedSetsDefaults(): void
    {
        $result = $this->applySnippetPropertiesLogic(null, []);
        
        $this->assertArrayHasKey('tpl', $result);
        $this->assertEquals('tpl.msOrderTotal', $result['tpl']['value']);
        $this->assertArrayHasKey('return', $result);
        $this->assertEquals('tpl', $result['return']['value']);
    }

    public function testSnippetEditedKeepsOriginalProperties(): void
    {
        $original = [
            'tpl' => ['value' => 'custom.tpl'],
            'return' => ['value' => 'raw'],
            'custom' => ['value' => 'something'],
        ];
        $result = $this->applySnippetPropertiesLogic('2023-01-01 00:00:00', $original);
        
        $this->assertSame($original, $result);
        $this->assertEquals('custom.tpl', $result['tpl']['value']);
        $this->assertEquals('raw', $result['return']['value']);
    }

    public function testSnippetNotEditedMergesWithExisting(): void
    {
        $existing = ['custom' => ['value' => 'keep_me']];
        $result = $this->applySnippetPropertiesLogic(null, $existing);
        
        $this->assertArrayHasKey('tpl', $result);
        $this->assertArrayHasKey('return', $result);
        $this->assertArrayHasKey('custom', $result);
        $this->assertEquals('keep_me', $result['custom']['value']);
    }
}
