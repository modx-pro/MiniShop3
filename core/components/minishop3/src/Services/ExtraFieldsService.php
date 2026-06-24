<?php

namespace MiniShop3\Services;

use MiniShop3\Model\msExtraField;
use MiniShop3\Model\msProductField;
use MiniShop3\Services\ExtraFields\KeyValueFieldService;
use MiniShop3\Services\ExtraFields\RepeaterFieldService;
use MiniShop3\Utils\ExtraFields;
use MODX\Revolution\modX;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ExtraFieldsService
{
    private modX $modx;
    private MigrationGenerator $migrationGenerator;
    private ExtraFields $extraFieldsUtil;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->migrationGenerator = new MigrationGenerator($modx);
        $this->extraFieldsUtil = new ExtraFields($modx);
    }

    /**
     * Create extra field with migration
     */
    public function createField(array $data): array
    {
        $repeaterError = $this->applyRepeaterConstraints($data);
        if ($repeaterError !== null) {
            return $repeaterError;
        }
        $keyValueError = $this->applyKeyValueConstraints($data);
        if ($keyValueError !== null) {
            return $keyValueError;
        }

        $validation = $this->validateFieldData($data);
        if (!$validation['success']) {
            return $validation;
        }

        /** @var msExtraField $field */
        $field = $this->modx->newObject(msExtraField::class);
        $field->fromArray($data);

        if (!$field->save()) {
            return ['success' => false, 'message' => 'Failed to create field in database'];
        }

        try {
            $migrationFile = $this->migrationGenerator->generateAddColumnMigration($field);
        } catch (\Exception $e) {
            $field->remove();
            return ['success' => false, 'message' => 'Migration generation error: ' . $e->getMessage()];
        }

        $migrationResult = $this->runMigrations();

        if (!$migrationResult['success']) {
            $field->remove();
            @unlink($migrationFile);
            return $migrationResult;
        }

        @unlink($migrationFile);
        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[ExtraFieldsService] Migration file deleted: " . basename($migrationFile)
        );

        $this->extraFieldsUtil->loadMap();
        $this->extraFieldsUtil->clearCache();

        // Only create msProductField for product-related models
        if ($field->get('class') === 'MiniShop3\\Model\\msProductData') {
            $this->createProductFieldFromExtra($field);
        }

        return [
            'success' => true,
            'message' => 'Field created successfully',
            'data' => $field->toArray(),
            'migration' => basename($migrationFile),
            'output' => $migrationResult['output'] ?? ''
        ];
    }

    /**
     * Delete extra field
     */
    public function deleteField(int $id): array
    {
        /** @var msExtraField $field */
        $field = $this->modx->getObject(msExtraField::class, $id);

        if (!$field) {
            return ['success' => false, 'message' => 'Field not found'];
        }

        try {
            $migrationFile = $this->migrationGenerator->generateDropColumnMigration($field);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Migration generation error: ' . $e->getMessage()];
        }

        $migrationResult = $this->runMigrations();

        if (!$migrationResult['success']) {
            @unlink($migrationFile);
            return $migrationResult;
        }

        @unlink($migrationFile);
        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[ExtraFieldsService] Migration file deleted: " . basename($migrationFile)
        );

        // Only delete msProductField for product-related models
        if ($field->get('class') === 'MiniShop3\\Model\\msProductData') {
            $this->deleteProductFieldsByName($field->get('key'));
        }

        $field->remove();

        $this->extraFieldsUtil->clearCache();

        return [
            'success' => true,
            'message' => 'Field deleted successfully',
            'migration' => basename($migrationFile),
            'output' => $migrationResult['output'] ?? ''
        ];
    }

    /**
     * Get list of all extra fields
     */
    public function getFields(array $criteria = []): array
    {
        $c = $this->modx->newQuery(msExtraField::class);

        if (!empty($criteria)) {
            $c->where($criteria);
        }

        $c->sortby('id', 'ASC');

        $fields = $this->modx->getCollection(msExtraField::class, $c);

        $result = [];
        foreach ($fields as $field) {
            $result[] = $this->formatField($field);
        }

        return $result;
    }

    /**
     * Get single extra field by ID (with column_exists flag).
     *
     * @return array<string, mixed>|null
     */
    public function getField(int $id): ?array
    {
        /** @var msExtraField|null $field */
        $field = $this->modx->getObject(msExtraField::class, $id);

        if (!$field) {
            return null;
        }

        return $this->formatField($field);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatField(msExtraField $field): array
    {
        $data = $field->toArray();

        $data['column_exists'] = $this->extraFieldsUtil->columnExists(
            $field->get('class'),
            $field->get('key')
        );

        return $data;
    }

    /**
     * Validate field data
     */
    private function validateFieldData(array $data): array
    {
        $required = ['class', 'key', 'dbtype', 'phptype'];

        foreach ($required as $fieldName) {
            if (empty($data[$fieldName])) {
                return [
                    'success' => false,
                    'message' => "Field '{$fieldName}' is required",
                    'field' => $fieldName
                ];
            }
        }

        $exists = $this->modx->getObject(msExtraField::class, [
            'class' => $data['class'],
            'key' => $data['key']
        ]);

        if ($exists) {
            return [
                'success' => false,
                'message' => "Field with name '{$data['key']}' already exists for class '{$data['class']}'",
                'field' => 'key'
            ];
        }

        if ($this->extraFieldsUtil->columnExists($data['class'], $data['key'])) {
            return [
                'success' => false,
                'message' => "Column '{$data['key']}' already exists in table",
                'field' => 'key'
            ];
        }

        return ['success' => true];
    }

    /**
     * Run all pending migrations
     */
    private function runMigrations(): array
    {
        try {
            $componentPath = MODX_CORE_PATH . 'components/minishop3/';
            $vendorAutoload = $componentPath . 'vendor/autoload.php';
            $phinxConfig = $componentPath . 'phinx.php';

            if (!file_exists($vendorAutoload)) {
                return ['success' => false, 'message' => 'Phinx is not installed. Run composer install'];
            }

            if (!file_exists($phinxConfig)) {
                return ['success' => false, 'message' => 'Phinx configuration not found'];
            }

            $configArray = require $phinxConfig;
            $config = new Config($configArray);

            $input = new StringInput('');
            $output = new BufferedOutput();

            $manager = new Manager($config, $input, $output);

            $manager->migrate('production');

            $outputText = $output->fetch();

            $this->modx->log(modX::LOG_LEVEL_INFO, '[ExtraFieldsService] Migrations executed successfully');

            return [
                'success' => true,
                'output' => $outputText
            ];
        } catch (\Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[ExtraFieldsService] Migration error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Migration execution error: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }

    /**
     * Automatically create msProductField when creating msExtraField
     */
    private function createProductFieldFromExtra(msExtraField $extraField): void
    {
        /** @var msProductField $productField */
        $productField = $this->modx->newObject(msProductField::class);

        $config = null;
        if ($extraField->get('xtype') === 'ms3-combo-select' && $extraField->get('select_options')) {
            $config = ['select_options' => $extraField->get('select_options')];
        } elseif ($extraField->get('xtype') === RepeaterFieldService::XTYPE && $extraField->get('repeater_config')) {
            $config = [
                'repeater_config' => $this->getRepeaterFieldService()->parseConfig(
                    $extraField->get('repeater_config')
                ),
            ];
        } elseif ($extraField->get('xtype') === KeyValueFieldService::XTYPE && $extraField->get('key_value_config')) {
            $config = [
                'key_value_config' => $this->getKeyValueFieldService()->parseConfig(
                    $extraField->get('key_value_config')
                ),
            ];
        }

        $width = $this->isWideFieldXtype($extraField->get('xtype')) ? 12 : 6;

        $productField->fromArray([
            'name' => $extraField->get('key'),
            'label' => $extraField->get('label') ?: $extraField->get('key'),
            'description' => $extraField->get('description'),
            'xtype' => $extraField->get('xtype') ?: 'textfield',
            'section' => null,
            'visible' => $extraField->get('active') ? 1 : 0,
            'required' => 0,
            'sort_order' => 999,
            'width' => $width,
            'is_system' => 0,
            'is_default' => 0,
            'config' => $config,
        ]);

        if ($productField->save()) {
            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                "[ExtraFieldsService] Auto-created msProductField: {$extraField->get('key')}"
            );
        } else {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                "[ExtraFieldsService] Failed to auto-create msProductField: {$extraField->get('key')}"
            );
        }
    }

    /**
     * Update extra field (metadata only, without DB structure changes)
     *
     * @param int $id Field ID
     * @param array $data Data to update
     * @return array
     */
    public function updateField(int $id, array $data): array
    {
        /** @var msExtraField $field */
        $field = $this->modx->getObject(msExtraField::class, $id);

        if (!$field) {
            return ['success' => false, 'message' => 'Field not found'];
        }

        if (($data['xtype'] ?? $field->get('xtype')) === RepeaterFieldService::XTYPE) {
            $repeaterPayload = array_merge($field->toArray(), $data);
            $repeaterError = $this->applyRepeaterConstraints($repeaterPayload);
            if ($repeaterError !== null) {
                return $repeaterError;
            }
            $data['repeater_config'] = $repeaterPayload['repeater_config'] ?? null;
        }
        if (($data['xtype'] ?? $field->get('xtype')) === KeyValueFieldService::XTYPE) {
            $keyValuePayload = array_merge($field->toArray(), $data);
            $keyValueError = $this->applyKeyValueConstraints($keyValuePayload);
            if ($keyValueError !== null) {
                return $keyValueError;
            }
            $data['key_value_config'] = $keyValuePayload['key_value_config'] ?? null;
        }

        $allowedFields = [
            'label',
            'description',
            'xtype',
            'active',
            'select_options',
            'repeater_config',
            'key_value_config',
        ];

        // Per-field null semantics:
        //   - `description`, `select_options` (optional text/json): null = clear
        //   - `label`, `xtype`, `active` (required for rendering): null skipped — a partial
        //     payload with `xtype: null` would silently break the field's UI.
        $nullClearable = ['description', 'select_options', 'repeater_config', 'key_value_config'];

        foreach ($allowedFields as $fieldName) {
            if (!array_key_exists($fieldName, $data)) {
                continue;
            }
            $value = $data[$fieldName];

            if ($value === null && !in_array($fieldName, $nullClearable, true)) {
                continue;
            }

            $field->set($fieldName, $value);
        }

        if ($field->get('xtype') !== RepeaterFieldService::XTYPE) {
            $field->set('repeater_config', null);
        }
        if ($field->get('xtype') !== KeyValueFieldService::XTYPE) {
            $field->set('key_value_config', null);
        }

        if (!$field->save()) {
            return ['success' => false, 'message' => 'Failed to update field in database'];
        }

        // Only update msProductField for product-related models
        if ($field->get('class') === 'MiniShop3\\Model\\msProductData') {
            $this->updateProductFieldFromExtra($field);
        }

        $this->extraFieldsUtil->clearCache();

        return [
            'success' => true,
            'message' => 'Field updated successfully',
            'data' => $field->toArray()
        ];
    }

    /**
     * Update msProductField based on msExtraField
     *
     * @param msExtraField $extraField
     * @return void
     */
    private function updateProductFieldFromExtra(msExtraField $extraField): void
    {
        $productField = $this->modx->getObject(msProductField::class, ['name' => $extraField->get('key')]);

        if ($productField) {
            $productField->set('label', $extraField->get('label') ?: $extraField->get('key'));
            $productField->set('description', $extraField->get('description'));
            $productField->set('xtype', $extraField->get('xtype') ?: 'textfield');
            $productField->set('visible', $extraField->get('active') ? 1 : 0);

            // Update config for ms3-combo-select
            $config = $productField->get('config') ?: [];
            if ($extraField->get('xtype') === 'ms3-combo-select') {
                $config['select_options'] = $extraField->get('select_options') ?: '';
                unset($config['repeater_config'], $config['key_value_config']);
            } elseif ($extraField->get('xtype') === RepeaterFieldService::XTYPE) {
                $config['repeater_config'] = $this->getRepeaterFieldService()->parseConfig(
                    $extraField->get('repeater_config')
                );
                unset($config['select_options'], $config['key_value_config']);
            } elseif ($extraField->get('xtype') === KeyValueFieldService::XTYPE) {
                $config['key_value_config'] = $this->getKeyValueFieldService()->parseConfig(
                    $extraField->get('key_value_config')
                );
                unset($config['select_options'], $config['repeater_config']);
            } else {
                unset($config['select_options'], $config['repeater_config'], $config['key_value_config']);
            }

            if ($this->isWideFieldXtype($extraField->get('xtype'))) {
                $productField->set('width', 12);
            }
            $productField->set('config', !empty($config) ? $config : null);

            if ($productField->save()) {
                $this->modx->log(
                    modX::LOG_LEVEL_INFO,
                    "[ExtraFieldsService] Updated msProductField: {$extraField->get('key')}"
                );
            } else {
                $this->modx->log(
                    modX::LOG_LEVEL_WARN,
                    "[ExtraFieldsService] Failed to update msProductField: {$extraField->get('key')}"
                );
            }
        }
    }

    /**
     * Delete msProductField by name (CASCADE delete)
     */
    private function deleteProductFieldsByName(string $fieldName): void
    {
        $fields = $this->modx->getCollection(msProductField::class, ['name' => $fieldName]);

        foreach ($fields as $field) {
            $field->remove();
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, "[ExtraFieldsService] Deleted msProductField: {$fieldName}");
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: false, message: string}|null
     */
    private function applyRepeaterConstraints(array &$data): ?array
    {
        if (($data['xtype'] ?? '') !== RepeaterFieldService::XTYPE) {
            return null;
        }

        $data['dbtype'] = 'json';
        $data['phptype'] = 'json';
        $data['precision'] = '';
        $data['null'] = true;

        $configValidation = $this->getRepeaterFieldService()->validateConfigSchema($data['repeater_config'] ?? '');
        if (!$configValidation['success']) {
            return [
                'success' => false,
                'message' => $configValidation['message'] ?? 'Invalid repeater configuration',
            ];
        }

        $data['repeater_config'] = $this->getRepeaterFieldService()->encodeConfig($configValidation['config']);

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: false, message: string}|null
     */
    private function applyKeyValueConstraints(array &$data): ?array
    {
        if (($data['xtype'] ?? '') !== KeyValueFieldService::XTYPE) {
            return null;
        }

        $data['dbtype'] = 'json';
        $data['phptype'] = 'json';
        $data['precision'] = '';
        $data['null'] = true;

        $configValidation = $this->getKeyValueFieldService()->validateConfigSchema($data['key_value_config'] ?? '');
        if (!$configValidation['success']) {
            return [
                'success' => false,
                'message' => $configValidation['message'] ?? 'Invalid key-value configuration',
            ];
        }

        $data['key_value_config'] = $this->getKeyValueFieldService()->encodeConfig($configValidation['config']);

        return null;
    }

    private function getRepeaterFieldService(): RepeaterFieldService
    {
        /** @var RepeaterFieldService $service */
        $service = $this->modx->services->get('ms3_repeater_field');

        return $service;
    }

    private function getKeyValueFieldService(): KeyValueFieldService
    {
        /** @var KeyValueFieldService $service */
        $service = $this->modx->services->get('ms3_key_value_field');

        return $service;
    }

    private function isWideFieldXtype(?string $xtype): bool
    {
        return in_array($xtype, [RepeaterFieldService::XTYPE, KeyValueFieldService::XTYPE], true);
    }
}
