<?php

namespace MiniShop3\Services;

use MODX\Revolution\modX;
use MiniShop3\Model\msGridField;

/**
 * Service for managing grid configurations
 */
class GridConfigService
{
    /** @var modX */
    protected $modx;

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Get grid columns configuration
     *
     * @param string $gridKey Grid key (customers, orders, products, etc.)
     * @return array
     */
    public function getGridConfig(string $gridKey, bool $includeHidden = false): array
    {
        $query = $this->modx->newQuery(msGridField::class);
        $where = ['grid_key' => $gridKey];
        if (!$includeHidden) {
            $where['visible'] = true;
        }
        $query->where($where);
        $query->sortby('sort_order', 'ASC');

        $fields = [];
        $collection = $this->modx->getCollection(msGridField::class, $query);

        foreach ($collection as $field) {
            $config = $field->get('config');

            // xPDO 3 with phptype='json' automatically deserializes JSON to array
            if (is_string($config)) {
                $config = json_decode($config, true) ?: [];
            } elseif (!is_array($config)) {
                $config = [];
            }

            $fieldData = [
                'name' => $field->get('field_name'),
                'label' => $this->resolveLabel($field),
                'visible' => (bool)$field->get('visible'),
                'sortable' => (bool)$field->get('sortable'),
                'filterable' => (bool)$field->get('filterable'),
                'frozen' => (bool)$field->get('frozen'),
                'width' => $field->get('width'),
                'minWidth' => $field->get('min_width'),
                'isSystem' => (bool)$field->get('is_system'),
            ];

            // Merge additional configuration from JSON
            if (!empty($config)) {
                $fieldData = array_merge($fieldData, $config);
            }

            $fields[] = $fieldData;
        }

        return $fields;
    }

    /**
     * Get label considering lexicon_key
     *
     * @param msGridField $field
     * @return string
     */
    protected function resolveLabel(msGridField $field): string
    {
        // Priority: direct label > lexicon_key > field_name
        $label = $field->get('label');
        if (!empty($label)) {
            return $label;
        }

        $lexiconKey = $field->get('lexicon_key');
        if (!empty($lexiconKey)) {
            $this->modx->lexicon->load('minishop3:vue');
            $translated = $this->modx->lexicon($lexiconKey);

            // If translation found (key itself not returned)
            if ($translated !== $lexiconKey) {
                return $translated;
            }
        }

        // Fallback - field_name
        return $field->get('field_name');
    }

    /**
     * Save grid configuration
     *
     * @param string $gridKey
     * @param array $fields
     * @return bool
     */
    public function saveGridConfig(string $gridKey, array $fields): bool
    {
        try {
            // Get list of field names to keep
            $fieldNamesToKeep = [];

            foreach ($fields as $index => $fieldData) {
                $fieldName = $fieldData['name'] ?? null;

                if (!$fieldName) {
                    continue;
                }

                $fieldNamesToKeep[] = $fieldName;

                $field = $this->modx->getObject(msGridField::class, [
                    'grid_key' => $gridKey,
                    'field_name' => $fieldName,
                ]);

                if (!$field) {
                    $this->modx->log(modX::LOG_LEVEL_WARN,
                        "[GridConfigService] Field not found: {$gridKey}.{$fieldName}");
                    continue;
                }

                // Update basic parameters
                if (isset($fieldData['label'])) {
                    $field->set('label', $fieldData['label']);
                }
                if (isset($fieldData['visible'])) {
                    $field->set('visible', (bool)$fieldData['visible']);
                }
                // Use array index as sort_order to preserve drag & drop order
                $field->set('sort_order', $index);

                if (isset($fieldData['sortable'])) {
                    $field->set('sortable', (bool)$fieldData['sortable']);
                }
                if (isset($fieldData['filterable'])) {
                    $field->set('filterable', (bool)$fieldData['filterable']);
                }
                if (isset($fieldData['frozen'])) {
                    $field->set('frozen', (bool)$fieldData['frozen']);
                }
                if (isset($fieldData['width'])) {
                    $field->set('width', $fieldData['width']);
                }
                if (isset($fieldData['minWidth'])) {
                    $field->set('min_width', $fieldData['minWidth']);
                }

                // Update JSON config (additional parameters)
                // Start with existing config to preserve values not sent
                $existingConfig = $field->get('config');
                if (is_string($existingConfig)) {
                    $existingConfig = json_decode($existingConfig, true) ?: [];
                } elseif (!is_array($existingConfig)) {
                    $existingConfig = [];
                }

                $config = $existingConfig;
                $configKeys = [
                    'template', 'type', 'format', 'actions',
                    // relation type
                    'relation',
                    // computed type
                    'computed',
                    // badge type
                    'source_field', 'color_field',
                    // datetime type
                    // (format is already included)
                    // price type
                    'decimals', 'currency', 'currency_position', 'thousands_separator', 'decimal_separator',
                    // weight type
                    'unit', 'unit_position',
                    // inline edit (category-products)
                    'editable', 'editor_type', 'editor_options', 'editor_reference', 'editor_combo_endpoint',
                ];
                foreach ($configKeys as $key) {
                    if (array_key_exists($key, $fieldData)) {
                        $config[$key] = $fieldData[$key];
                    }
                }

                if (($config['editor_type'] ?? '') !== GridColumnEditorType::COMBO) {
                    unset($config['editor_reference'], $config['editor_combo_endpoint']);
                }

                $comboCheck = GridEditorReferenceRegistry::validateComboEditorConfig($config);
                if (!$comboCheck['success']) {
                    $this->modx->log(modX::LOG_LEVEL_ERROR,
                        '[GridConfigService] Combo editor validation failed for ' . $gridKey . '.' . $fieldName . ': ' . ($comboCheck['message'] ?? ''));

                    return false;
                }

                $field->set('config', json_encode($config, JSON_UNESCAPED_UNICODE));

                if (!$field->save()) {
                    $this->modx->log(modX::LOG_LEVEL_ERROR,
                        "[GridConfigService] Failed to save field: {$gridKey}.{$fieldName}");
                    return false;
                }
            }

            // Delete fields not in new list (only non-system)
            $fieldsToDelete = $this->modx->getCollection(msGridField::class, [
                'grid_key' => $gridKey,
                'field_name:NOT IN' => $fieldNamesToKeep,
                'is_system' => false, // Protect system fields
            ]);

            foreach ($fieldsToDelete as $field) {
                $fieldName = $field->get('field_name');
                $this->modx->log(modX::LOG_LEVEL_INFO,
                    "[GridConfigService] Deleting field: {$gridKey}.{$fieldName}");

                if (!$field->remove()) {
                    $this->modx->log(modX::LOG_LEVEL_ERROR,
                        "[GridConfigService] Failed to delete field: {$gridKey}.{$fieldName}");
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,
                "[GridConfigService] Error saving grid config: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete field from grid configuration
     *
     * @param string $gridKey
     * @param string $fieldName
     * @return array
     */
    public function deleteField(string $gridKey, string $fieldName): array
    {
        try {
            $field = $this->modx->getObject(msGridField::class, [
                'grid_key' => $gridKey,
                'field_name' => $fieldName,
            ]);

            if (!$field) {
                return [
                    'success' => false,
                    'message' => "Field not found: {$gridKey}.{$fieldName}"
                ];
            }

            // Protect system fields
            if ($field->get('is_system')) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete system field'
                ];
            }

            if ($field->remove()) {
                $this->modx->log(modX::LOG_LEVEL_INFO,
                    "[GridConfigService] Field deleted: {$gridKey}.{$fieldName}");

                return [
                    'success' => true,
                    'message' => 'Field deleted successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to delete field from database'
                ];
            }
        } catch (\Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,
                "[GridConfigService] Error deleting field: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Error deleting field: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Add new field to grid configuration
     *
     * @param string $gridKey
     * @param array $data
     * @return array
     */
    public function addField(string $gridKey, array $data): array
    {
        try {
            // Basic validation
            if (empty($data['field_name'])) {
                return ['success' => false, 'message' => 'field_name is required'];
            }

            if (!preg_match('/^[a-z0-9_]+$/i', $data['field_name'])) {
                return ['success' => false, 'message' => 'field_name must contain only letters, numbers and underscores'];
            }

            // Check uniqueness
            $exists = $this->modx->getObject(msGridField::class, [
                'grid_key' => $gridKey,
                'field_name' => $data['field_name'],
            ]);

            if ($exists) {
                return ['success' => false, 'message' => 'Field with this name already exists'];
            }

            // Validation by type
            $type = $data['type'] ?? 'model';
            $config = $data['config'] ?? [];

            switch ($type) {
                case 'template':
                    $validation = $this->validateTemplateConfig($config);
                    if (!$validation['success']) {
                        return $validation;
                    }
                    break;

                case 'relation':
                    $validation = $this->validateRelationConfig($config);
                    if (!$validation['success']) {
                        return $validation;
                    }
                    // Use updated config with resolvedTableName
                    if (isset($validation['config'])) {
                        $config = $validation['config'];
                    }
                    break;

                case 'computed':
                    $validation = $this->validateComputedConfig($config);
                    if (!$validation['success']) {
                        return $validation;
                    }
                    break;

                case 'actions':
                    $validation = $this->validateActionsConfig($config);
                    if (!$validation['success']) {
                        return $validation;
                    }
                    break;
            }

            // Add type to config
            $config['type'] = $type;

            if (($config['editor_type'] ?? '') !== GridColumnEditorType::COMBO) {
                unset($config['editor_reference'], $config['editor_combo_endpoint']);
            }

            $comboCheck = GridEditorReferenceRegistry::validateComboEditorConfig($config);
            if (!$comboCheck['success']) {
                return ['success' => false, 'message' => $comboCheck['message'] ?? 'Invalid combo editor configuration'];
            }

            // Get maximum sort_order
            $maxSortOrder = 0;
            $query = $this->modx->newQuery(msGridField::class);
            $query->where(['grid_key' => $gridKey]);
            $query->sortby('sort_order', 'DESC');
            $query->limit(1);
            $lastField = $this->modx->getObject(msGridField::class, $query);
            if ($lastField) {
                $maxSortOrder = (int)$lastField->get('sort_order');
            }

            // Create field
            $field = $this->modx->newObject(msGridField::class);
            $field->fromArray([
                'grid_key' => $gridKey,
                'field_name' => $data['field_name'],
                'label' => $data['label'] ?? $data['field_name'],
                'visible' => $data['visible'] ?? true,
                'sortable' => $data['sortable'] ?? false,
                'filterable' => $data['filterable'] ?? false,
                'frozen' => $data['frozen'] ?? false,
                'width' => $data['width'] ?? null,
                'min_width' => $data['min_width'] ?? null,
                'sort_order' => $maxSortOrder + 1,
                'is_system' => false,
                'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
            ]);

            if ($field->save()) {
                $this->modx->log(modX::LOG_LEVEL_INFO,
                    "[GridConfigService] Field created: {$gridKey}.{$data['field_name']}");

                return [
                    'success' => true,
                    'message' => 'Field created successfully',
                    'field' => $field->toArray()
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to save field'];
            }

        } catch (\Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,
                "[GridConfigService] Error adding field: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Error adding field: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update existing field in grid configuration
     *
     * @param string $gridKey
     * @param string $fieldName
     * @param array $data
     * @return array
     */
    public function updateField(string $gridKey, string $fieldName, array $data): array
    {
        try {
            // Find existing field
            $field = $this->modx->getObject(msGridField::class, [
                'grid_key' => $gridKey,
                'field_name' => $fieldName,
            ]);

            if (!$field) {
                return ['success' => false, 'message' => "Field not found: {$gridKey}.{$fieldName}"];
            }

            // Protect system fields from type changes
            if ($field->get('is_system')) {
                return ['success' => false, 'message' => 'Cannot modify system field'];
            }

            // Get field type
            $type = $data['type'] ?? 'model';
            $config = $data['config'] ?? [];

            // Validation by type
            switch ($type) {
                case 'template':
                    $validation = $this->validateTemplateConfig($config);
                    if (!$validation['success']) {
                        return $validation;
                    }
                    break;
                case 'relation':
                    $validation = $this->validateRelationConfig($config);
                    if (!$validation['success']) {
                        return $validation;
                    }
                    // Use updated config with resolvedTableName
                    if (isset($validation['config'])) {
                        $config = $validation['config'];
                    }
                    break;
                case 'computed':
                    $validation = $this->validateComputedConfig($config);
                    if (!$validation['success']) {
                        return $validation;
                    }
                    break;
                case 'actions':
                    $validation = $this->validateActionsConfig($config);
                    if (!$validation['success']) {
                        return $validation;
                    }
                    break;
            }

            // Add type to config
            $config['type'] = $type;

            if (($config['editor_type'] ?? '') !== GridColumnEditorType::COMBO) {
                unset($config['editor_reference'], $config['editor_combo_endpoint']);
            }

            $comboCheck = GridEditorReferenceRegistry::validateComboEditorConfig($config);
            if (!$comboCheck['success']) {
                return ['success' => false, 'message' => $comboCheck['message'] ?? 'Invalid combo editor configuration'];
            }

            // Update field
            if (isset($data['label'])) {
                $field->set('label', $data['label']);
            }
            if (isset($data['visible'])) {
                $field->set('visible', (bool)$data['visible']);
            }
            if (isset($data['sortable'])) {
                $field->set('sortable', (bool)$data['sortable']);
            }
            if (isset($data['filterable'])) {
                $field->set('filterable', (bool)$data['filterable']);
            }
            if (isset($data['frozen'])) {
                $field->set('frozen', (bool)$data['frozen']);
            }
            if (isset($data['width'])) {
                $field->set('width', $data['width'] ?: null);
            }

            // Update JSON config
            $field->set('config', json_encode($config, JSON_UNESCAPED_UNICODE));

            if ($field->save()) {
                $this->modx->log(modX::LOG_LEVEL_INFO,
                    "[GridConfigService] Field updated: {$gridKey}.{$fieldName}");

                return [
                    'success' => true,
                    'message' => 'Field updated successfully',
                    'field' => $field->toArray()
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to save field'];
            }

        } catch (\Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,
                "[GridConfigService] Error updating field: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Error updating field: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate Template field configuration
     *
     * @param array $config
     * @return array
     */
    protected function validateTemplateConfig(array $config): array
    {
        if (empty($config['template'])) {
            return ['success' => false, 'message' => 'template is required for template field'];
        }

        if (!is_string($config['template'])) {
            return ['success' => false, 'message' => 'template must be a string'];
        }

        return ['success' => true];
    }

    /**
     * Validate Relation field configuration
     *
     * @param array $config
     * @return array
     */
    protected function validateRelationConfig(array $config): array
    {
        $relation = $config['relation'] ?? [];

        if (empty($relation['table'])) {
            return ['success' => false, 'message' => 'relation.table is required'];
        }

        if (empty($relation['foreignKey'])) {
            return ['success' => false, 'message' => 'relation.foreignKey is required'];
        }

        if (empty($relation['displayField'])) {
            return ['success' => false, 'message' => 'relation.displayField is required'];
        }

        // Validate aggregation
        $aggregation = $relation['aggregation'] ?? null;
        $allowedAggregations = ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'];

        if ($aggregation !== null && !in_array($aggregation, $allowedAggregations)) {
            return ['success' => false, 'message' => "Invalid aggregation type. Allowed: " . implode(', ', $allowedAggregations)];
        }

        // Determine type: model or direct table name
        $tableOrModel = $relation['table'];
        $isModel = strpos($tableOrModel, '\\') !== false || strpos($tableOrModel, '::') !== false;

        if ($isModel) {
            // This is model class - get table name
            try {
                $tableName = $this->modx->getTableName($tableOrModel);

                if (empty($tableName)) {
                    return ['success' => false, 'message' => "Unable to get table name for model: {$tableOrModel}"];
                }

                // Save resolved table name in config for query usage
                $config['relation']['resolvedTableName'] = $tableName;
            } catch (\Exception $e) {
                return ['success' => false, 'message' => "Invalid model class: {$tableOrModel}. " . $e->getMessage()];
            }
        } else {
            // This is direct table name - add table prefix if not already present
            $tablePrefix = $this->modx->config['table_prefix'] ?? '';
            if ($tablePrefix !== '' && !str_starts_with($tableOrModel, $tablePrefix)) {
                $tableOrModel = $tablePrefix . $tableOrModel;
            }
            $config['relation']['resolvedTableName'] = $tableOrModel;
        }

        return ['success' => true, 'config' => $config];
    }

    /**
     * Validate Computed field configuration
     *
     * @param array $config
     * @return array
     */
    protected function validateComputedConfig(array $config): array
    {
        $computed = $config['computed'] ?? [];

        if (empty($computed['className'])) {
            return ['success' => false, 'message' => 'computed.className is required'];
        }

        // Check that class exists
        if (!class_exists($computed['className'])) {
            return ['success' => false, 'message' => "Class {$computed['className']} not found"];
        }

        // Check that class implements required interface
        $interfaces = class_implements($computed['className']);
        if (!isset($interfaces['MiniShop3\\Interfaces\\ComputedFieldInterface'])) {
            return ['success' => false, 'message' => "Class must implement ComputedFieldInterface"];
        }

        return ['success' => true];
    }

    /**
     * Get filterable fields from grid configuration
     *
     * @param string $gridKey Grid key
     * @return array Array of filterable field configs
     */
    public function getFilterableFields(string $gridKey): array
    {
        $query = $this->modx->newQuery(msGridField::class);
        $query->where([
            'grid_key' => $gridKey,
            'filterable' => true,
        ]);
        $query->sortby('sort_order', 'ASC');

        $filters = [];
        $collection = $this->modx->getCollection(msGridField::class, $query);

        foreach ($collection as $field) {
            $fieldName = $field->get('field_name');
            $config = $field->get('config');

            // Parse JSON config
            if (is_string($config)) {
                $config = json_decode($config, true) ?: [];
            } elseif (!is_array($config)) {
                $config = [];
            }

            $filters[$fieldName] = [
                'field_name' => $fieldName,
                'label' => $this->resolveLabel($field),
                'type' => $config['type'] ?? 'model',
                'config' => $config,
            ];
        }

        return $filters;
    }

    /**
     * Validate Actions field configuration
     *
     * @param array $config
     * @return array
     */
    protected function validateActionsConfig(array $config): array
    {
        $actions = $config['actions'] ?? [];

        // Actions can be empty array - this is allowed
        if (!is_array($actions)) {
            return ['success' => false, 'message' => 'actions must be an array'];
        }

        // Built-in Vue manager handlers (see vueManager/src/actionRegistry.js)
        $allowedHandlers = ['edit', 'delete', 'view', 'refresh', 'addresses', 'publish', 'duplicate'];
        $actionNames = [];

        foreach ($actions as $index => $action) {
            // Required field name
            if (empty($action['name'])) {
                return ['success' => false, 'message' => "actions[{$index}].name is required"];
            }

            // Check name uniqueness
            if (in_array($action['name'], $actionNames)) {
                return ['success' => false, 'message' => "Duplicate action name: {$action['name']}"];
            }
            $actionNames[] = $action['name'];

            // Required field handler
            if (empty($action['handler'])) {
                return ['success' => false, 'message' => "actions[{$index}].handler is required"];
            }

            // Handler must be either built-in or start with 'custom:'
            $handler = $action['handler'];
            if (!in_array($handler, $allowedHandlers) && strpos($handler, 'custom:') !== 0) {
                // Allow any handlers - they can be registered by plugins
                // But log warning
                $this->modx->log(modX::LOG_LEVEL_INFO,
                    "[GridConfigService] Custom handler used: {$handler} in action {$action['name']}");
            }

            // Validate severity if specified
            if (!empty($action['severity'])) {
                $allowedSeverities = ['secondary', 'success', 'info', 'warn', 'danger'];
                if (!in_array($action['severity'], $allowedSeverities)) {
                    return ['success' => false, 'message' => "Invalid severity for action {$action['name']}. Allowed: " . implode(', ', $allowedSeverities)];
                }
            }
        }

        return ['success' => true];
    }

    /**
     * Extract relation fields from grid config and group by table+foreignKey
     * for efficient JOIN building
     *
     * @param array $gridFields Array of grid field configs
     * @return array Grouped relation fields structure:
     *   [
     *     'table_foreignKey' => [
     *       'table' => 'msOrderStatus',
     *       'modelClass' => 'MiniShop3\Model\msOrderStatus',
     *       'foreignKey' => 'status_id',
     *       'alias' => 'rel_msOrderStatus_status_id',
     *       'fields' => [
     *         ['name' => 'status_name', 'displayField' => 'name'],
     *         ['name' => 'status_color', 'displayField' => 'color'],
     *       ]
     *     ]
     *   ]
     */
    public function extractRelationFields(array $gridFields): array
    {
        $relationGroups = [];

        foreach ($gridFields as $field) {
            // Skip non-relation fields
            if (($field['type'] ?? 'model') !== 'relation') {
                continue;
            }

            $relation = $field['relation'] ?? null;
            if (!$relation || empty($relation['table']) || empty($relation['foreignKey']) || empty($relation['displayField'])) {
                continue;
            }

            $table = $relation['table'];
            $foreignKey = $relation['foreignKey'];
            $displayField = $relation['displayField'];
            $fieldName = $field['name'];

            // Group key: table + foreignKey (same table with same FK = one JOIN)
            $groupKey = "{$table}_{$foreignKey}";

            if (!isset($relationGroups[$groupKey])) {
                // Resolve model class from table name
                $modelClass = $this->resolveModelClass($table);

                // Generate unique alias for this JOIN
                $alias = "rel_{$table}_{$foreignKey}";

                $relationGroups[$groupKey] = [
                    'table' => $table,
                    'modelClass' => $modelClass,
                    'foreignKey' => $foreignKey,
                    'alias' => $alias,
                    'fields' => [],
                ];
            }

            // Add field to this group
            $relationGroups[$groupKey]['fields'][] = [
                'name' => $fieldName,
                'displayField' => $displayField,
            ];
        }

        return $relationGroups;
    }

    /**
     * Resolve full model class name from short table name
     *
     * @param string $tableName Short table name (e.g. 'msOrderStatus')
     * @return string|null Full class name or null if not found
     */
    protected function resolveModelClass(string $tableName): ?string
    {
        // Known MiniShop3 model mappings
        $modelMap = [
            'msOrder' => 'MiniShop3\\Model\\msOrder',
            'msOrderStatus' => 'MiniShop3\\Model\\msOrderStatus',
            'msOrderAddress' => 'MiniShop3\\Model\\msOrderAddress',
            'msOrderProduct' => 'MiniShop3\\Model\\msOrderProduct',
            'msDelivery' => 'MiniShop3\\Model\\msDelivery',
            'msPayment' => 'MiniShop3\\Model\\msPayment',
            'msProduct' => 'MiniShop3\\Model\\msProduct',
            'msProductData' => 'MiniShop3\\Model\\msProductData',
            'msCategory' => 'MiniShop3\\Model\\msCategory',
            'msCategoryMember' => 'MiniShop3\\Model\\msCategoryMember',
            'msVendor' => 'MiniShop3\\Model\\msVendor',
            'msCustomer' => 'MiniShop3\\Model\\msCustomer',
            'modUser' => 'MODX\\Revolution\\modUser',
            'modUserProfile' => 'MODX\\Revolution\\modUserProfile',
            'modResource' => 'MODX\\Revolution\\modResource',
        ];

        return $modelMap[$tableName] ?? null;
    }
}
