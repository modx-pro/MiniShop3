<?php

namespace MiniShop3\Services;

use MODX\Revolution\modX;
use MiniShop3\Model\msGridField;

/**
 * Сервис для управления конфигурацией гридов
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
     * Получить конфигурацию колонок грида
     *
     * @param string $gridKey Ключ грида (customers, orders, products и т.д.)
     * @return array
     */
    public function getGridConfig(string $gridKey): array
    {
        $query = $this->modx->newQuery(msGridField::class);
        $query->where([
            'grid_key' => $gridKey,
            'visible' => true,
        ]);
        $query->sortby('sort_order', 'ASC');

        $fields = [];
        $collection = $this->modx->getCollection(msGridField::class, $query);

        foreach ($collection as $field) {
            $config = $field->get('config');

            // xPDO 3 с phptype='json' автоматически десериализует JSON в массив
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

            // Мерджим дополнительную конфигурацию из JSON
            if (!empty($config)) {
                $fieldData = array_merge($fieldData, $config);
            }

            $fields[] = $fieldData;
        }

        return $fields;
    }

    /**
     * Получить label с учетом lexicon_key
     *
     * @param msGridField $field
     * @return string
     */
    protected function resolveLabel(msGridField $field): string
    {
        // Приоритет: прямой label > lexicon_key > field_name
        $label = $field->get('label');
        if (!empty($label)) {
            return $label;
        }

        $lexiconKey = $field->get('lexicon_key');
        if (!empty($lexiconKey)) {
            $this->modx->lexicon->load('minishop3:vue');
            $translated = $this->modx->lexicon($lexiconKey);

            // Если перевод найден (не вернулся сам ключ)
            if ($translated !== $lexiconKey) {
                return $translated;
            }
        }

        // Fallback - field_name
        return $field->get('field_name');
    }

    /**
     * Сохранить конфигурацию грида
     *
     * @param string $gridKey
     * @param array $fields
     * @return bool
     */
    public function saveGridConfig(string $gridKey, array $fields): bool
    {
        try {
            // Получаем список имён полей, которые нужно сохранить
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

                // Обновляем базовые параметры
                if (isset($fieldData['label'])) {
                    $field->set('label', $fieldData['label']);
                }
                if (isset($fieldData['visible'])) {
                    $field->set('visible', (bool)$fieldData['visible']);
                }
                // Используем индекс массива как sort_order для сохранения порядка drag & drop
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

                // Обновляем JSON config (дополнительные параметры)
                $config = [];
                $configKeys = ['template', 'type', 'format'];
                foreach ($configKeys as $key) {
                    if (isset($fieldData[$key])) {
                        $config[$key] = $fieldData[$key];
                    }
                }

                if (!empty($config)) {
                    $field->set('config', json_encode($config, JSON_UNESCAPED_UNICODE));
                }

                if (!$field->save()) {
                    $this->modx->log(modX::LOG_LEVEL_ERROR,
                        "[GridConfigService] Failed to save field: {$gridKey}.{$fieldName}");
                    return false;
                }
            }

            // Удаляем поля, которых нет в новом списке (только НЕ системные)
            $fieldsToDelete = $this->modx->getCollection(msGridField::class, [
                'grid_key' => $gridKey,
                'field_name:NOT IN' => $fieldNamesToKeep,
                'is_system' => false, // Защита системных полей
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
     * Удалить поле из конфигурации грида
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

            // Защита системных полей
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
     * Добавить новое поле в конфигурацию грида
     *
     * @param string $gridKey
     * @param array $data
     * @return array
     */
    public function addField(string $gridKey, array $data): array
    {
        try {
            // Базовая валидация
            if (empty($data['field_name'])) {
                return ['success' => false, 'message' => 'field_name is required'];
            }

            if (!preg_match('/^[a-z0-9_]+$/i', $data['field_name'])) {
                return ['success' => false, 'message' => 'field_name must contain only letters, numbers and underscores'];
            }

            // Проверка уникальности
            $exists = $this->modx->getObject(msGridField::class, [
                'grid_key' => $gridKey,
                'field_name' => $data['field_name'],
            ]);

            if ($exists) {
                return ['success' => false, 'message' => 'Field with this name already exists'];
            }

            // Валидация по типу
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
                    // Используем обновленный config с resolvedTableName
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
            }

            // Добавляем тип в config
            $config['type'] = $type;

            // Получаем максимальный sort_order
            $maxSortOrder = 0;
            $query = $this->modx->newQuery(msGridField::class);
            $query->where(['grid_key' => $gridKey]);
            $query->sortby('sort_order', 'DESC');
            $query->limit(1);
            $lastField = $this->modx->getObject(msGridField::class, $query);
            if ($lastField) {
                $maxSortOrder = (int)$lastField->get('sort_order');
            }

            // Создаём поле
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
     * Обновить существующее поле в конфигурации грида
     *
     * @param string $gridKey
     * @param string $fieldName
     * @param array $data
     * @return array
     */
    public function updateField(string $gridKey, string $fieldName, array $data): array
    {
        try {
            // Находим существующее поле
            $field = $this->modx->getObject(msGridField::class, [
                'grid_key' => $gridKey,
                'field_name' => $fieldName,
            ]);

            if (!$field) {
                return ['success' => false, 'message' => "Field not found: {$gridKey}.{$fieldName}"];
            }

            // Защита системных полей от изменения типа
            if ($field->get('is_system')) {
                return ['success' => false, 'message' => 'Cannot modify system field'];
            }

            // Получаем тип поля
            $type = $data['type'] ?? 'model';
            $config = $data['config'] ?? [];

            // Валидация по типу
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
                    // Используем обновленный config с resolvedTableName
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
            }

            // Добавляем тип в config
            $config['type'] = $type;

            // Обновляем поле
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

            // Обновляем JSON config
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
     * Валидация конфигурации Template поля
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
     * Валидация конфигурации Relation поля
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

        // Валидация агрегации
        $aggregation = $relation['aggregation'] ?? null;
        $allowedAggregations = ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'];

        if ($aggregation !== null && !in_array($aggregation, $allowedAggregations)) {
            return ['success' => false, 'message' => "Invalid aggregation type. Allowed: " . implode(', ', $allowedAggregations)];
        }

        // Определяем тип: модель или прямое имя таблицы
        $tableOrModel = $relation['table'];
        $isModel = strpos($tableOrModel, '\\') !== false || strpos($tableOrModel, '::') !== false;

        if ($isModel) {
            // Это класс модели - получаем имя таблицы
            try {
                $tableName = $this->modx->getTableName($tableOrModel);

                if (empty($tableName)) {
                    return ['success' => false, 'message' => "Unable to get table name for model: {$tableOrModel}"];
                }

                // Сохраняем разрешенное имя таблицы в конфиг для использования в query
                $config['relation']['resolvedTableName'] = $tableName;
            } catch (\Exception $e) {
                return ['success' => false, 'message' => "Invalid model class: {$tableOrModel}. " . $e->getMessage()];
            }
        } else {
            // Это прямое имя таблицы - используем как есть
            $config['relation']['resolvedTableName'] = $tableOrModel;
        }

        return ['success' => true, 'config' => $config];
    }

    /**
     * Валидация конфигурации Computed поля
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

        // Проверяем что класс существует
        if (!class_exists($computed['className'])) {
            return ['success' => false, 'message' => "Class {$computed['className']} not found"];
        }

        // Проверяем что класс реализует нужный интерфейс
        $interfaces = class_implements($computed['className']);
        if (!isset($interfaces['MiniShop3\\Interfaces\\ComputedFieldInterface'])) {
            return ['success' => false, 'message' => "Class must implement ComputedFieldInterface"];
        }

        return ['success' => true];
    }
}
