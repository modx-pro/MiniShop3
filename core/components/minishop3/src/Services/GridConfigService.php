<?php

declare(strict_types=1);

namespace MiniShop3\Services;

use MiniShop3\Model\msGridField;
use MiniShop3\Services\Grid\GridColumnTypeValidator;
use MiniShop3\Services\Grid\GridConfigRepository;
use MiniShop3\Services\Grid\GridRelationFieldExtractor;
use MODX\Revolution\modX;

/**
 * Facade: grid column configuration for manager grids.
 *
 * Persistence → {@see GridConfigRepository}; type rules → {@see GridColumnTypeValidator}.
 */
class GridConfigService
{
    protected modX $modx;

    private GridConfigRepository $repository;

    private GridColumnTypeValidator $typeValidator;

    private GridRelationFieldExtractor $relationExtractor;

    /**
     * Request-scoped memo for getGridConfig(): gridKey:includeHidden → column config.
     *
     * This cache is safe only because GridConfigService is registered in the
     * MODX DI container as a request-scoped singleton (see ServiceRegistry):
     * one instance per request, never shared across requests. The memo must
     * never be persisted to a long-lived process cache or serialized — it
     * holds no TTL and would leak stale data across requests. Mutation
     * methods (saveGridConfig, addField, updateField, deleteField) invalidate
     * the affected gridKey entries via invalidateGridConfigCache() so the next
     * read re-loads from the database.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $gridConfigCache = [];

    /** @var list<string> */
    private const SAVE_CONFIG_KEYS = [
        'template', 'type', 'format', 'actions',
        'relation',
        'computed',
        'option',
        'source_field', 'color_field',
        'decimals', 'currency', 'currency_position', 'thousands_separator', 'decimal_separator',
        'unit', 'unit_position',
        'editable', 'editor_type', 'editor_options', 'editor_reference', 'editor_combo_endpoint',
    ];

    public function __construct(
        modX $modx,
        ?GridConfigRepository $repository = null,
        ?GridColumnTypeValidator $typeValidator = null,
        ?GridRelationFieldExtractor $relationExtractor = null,
    ) {
        $this->modx = $modx;
        $this->repository = $repository ?? new GridConfigRepository($modx);
        $this->typeValidator = $typeValidator ?? new GridColumnTypeValidator($modx);
        $this->relationExtractor = $relationExtractor ?? new GridRelationFieldExtractor();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getGridConfig(string $gridKey, bool $includeHidden = false): array
    {
        $cacheKey = $this->gridConfigCacheKey($gridKey, $includeHidden);
        if (array_key_exists($cacheKey, $this->gridConfigCache)) {
            return $this->gridConfigCache[$cacheKey];
        }

        $fields = $this->loadGridConfig($gridKey, $includeHidden);
        $this->gridConfigCache[$cacheKey] = $fields;

        return $fields;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadGridConfig(string $gridKey, bool $includeHidden): array
    {
        $fields = [];

        foreach ($this->repository->findByGridKey($gridKey, $includeHidden) as $field) {
            $config = $this->repository->decodeConfig($field->get('config'));

            $fieldData = [
                'name' => $field->get('field_name'),
                'label' => $this->resolveLabel($field),
                'visible' => (bool) $field->get('visible'),
                'sortable' => (bool) $field->get('sortable'),
                'filterable' => (bool) $field->get('filterable'),
                'frozen' => (bool) $field->get('frozen'),
                'width' => $field->get('width'),
                'minWidth' => $field->get('min_width'),
                'isSystem' => (bool) $field->get('is_system'),
            ];

            if ($config !== []) {
                $fieldData = array_merge($fieldData, $config);
            }

            $fields[] = $fieldData;
        }

        return $fields;
    }

    private function gridConfigCacheKey(string $gridKey, bool $includeHidden): string
    {
        return $gridKey . ':' . ($includeHidden ? '1' : '0');
    }

    /**
     * Call at the start of any msGridField mutation so request-scoped cache stays consistent.
     */
    private function beginGridMutation(string $gridKey): void
    {
        $this->invalidateGridConfigCache($gridKey);
    }

    private function invalidateGridConfigCache(string $gridKey): void
    {
        unset(
            $this->gridConfigCache[$this->gridConfigCacheKey($gridKey, false)],
            $this->gridConfigCache[$this->gridConfigCacheKey($gridKey, true)]
        );
    }

    protected function resolveLabel(msGridField $field): string
    {
        $label = $field->get('label');
        if (!empty($label)) {
            return $label;
        }

        $lexiconKey = $field->get('lexicon_key');
        if (!empty($lexiconKey)) {
            $this->modx->lexicon->load('minishop3:vue');
            $translated = $this->modx->lexicon($lexiconKey);
            if ($translated !== $lexiconKey) {
                return $translated;
            }
        }

        return $field->get('field_name');
    }

    /**
     * @param list<array<string, mixed>> $fields
     */
    public function saveGridConfig(string $gridKey, array $fields): bool
    {
        $this->beginGridMutation($gridKey);

        try {
            $fieldNamesToKeep = [];

            foreach ($fields as $index => $fieldData) {
                $fieldName = $fieldData['name'] ?? null;
                if (!$fieldName) {
                    continue;
                }

                $fieldNamesToKeep[] = $fieldName;
                $field = $this->repository->findOne($gridKey, $fieldName);
                if (!$field) {
                    $this->modx->log(
                        modX::LOG_LEVEL_WARN,
                        "[GridConfigService] Field not found: {$gridKey}.{$fieldName}"
                    );
                    continue;
                }

                $this->applySavePayload($field, $fieldData, $index);

                $config = $this->repository->decodeConfig($field->get('config'));
                foreach (self::SAVE_CONFIG_KEYS as $key) {
                    if (array_key_exists($key, $fieldData)) {
                        $config[$key] = $fieldData[$key];
                    }
                }

                $normalized = $this->normalizeComboEditorConfig($config);
                if (!$normalized['success']) {
                    $this->modx->log(
                        modX::LOG_LEVEL_ERROR,
                        '[GridConfigService] Combo editor validation failed for '
                        . $gridKey . '.' . $fieldName . ': ' . ($normalized['message'] ?? '')
                    );

                    return false;
                }

                $field->set('config', $this->repository->encodeConfig($normalized['config']));
                if (!$this->repository->save($field)) {
                    $this->modx->log(
                        modX::LOG_LEVEL_ERROR,
                        "[GridConfigService] Failed to save field: {$gridKey}.{$fieldName}"
                    );

                    return false;
                }
            }

            foreach ($this->repository->findNonSystemNotIn($gridKey, $fieldNamesToKeep) as $field) {
                $fieldName = $field->get('field_name');
                $this->modx->log(modX::LOG_LEVEL_INFO, "[GridConfigService] Deleting field: {$gridKey}.{$fieldName}");
                if (!$this->repository->remove($field)) {
                    $this->modx->log(
                        modX::LOG_LEVEL_ERROR,
                        "[GridConfigService] Failed to delete field: {$gridKey}.{$fieldName}"
                    );
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[GridConfigService] Error saving grid config: ' . $e->getMessage()
            );

            return false;
        }
    }

    /**
     * @param array<string, mixed> $fieldData
     */
    private function applySavePayload(msGridField $field, array $fieldData, int $index): void
    {
        if (isset($fieldData['label'])) {
            $field->set('label', $fieldData['label']);
        }
        if (isset($fieldData['visible'])) {
            $field->set('visible', (bool) $fieldData['visible']);
        }
        $field->set('sort_order', $index);
        if (isset($fieldData['sortable'])) {
            $field->set('sortable', (bool) $fieldData['sortable']);
        }
        if (isset($fieldData['filterable'])) {
            $field->set('filterable', (bool) $fieldData['filterable']);
        }
        if (isset($fieldData['frozen'])) {
            $field->set('frozen', (bool) $fieldData['frozen']);
        }
        if (isset($fieldData['width'])) {
            $field->set('width', $fieldData['width']);
        }
        if (isset($fieldData['minWidth'])) {
            $field->set('min_width', $fieldData['minWidth']);
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function deleteField(string $gridKey, string $fieldName): array
    {
        $this->beginGridMutation($gridKey);

        try {
            $field = $this->repository->findOne($gridKey, $fieldName);
            if (!$field) {
                return [
                    'success' => false,
                    'message' => "Field not found: {$gridKey}.{$fieldName}",
                ];
            }

            if ($field->get('is_system')) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete system field',
                ];
            }

            if ($this->repository->remove($field)) {
                $this->modx->log(
                    modX::LOG_LEVEL_INFO,
                    "[GridConfigService] Field deleted: {$gridKey}.{$fieldName}"
                );

                return [
                    'success' => true,
                    'message' => 'Field deleted successfully',
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to delete field from database',
            ];
        } catch (\Exception $e) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[GridConfigService] Error deleting field: ' . $e->getMessage()
            );

            return [
                'success' => false,
                'message' => 'Error deleting field: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, message: string, field?: array<string, mixed>}
     */
    public function addField(string $gridKey, array $data): array
    {
        $this->beginGridMutation($gridKey);

        try {
            if (empty($data['field_name'])) {
                return ['success' => false, 'message' => 'field_name is required'];
            }

            if (!preg_match('/^[a-z0-9_]+$/i', $data['field_name'])) {
                return [
                    'success' => false,
                    'message' => 'field_name must contain only letters, numbers and underscores',
                ];
            }

            if ($this->repository->findOne($gridKey, $data['field_name'])) {
                return ['success' => false, 'message' => 'Field with this name already exists'];
            }

            $prepared = $this->prepareTypedConfig(
                (string) ($data['type'] ?? 'model'),
                is_array($data['config'] ?? null) ? $data['config'] : [],
                (string) $data['field_name'],
                $gridKey
            );
            if (!$prepared['success']) {
                return $prepared;
            }

            $field = $this->repository->create([
                'grid_key' => $gridKey,
                'field_name' => $data['field_name'],
                'label' => $data['label'] ?? $data['field_name'],
                'visible' => $data['visible'] ?? true,
                'sortable' => $data['sortable'] ?? false,
                'filterable' => $data['filterable'] ?? false,
                'frozen' => $data['frozen'] ?? false,
                'width' => $data['width'] ?? null,
                'min_width' => $data['min_width'] ?? null,
                'sort_order' => $this->repository->nextSortOrder($gridKey),
                'is_system' => false,
                'config' => $this->repository->encodeConfig($prepared['config']),
            ]);

            if ($this->repository->save($field)) {
                $this->modx->log(
                    modX::LOG_LEVEL_INFO,
                    "[GridConfigService] Field created: {$gridKey}.{$data['field_name']}"
                );

                return [
                    'success' => true,
                    'message' => 'Field created successfully',
                    'field' => $field->toArray(),
                ];
            }

            return ['success' => false, 'message' => 'Failed to save field'];
        } catch (\Exception $e) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[GridConfigService] Error adding field: ' . $e->getMessage()
            );

            return [
                'success' => false,
                'message' => 'Error adding field: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, message: string, field?: array<string, mixed>}
     */
    public function updateField(string $gridKey, string $fieldName, array $data): array
    {
        $this->beginGridMutation($gridKey);

        try {
            $field = $this->repository->findOne($gridKey, $fieldName);
            if (!$field) {
                return ['success' => false, 'message' => "Field not found: {$gridKey}.{$fieldName}"];
            }

            if ($field->get('is_system')) {
                return ['success' => false, 'message' => 'Cannot modify system field'];
            }

            $prepared = $this->prepareTypedConfig(
                (string) ($data['type'] ?? 'model'),
                is_array($data['config'] ?? null) ? $data['config'] : [],
                (string) ($data['field_name'] ?? $fieldName),
                $gridKey
            );
            if (!$prepared['success']) {
                return $prepared;
            }

            if (isset($data['label'])) {
                $field->set('label', $data['label']);
            }
            if (isset($data['visible'])) {
                $field->set('visible', (bool) $data['visible']);
            }
            if (isset($data['sortable'])) {
                $field->set('sortable', (bool) $data['sortable']);
            }
            if (isset($data['filterable'])) {
                $field->set('filterable', (bool) $data['filterable']);
            }
            if (isset($data['frozen'])) {
                $field->set('frozen', (bool) $data['frozen']);
            }
            if (isset($data['width'])) {
                $field->set('width', $data['width'] ?: null);
            }

            $field->set('config', $this->repository->encodeConfig($prepared['config']));

            if ($this->repository->save($field)) {
                $this->modx->log(
                    modX::LOG_LEVEL_INFO,
                    "[GridConfigService] Field updated: {$gridKey}.{$fieldName}"
                );

                return [
                    'success' => true,
                    'message' => 'Field updated successfully',
                    'field' => $field->toArray(),
                ];
            }

            return ['success' => false, 'message' => 'Failed to save field'];
        } catch (\Exception $e) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[GridConfigService] Error updating field: ' . $e->getMessage()
            );

            return [
                'success' => false,
                'message' => 'Error updating field: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, array{field_name: string, label: string, type: mixed, config: array<string, mixed>}>
     */
    public function getFilterableFields(string $gridKey): array
    {
        $filters = [];

        foreach ($this->repository->findFilterableByGridKey($gridKey) as $field) {
            $fieldName = $field->get('field_name');
            $config = $this->repository->decodeConfig($field->get('config'));

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
     * @param list<array<string, mixed>> $gridFields
     * @return array<string, mixed>
     */
    public function extractRelationFields(array $gridFields): array
    {
        return $this->relationExtractor->extract($gridFields);
    }

    /**
     * Type rules + combo editor cleanup for add/update payloads.
     *
     * @param array<string, mixed> $config
     * @return array{success: bool, message?: string, config?: array<string, mixed>}
     */
    private function prepareTypedConfig(string $type, array $config, string $fieldName, string $gridKey = ''): array
    {
        $validation = $this->typeValidator->validateForType($type, $config, $fieldName, $gridKey);
        if (!$validation['success']) {
            return $validation;
        }
        if (isset($validation['config'])) {
            $config = $validation['config'];
        }

        $config['type'] = $type;

        return $this->normalizeComboEditorConfig($config);
    }

    /**
     * @param array<string, mixed> $config
     * @return array{success: bool, message?: string, config?: array<string, mixed>}
     */
    private function normalizeComboEditorConfig(array $config): array
    {
        if (($config['editor_type'] ?? '') !== GridColumnEditorType::COMBO) {
            unset($config['editor_reference'], $config['editor_combo_endpoint']);
        }

        $comboCheck = GridEditorReferenceRegistry::validateComboEditorConfig($config);
        if (!$comboCheck['success']) {
            return [
                'success' => false,
                'message' => $comboCheck['message'] ?? 'Invalid combo editor configuration',
            ];
        }

        return ['success' => true, 'config' => $config];
    }
}
