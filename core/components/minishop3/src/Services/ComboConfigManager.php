<?php

namespace MiniShop3\Services;

use MODX\Revolution\modX;

/**
 * Combo Field Configuration Manager
 *
 * Manages combo/select field configurations for model forms.
 * Supports both file-based configs (default) and database configs (from UI, higher priority).
 *
 * Config locations:
 * - Default: core/components/minishop3/config/combos/{model}.php
 * - Custom:  core/components/minishop3/custom/combos/{model}.php
 * - DB:      ms3_model_fields.config (highest priority)
 *
 * Source types for combo fields:
 * - model: Load options from xPDO model class
 * - static: Static options array
 *
 * DB config format (in ms3_model_fields.config JSON):
 * {
 *   "source": {
 *     "type": "model",
 *     "class": "MiniShop3\\Model\\msOrderStatus",
 *     "valueField": "id",
 *     "labelField": "name",
 *     "where": {"active": true},
 *     "sort": {"rank": "ASC"}
 *   }
 * }
 *
 * @see \MiniShop3\Services\FilterConfigManager for similar implementation
 */
class ComboConfigManager
{
    private modX $modx;
    private string $configPath;
    private string $customPath;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->configPath = MODX_CORE_PATH . 'components/minishop3/config/combos/';
        $this->customPath = MODX_CORE_PATH . 'components/minishop3/custom/combos/';
    }

    /**
     * Get combo configuration for a specific field
     *
     * Priority: DB (properties) > custom file > default file
     *
     * @param string $model Model name (msOrder, msOrderAddress)
     * @param string $fieldName Field name (status_id, delivery_id, etc)
     * @param array|null $dbProperties Config from database (ms3_model_fields.config)
     * @return array|null Source configuration or null if not found
     */
    public function getFieldConfig(string $model, string $fieldName, ?array $dbProperties = null): ?array
    {
        // Priority 1: Database properties (from UI)
        if (!empty($dbProperties['source'])) {
            return $dbProperties['source'];
        }

        // Priority 2-3: File configs (custom overrides default)
        $fileConfigs = $this->loadConfig($model);

        if (isset($fileConfigs[$fieldName]['source'])) {
            return $fileConfigs[$fieldName]['source'];
        }

        return null;
    }

    /**
     * Get all combo configurations for a model
     *
     * @param string $model Model name
     * @return array Configurations keyed by field name
     */
    public function getModelConfigs(string $model): array
    {
        return $this->loadConfig($model);
    }

    /**
     * Resolve combo options for a field
     *
     * @param string $model Model name
     * @param string $fieldName Field name
     * @param array|null $dbProperties Properties from database
     * @return array Options array [{value, label}, ...]
     */
    public function resolveOptions(string $model, string $fieldName, ?array $dbProperties = null): array
    {
        $source = $this->getFieldConfig($model, $fieldName, $dbProperties);

        if (empty($source)) {
            return [];
        }

        return $this->resolveSelectOptions($source);
    }

    /**
     * Resolve options for all combo fields of a model
     *
     * @param string $model Model name
     * @param array $fieldsWithProperties Array of fields with their DB properties [{name, properties}, ...]
     * @return array Options keyed by field name
     * @deprecated Use resolveAllOptionsWithMeta() for full config support
     */
    public function resolveAllOptions(string $model, array $fieldsWithProperties = []): array
    {
        $result = [];
        $fullResult = $this->resolveAllOptionsWithMeta($model, $fieldsWithProperties);

        foreach ($fullResult as $fieldName => $data) {
            $result[$fieldName] = $data['options'] ?? [];
        }

        return $result;
    }

    /**
     * Resolve options for all combo fields of a model with metadata
     *
     * Returns options along with configuration metadata (compareField, etc.)
     *
     * @param string $model Model name
     * @param array $fieldsWithProperties Array of fields with their DB properties [{name, properties}, ...]
     * @return array Data keyed by field name: [{options: [], compareField: string, ...}, ...]
     */
    public function resolveAllOptionsWithMeta(string $model, array $fieldsWithProperties = []): array
    {
        $result = [];

        // Create lookup map from fields with properties
        $propertiesMap = [];
        foreach ($fieldsWithProperties as $field) {
            if (!empty($field['name'])) {
                $propertiesMap[$field['name']] = $field['properties'] ?? null;
            }
        }

        // Get file-based configs
        $fileConfigs = $this->loadConfig($model);

        // Combine field names from both sources
        $fieldNames = array_unique(array_merge(
            array_keys($fileConfigs),
            array_keys($propertiesMap)
        ));

        foreach ($fieldNames as $fieldName) {
            $dbProps = $propertiesMap[$fieldName] ?? null;

            // Decode JSON if needed
            if (is_string($dbProps)) {
                $dbProps = json_decode($dbProps, true);
            }

            // Get source config
            $source = $this->getFieldConfig($model, $fieldName, $dbProps);

            if (empty($source)) {
                continue;
            }

            $options = $this->resolveSelectOptions($source);

            if (!empty($options)) {
                $result[$fieldName] = [
                    'options' => $options,
                    'compareField' => $source['compareField'] ?? $fieldName,
                    'valueField' => $source['valueField'] ?? 'id',
                ];
            }
        }

        return $result;
    }

    /**
     * Load and merge config files
     *
     * @param string $model Model name
     * @return array Merged configuration
     */
    private function loadConfig(string $model): array
    {
        // Sanitize model name
        $model = preg_replace('/[^a-zA-Z0-9_-]/', '', $model);

        // Load default config
        $defaultFile = $this->configPath . $model . '.php';
        $configs = [];

        if (file_exists($defaultFile)) {
            $configs = include $defaultFile;
            if (!is_array($configs)) {
                $this->modx->log(modX::LOG_LEVEL_ERROR,
                    "[ComboConfigManager] Config file did not return array: {$defaultFile}"
                );
                $configs = [];
            }
        }

        // Merge with custom config (custom overrides default)
        $customFile = $this->customPath . $model . '.php';
        if (file_exists($customFile)) {
            $custom = include $customFile;
            if (is_array($custom)) {
                $configs = array_replace_recursive($configs, $custom);
            }
        }

        return $configs;
    }

    /**
     * Resolve select options from source configuration
     *
     * @param array $source Source configuration
     * @return array Options array [{value, label}, ...]
     */
    public function resolveSelectOptions(array $source): array
    {
        $type = $source['type'] ?? '';

        return match ($type) {
            'model' => $this->resolveFromModel($source),
            'static' => $source['options'] ?? [],
            default => [],
        };
    }

    /**
     * Resolve options from xPDO model
     *
     * @param array $source Source configuration
     * @return array Options array
     */
    private function resolveFromModel(array $source): array
    {
        $class = $source['class'] ?? '';
        if (empty($class)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "[ComboConfigManager] Empty class in source config");
            return [];
        }

        $valueField = $source['valueField'] ?? 'id';
        $labelField = $source['labelField'] ?? 'name';
        $labelTemplate = $source['labelTemplate'] ?? null;
        $limit = $source['limit'] ?? 500;

        try {
            $c = $this->modx->newQuery($class);

            // WHERE conditions
            if (!empty($source['where'])) {
                $c->where($source['where']);
            }

            // ORDER BY
            if (!empty($source['sort'])) {
                foreach ($source['sort'] as $field => $dir) {
                    $c->sortby($field, $dir);
                }
            }

            // LIMIT protection
            $c->limit($limit);

            $options = [];
            foreach ($this->modx->getIterator($class, $c) as $item) {
                // Build label from template or single field
                $label = $this->buildLabel($item, $labelTemplate, $labelField);

                $options[] = [
                    'value' => $item->get($valueField),
                    'label' => $label,
                ];
            }

            return $options;
        } catch (\Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,
                "[ComboConfigManager] Error resolving options from model {$class}: " . $e->getMessage()
            );
            return [];
        }
    }

    /**
     * Build label from template or single field
     *
     * Supports:
     * - labelTemplate: "{first_name} {last_name} ({email})" - replaces {field} with values
     * - labelField: "name" - simple single field (backward compatible)
     *
     * @param \xPDO\Om\xPDOObject $item xPDO object
     * @param string|null $template Label template with {field} placeholders
     * @param string $singleField Fallback single field name
     * @return string Built label
     */
    private function buildLabel($item, ?string $template, string $singleField): string
    {
        // Use template if provided
        if (!empty($template)) {
            $label = preg_replace_callback(
                '/\{(\w+)\}/',
                function ($matches) use ($item) {
                    $fieldName = $matches[1];
                    $value = $item->get($fieldName);
                    return $value !== null ? (string)$value : '';
                },
                $template
            );
            // Clean up multiple spaces and trim
            $label = trim(preg_replace('/\s+/', ' ', $label));
        } else {
            // Fallback to single field
            $label = $item->get($singleField);
        }

        // Translate lexicon keys
        if (is_string($label) && str_starts_with($label, 'ms3_')) {
            $translated = $this->modx->lexicon($label);
            if ($translated !== $label) {
                $label = $translated;
            }
        }

        return (string)$label;
    }

    /**
     * Get default source template for new combo fields
     *
     * @return array Default source configuration (commented example)
     */
    public function getDefaultSourceTemplate(): array
    {
        return [
            'type' => 'model',
            'class' => 'MiniShop3\\Model\\ClassName',
            'valueField' => 'id',
            'labelField' => 'name',
            'where' => [],
            'sort' => ['name' => 'ASC'],
            'limit' => 500,
        ];
    }

    /**
     * Validate source configuration
     *
     * @param array $source Source configuration to validate
     * @return array Validation result ['valid' => bool, 'errors' => array]
     */
    public function validateSource(array $source): array
    {
        $errors = [];

        $type = $source['type'] ?? '';

        if (empty($type)) {
            $errors[] = 'Source type is required';
        } elseif (!in_array($type, ['model', 'static'])) {
            $errors[] = "Unknown source type: {$type}";
        }

        if ($type === 'model') {
            if (empty($source['class'])) {
                $errors[] = 'Class is required for model source';
            } elseif (!class_exists($source['class'])) {
                $errors[] = "Class not found: {$source['class']}";
            }
        }

        if ($type === 'static') {
            if (empty($source['options']) || !is_array($source['options'])) {
                $errors[] = 'Options array is required for static source';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
