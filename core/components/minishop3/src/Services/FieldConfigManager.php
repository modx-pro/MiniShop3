<?php

namespace MiniShop3\Services;

use MODX\Revolution\modX;

/**
 * Universal service for working with model fields
 *
 * Provides automatic reading of fields from xPDO models and their configuration
 */
class FieldConfigManager
{
    /** @var modX */
    protected $modx;

    protected $configManager;

    /** @var array Cache of loaded model aliases */
    protected $modelAliases = [];

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->loadModelAliases();
    }

    /**
     * Load alias → model class mapping
     */
    protected function loadModelAliases(): void
    {
        $aliasesPath = MODX_CORE_PATH . 'components/minishop3/config/model_aliases.php';

        if (file_exists($aliasesPath)) {
            $this->modelAliases = include $aliasesPath;
        }
    }

    /**
     * Get full model class by alias
     *
     * @param string $alias
     * @return string|null
     */
    public function getModelClassByAlias(string $alias): ?string
    {
        return $this->modelAliases[$alias] ?? null;
    }

    /**
     * Load fields from ms3_product_fields table
     *
     * @return array
     */
    public function loadFieldsFromDatabase(): array
    {
        $query = $this->modx->newQuery('MiniShop3\\Model\\msProductField');
        $query->where(['visible' => true]);
        $query->sortby('sort_order', 'ASC');

        $collection = $this->modx->getCollection('MiniShop3\\Model\\msProductField', $query);

        $fields = [];
        foreach ($collection as $field) {
            $configRaw = $field->get('config');

            if (is_array($configRaw)) {
                $config = $configRaw;
            } elseif (is_string($configRaw) && !empty($configRaw)) {
                $config = json_decode($configRaw, true) ?: [];
            } else {
                $config = [];
            }

            $fieldData = [
                'name' => $field->get('name'),
                'label' => $field->get('label'),
                'xtype' => $field->get('xtype'),
                'section' => $field->get('section'),
                'visible' => (bool)$field->get('visible'),
                'required' => (bool)$field->get('required'),
                'sort_order' => (int)$field->get('sort_order'),
                'width' => (int)$field->get('width'),
                'description' => $field->get('description'),
                'is_system' => (bool)$field->get('is_system'),
                'is_default' => (bool)$field->get('is_default'),
            ];

            if (is_array($config)) {
                $fieldData = array_merge($fieldData, $config);
            }

            $fields[] = $fieldData;
        }

        return $fields;
    }

    /**
     * Get all model fields with automatic type detection
     *
     * @param string $modelClass Full model class name
     * @return array Array of fields from model
     * @throws \Exception
     */
    public function getModelFields(string $modelClass): array
    {
        if (!class_exists($modelClass)) {
            throw new \Exception("Model class not found: {$modelClass}");
        }

        $mysqlClass = str_replace('\\Model\\', '\\Model\\mysql\\', $modelClass);

        if (!class_exists($mysqlClass)) {
            throw new \Exception("MySQL model class not found: {$mysqlClass}");
        }

        $metaMap = $mysqlClass::$metaMap ?? null;

        if (!$metaMap || !isset($metaMap['fields'])) {
            throw new \Exception("Model {$mysqlClass} does not have metaMap with fields");
        }

        $fields = [];
        $fieldMeta = $metaMap['fieldMeta'] ?? [];

        foreach ($metaMap['fields'] as $fieldName => $defaultValue) {
            if ($fieldName === 'id') {
                continue;
            }

            $meta = $fieldMeta[$fieldName] ?? [];

            $fields[] = [
                'name' => $fieldName,
                'label_key' => 'ms3_product_' . $fieldName,
                'description_key' => 'ms3_product_' . $fieldName . '_help',
                'xtype' => $this->guessXtype($meta),
                'width' => 4,
                'visible' => true,
                'editable' => true,
                'required' => !($meta['null'] ?? true),
                'default' => $defaultValue,
                'phptype' => $meta['phptype'] ?? 'string',
                'dbtype' => $meta['dbtype'] ?? 'varchar',
            ];
        }

        return $fields;
    }

    /**
     * Determine widget xtype from field metadata
     *
     * @param array $fieldMeta Field metadata from $metaMap['fieldMeta']
     * @return string
     */
    protected function guessXtype(array $fieldMeta): string
    {
        $phptype = $fieldMeta['phptype'] ?? 'string';
        $dbtype = $fieldMeta['dbtype'] ?? '';
        $precision = $fieldMeta['precision'] ?? '';

        if ($phptype === 'boolean' || ($dbtype === 'tinyint' && $precision === '1')) {
            return 'checkbox';
        }

        if (in_array($phptype, ['float', 'integer']) || in_array($dbtype, ['decimal', 'int'])) {
            return 'numberfield';
        }

        if ($phptype === 'json') {
            return 'textarea';
        }

        if ($dbtype === 'text') {
            return 'textarea';
        }

        return 'textfield';
    }

    /**
     * Generate readable label from field name
     *
     * @param string $fieldName
     * @return string
     */
    protected function generateLabel(string $fieldName): string
    {
        $words = explode('_', $fieldName);
        $words = array_map('ucfirst', $words);
        return implode(' ', $words);
    }

    /**
     * Get complete field configuration for page with model, JSON and DB
     *
     * @param string $pageKey Page key (product_data, order, etc)
     * @param string|null $modelAlias Model alias (if null, taken from JSON config)
     * @param string $contextKey MODX context
     * @return array
     * @throws \Exception
     */
    public function getPageFieldsConfig(string $pageKey, ?string $modelAlias = null, string $contextKey = 'web'): array
    {
        // 1. If modelAlias is specified, read fields directly from model
        if ($modelAlias) {
            $modelClass = $this->getModelClassByAlias($modelAlias);
            if (!$modelClass) {
                throw new \Exception("Model alias not found: {$modelAlias}");
            }

            $modelFields = $this->getModelFields($modelClass);
        } else {
            // While model is not specified, use old logic (JSON only)
            $modelFields = [];
        }

        // 2. Load JSON config (if exists)
        $jsonConfig = $this->loadJsonConfig($pageKey);
        $jsonFields = $jsonConfig['fields'] ?? [];

        // 3. Merge: model fields + JSON overrides
        $baseFields = $this->mergeModelWithJson($modelFields, $jsonFields);

        // 4. Apply lexicon to label and description
        $finalFields = $this->applyLexicon($baseFields);

        // 5. Load and process sections
        $sections = $this->processSections($jsonConfig['sections'] ?? []);

        return [
            'model_alias' => $modelAlias,
            'model_class' => $modelClass ?? null,
            'sections' => $sections,
            'fields' => $finalFields,
        ];
    }

    /**
     * Load JSON config (optional, may not exist)
     *
     * @param string $pageKey
     * @return array
     */
    protected function loadJsonConfig(string $pageKey): array
    {
        $configPath = MODX_CORE_PATH . 'components/minishop3/config/pages/' . $pageKey . '.json';

        if (!file_exists($configPath)) {
            return ['fields' => []];
        }

        $content = file_get_contents($configPath);
        if ($content === false) {
            return ['fields' => []];
        }

        $config = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "Invalid JSON in {$configPath}: " . json_last_error_msg());
            return ['fields' => []];
        }

        return $config;
    }

    /**
     * Merge model fields with JSON config
     *
     * @param array $modelFields Fields from model
     * @param array|object $jsonFields Fields from JSON (can be object or array)
     * @return array
     */
    protected function mergeModelWithJson(array $modelFields, $jsonFields): array
    {
        // Convert field array to associative array by name
        $fieldsMap = [];
        foreach ($modelFields as $field) {
            $fieldsMap[$field['name']] = $field;
        }

        // If JSON contains object (new format), apply overrides
        if (is_array($jsonFields) && !isset($jsonFields[0])) {
            // New format: { "price": { "xtype": "numberfield", "label": "Price" } }
            foreach ($jsonFields as $fieldName => $override) {
                if (isset($fieldsMap[$fieldName])) {
                    $fieldsMap[$fieldName] = array_merge($fieldsMap[$fieldName], $override);
                }
            }
        } else {
            // Old format: array of fields
            // In this case, just add fields from JSON that are not in model
            foreach ($jsonFields as $jsonField) {
                $name = $jsonField['name'] ?? null;
                if ($name && !isset($fieldsMap[$name])) {
                    $fieldsMap[$name] = $jsonField;
                }
            }
        }

        return array_values($fieldsMap);
    }

    /**
     * Get lexicon value with fallback logic
     *
     * Logic:
     * 1. Try to load for current admin language
     * 2. If not found - try English (en)
     * 3. If not found - return the key itself
     *
     * @param string $key Lexicon key
     * @param string $namespace Lexicon namespace (default: minishop3)
     * @param string $topic Lexicon topic (default: default)
     * @return string
     */
    protected function getLexiconValue(string $key, string $namespace = 'minishop3', string $topic = 'default'): string
    {
        // Determine current admin language
        $currentLanguage = $this->modx->getOption('cultureKey', null, 'en');
        if (!empty($this->modx->cultureKey)) {
            $currentLanguage = $this->modx->cultureKey;
        }

        // Direct lexicon loading from file (bypass MODX cache)
        $lexiconPath = MODX_CORE_PATH . "components/{$namespace}/lexicon/{$currentLanguage}/{$topic}.inc.php";

        if (file_exists($lexiconPath)) {
            $_lang = [];
            include $lexiconPath;

            if (isset($_lang[$key])) {
                return $_lang[$key];
            }
        }

        // If not found in current language and it's not English - try English
        if ($currentLanguage !== 'en') {
            $lexiconPathEn = MODX_CORE_PATH . "components/{$namespace}/lexicon/en/{$topic}.inc.php";

            if (file_exists($lexiconPathEn)) {
                $_lang = [];
                include $lexiconPathEn;

                if (isset($_lang[$key])) {
                    return $_lang[$key];
                }
            }
        }

        // If not found anywhere - return the key itself
        return $key;
    }

    /**
     * Apply lexicon to fields (convert label_key and description_key to label and description)
     *
     * @param array $fields
     * @return array
     */
    public function applyLexicon(array $fields): array
    {
        foreach ($fields as &$field) {
            // Convert label_key to label with fallback logic
            if (isset($field['label_key'])) {
                $field['label'] = $this->getLexiconValue($field['label_key'], 'minishop3', 'product');
                // If lexicon returned key (translation not found), use auto-generated label
                if ($field['label'] === $field['label_key']) {
                    $field['label'] = $this->generateLabel($field['name'] ?? '');
                }
                unset($field['label_key']);
            }

            // Convert description_key to description with fallback logic
            if (isset($field['description_key'])) {
                $field['description'] = $this->getLexiconValue($field['description_key'], 'minishop3', 'product');
                // If lexicon returned key (translation not found), leave empty
                if ($field['description'] === $field['description_key']) {
                    $field['description'] = '';
                }
                unset($field['description_key']);
            }

            // If no label_key or label - generate
            if (!isset($field['label'])) {
                $field['label'] = $this->generateLabel($field['name'] ?? '');
            }

            // If no description - empty string
            if (!isset($field['description'])) {
                $field['description'] = '';
            }
        }

        return $fields;
    }

    /**
     * Process sections: apply lexicon to label
     *
     * @param array $sections
     * @return array
     */
    protected function processSections(array $sections): array
    {
        $this->modx->lexicon->load('minishop3:product');

        $result = [];

        foreach ($sections as $key => $section) {
            // Apply lexicon to section label
            if (isset($section['label_key'])) {
                $section['label'] = $this->modx->lexicon($section['label_key']);
                // If lexicon not found, use key as is
                if ($section['label'] === $section['label_key']) {
                    $section['label'] = ucfirst(str_replace('_', ' ', $key));
                }
                unset($section['label_key']);
            }

            // Add section key
            $section['key'] = $key;

            // Set defaults
            $section['collapsed'] = $section['collapsed'] ?? false;
            $section['order'] = $section['order'] ?? 0;

            $result[$key] = $section;
        }

        // Sort by order
        uasort($result, function($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });

        return $result;
    }
}
