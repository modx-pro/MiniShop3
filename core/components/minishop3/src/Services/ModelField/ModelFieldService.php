<?php

namespace MiniShop3\Services\ModelField;

use MiniShop3\Model\msModelField;
use MiniShop3\Services\ComboConfigManager;
use MODX\Revolution\modX;

/**
 * CRUD and helpers for msModelField configuration.
 */
class ModelFieldService
{
    private const COMBO_XTYPES = ['combo', 'combobox', 'select', 'dropdown'];

    private const UPDATE_FIELDS = [
        'model',
        'name',
        'label',
        'xtype',
        'visible',
        'required',
        'sort_order',
        'section_id',
        'width',
        'placeholder',
        'description',
        'config',
    ];

    private modX $modx;
    private ModelFieldSectionService $sectionService;

    public function __construct(modX $modx, ModelFieldSectionService $sectionService)
    {
        $this->modx = $modx;
        $this->sectionService = $sectionService;
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>, message?: string, created?: true}
     */
    public function getList(array $params = []): array
    {
        $this->modx->lexicon->load('minishop3:vue');

        $start = (int)($params['start'] ?? 0);
        $limit = (int)($params['limit'] ?? 100);

        $criteria = [];
        if (!empty($params['model'])) {
            $criteria['model'] = $params['model'];
        }

        $total = $this->modx->getCount(msModelField::class, $criteria);

        $query = $this->modx->newQuery(msModelField::class);
        if (!empty($criteria)) {
            $query->where($criteria);
        }
        $query->sortby('model', 'ASC');
        $query->sortby('sort_order', 'ASC');
        $query->limit($limit, $start);

        $sections = !empty($params['model'])
            ? $this->sectionService->getSectionsForModel($params['model'])
            : [];

        $results = [];
        foreach ($this->modx->getIterator(msModelField::class, $query) as $field) {
            $results[] = $this->formatField($field, $sections);
        }

        return $this->ok([
            'results' => $results,
            'total' => $total,
            'sections' => $sections,
        ]);
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>, message?: string, created?: true}
     */
    public function get(array $params = []): array
    {
        $field = $this->requireField((int)($params['id'] ?? 0));
        if (!$field instanceof msModelField) {
            return $field;
        }

        return $this->ok($this->formatField($field));
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>, message?: string, created?: true}
     */
    public function create(array $params = []): array
    {
        $model = $params['model'] ?? '';
        $name = $params['name'] ?? '';

        if (empty($model) || empty($name)) {
            return $this->fail('Model and name are required');
        }

        $invalid = $this->rejectInvalidModel($model);
        if ($invalid !== null) {
            return $invalid;
        }

        $existing = $this->modx->getObject(msModelField::class, [
            'model' => $model,
            'name' => $name,
        ]);
        if ($existing) {
            return $this->fail('Field with this name already exists for this model');
        }

        $field = $this->modx->newObject(msModelField::class);
        $field->fromArray([
            'model' => $model,
            'name' => $name,
            'label' => $params['label'] ?? null,
            'xtype' => $params['xtype'] ?? 'textfield',
            'visible' => isset($params['visible']) ? (bool)$params['visible'] : true,
            'required' => isset($params['required']) ? (bool)$params['required'] : false,
            'sort_order' => (int)($params['sort_order'] ?? 0),
            'section_id' => isset($params['section_id']) ? (int)$params['section_id'] : null,
            'width' => (int)($params['width'] ?? 6),
            'placeholder' => $params['placeholder'] ?? null,
            'description' => $params['description'] ?? null,
            'config' => $params['config'] ?? null,
        ]);

        if (!$field->save()) {
            return $this->fail('Failed to create field', 'server_error');
        }

        return $this->ok($this->formatField($field), 'Field created successfully', true);
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>, message?: string, created?: true}
     */
    public function update(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);
        $field = $this->requireField($id);
        if (!$field instanceof msModelField) {
            return $field;
        }

        if (isset($params['name']) || isset($params['model'])) {
            $newModel = $params['model'] ?? $field->get('model');
            $newName = $params['name'] ?? $field->get('name');

            if ($newModel !== $field->get('model') || $newName !== $field->get('name')) {
                $existing = $this->modx->getObject(msModelField::class, [
                    'model' => $newModel,
                    'name' => $newName,
                    'id:!=' => $id,
                ]);
                if ($existing) {
                    return $this->fail('Field with this name already exists for this model');
                }
            }
        }

        if (isset($params['model'])) {
            $invalid = $this->rejectInvalidModel($params['model']);
            if ($invalid !== null) {
                return $invalid;
            }
        }

        foreach (self::UPDATE_FIELDS as $fieldName) {
            if (!array_key_exists($fieldName, $params)) {
                continue;
            }
            $field->set($fieldName, $this->castUpdateValue($fieldName, $params[$fieldName]));
        }

        if (!$field->save()) {
            return $this->fail('Failed to update field', 'server_error');
        }

        return $this->ok($this->formatField($field), 'Field updated successfully');
    }

    /**
     * @return array{success: bool, data?: mixed, message?: string, created?: true}
     */
    public function delete(array $params = []): array
    {
        $field = $this->requireField((int)($params['id'] ?? 0));
        if (!$field instanceof msModelField) {
            return $field;
        }

        if (!$field->remove()) {
            return $this->fail('Failed to delete field', 'server_error');
        }

        return $this->ok(null, 'Field deleted successfully');
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>}
     */
    public function getModels(): array
    {
        $models = [];
        foreach (msModelField::getAvailableModels() as $model) {
            $models[] = [
                'value' => $model,
                'label' => $this->resolveModelLabel($model),
            ];
        }

        return $this->ok(['models' => $models]);
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>, message?: string, created?: true}
     */
    public function getVisibleFields(array $params = []): array
    {
        $model = $params['model'] ?? '';
        if (empty($model)) {
            return $this->fail('Model is required');
        }

        $invalid = $this->rejectInvalidModel($model);
        if ($invalid !== null) {
            return $invalid;
        }

        $this->modx->lexicon->load('minishop3:vue');
        $this->modx->lexicon->load('minishop3:default');
        $this->modx->lexicon->load('minishop3:manager');

        $sectionsById = [];
        foreach ($this->sectionService->getSectionsForModel($model) as $section) {
            if (!$section['hidden']) {
                $sectionsById[$section['id']] = $section;
            }
        }

        $query = $this->modx->newQuery(msModelField::class);
        $query->where([
            'model' => $model,
            'visible' => true,
        ]);
        $query->sortby('sort_order', 'ASC');

        $results = [];
        $fieldsWithProperties = [];
        foreach ($this->modx->getIterator(msModelField::class, $query) as $field) {
            $data = $field->toArray();

            $label = $data['label'] ?? $data['name'];
            $data['label_display'] = $this->translateMs3Key($label) ?? $label;

            $description = $data['description'] ?? '';
            $data['description_display'] = $this->translateMs3Key($description) ?? $description;

            if (!empty($data['section_id']) && isset($sectionsById[$data['section_id']])) {
                $data['section'] = $sectionsById[$data['section_id']];
            }

            $results[] = $data;

            if (in_array($data['xtype'] ?? '', self::COMBO_XTYPES)) {
                $fieldsWithProperties[] = [
                    'name' => $data['name'],
                    'properties' => $data['config'] ?? null,
                ];
            }
        }

        $comboOptionsWithMeta = (new ComboConfigManager($this->modx))
            ->resolveAllOptionsWithMeta($model, $fieldsWithProperties);

        return $this->ok([
            'results' => $results,
            'total' => count($results),
            'model' => $model,
            'sections' => array_values($sectionsById),
            'comboOptions' => $comboOptionsWithMeta,
        ]);
    }

    /**
     * @return array{success: bool, data?: mixed, message?: string, created?: true}
     */
    public function updateRanks(array $params = []): array
    {
        $ranks = $params['ranks'] ?? [];
        if (empty($ranks) || !is_array($ranks)) {
            return $this->fail('Ranks array is required');
        }

        foreach ($ranks as $item) {
            if (!isset($item['id'], $item['sort_order'])) {
                continue;
            }

            $field = $this->modx->getObject(msModelField::class, (int)$item['id']);
            if ($field) {
                $field->set('sort_order', (int)$item['sort_order']);
                $field->save();
            }
        }

        return $this->ok(null, 'Ranks updated successfully');
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>, message?: string, created?: true}
     */
    public function getComboOptions(array $params = []): array
    {
        $model = $params['model'] ?? '';
        if (empty($model)) {
            return $this->fail('Model is required');
        }

        $invalid = $this->rejectInvalidModel($model);
        if ($invalid !== null) {
            return $invalid;
        }

        $query = $this->modx->newQuery(msModelField::class);
        $query->where([
            'model' => $model,
            'xtype:IN' => self::COMBO_XTYPES,
        ]);

        $fieldsWithProperties = [];
        foreach ($this->modx->getIterator(msModelField::class, $query) as $field) {
            $fieldsWithProperties[] = [
                'name' => $field->get('name'),
                'properties' => $field->get('config'),
            ];
        }

        $options = (new ComboConfigManager($this->modx))
            ->resolveAllOptionsWithMeta($model, $fieldsWithProperties);

        return $this->ok([
            'model' => $model,
            'options' => $options,
        ]);
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>, message?: string, created?: true}
     */
    public function getFieldComboOptions(array $params = []): array
    {
        $model = $params['model'] ?? '';
        $fieldName = $params['field_name'] ?? '';

        if (empty($model) || empty($fieldName)) {
            return $this->fail('Model and field_name are required');
        }

        $invalid = $this->rejectInvalidModel($model);
        if ($invalid !== null) {
            return $invalid;
        }

        $field = $this->modx->getObject(msModelField::class, [
            'model' => $model,
            'name' => $fieldName,
        ]);

        $dbProperties = null;
        if ($field) {
            $dbProperties = $field->get('config');
            if (is_string($dbProperties)) {
                $dbProperties = json_decode($dbProperties, true);
            }
        }

        $options = (new ComboConfigManager($this->modx))
            ->resolveOptions($model, $fieldName, $dbProperties);

        return $this->ok([
            'model' => $model,
            'field_name' => $fieldName,
            'options' => $options,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     * @return array<string, mixed>
     */
    public function formatField(msModelField $field, array $sections = []): array
    {
        $data = $field->toArray();

        $translatedLabel = $this->translateMs3Key($data['label'] ?? null);
        if ($translatedLabel !== null) {
            $data['label_translated'] = $translatedLabel;
        }

        $translatedDescription = $this->translateMs3Key($data['description'] ?? null);
        if ($translatedDescription !== null) {
            $data['description_translated'] = $translatedDescription;
        }

        $data['model_label'] = $this->resolveModelLabel((string)$data['model']);

        if (!empty($data['section_id']) && !empty($sections)) {
            foreach ($sections as $section) {
                if ($section['id'] == $data['section_id']) {
                    $data['section_name'] = $section['label'];
                    break;
                }
            }
        }

        return $data;
    }

    /**
     * @return msModelField|array{success: false, message: string, error: string}
     */
    private function requireField(int $id): msModelField|array
    {
        if (!$id) {
            return $this->fail('Field ID is required');
        }

        $field = $this->modx->getObject(msModelField::class, $id);
        if (!$field instanceof msModelField) {
            return $this->fail('Field not found', 'not_found');
        }

        return $field;
    }

    /**
     * @return array{success: false, message: string, error: string}|null
     */
    private function rejectInvalidModel(string $model): ?array
    {
        if (!in_array($model, msModelField::getAvailableModels())) {
            return $this->fail('Invalid model type');
        }

        return null;
    }

    private function castUpdateValue(string $fieldName, mixed $value): mixed
    {
        if (in_array($fieldName, ['visible', 'required'], true)) {
            return (bool)$value;
        }
        if (in_array($fieldName, ['sort_order', 'width'], true)) {
            return (int)$value;
        }
        if ($fieldName === 'section_id') {
            return $value ? (int)$value : null;
        }

        return $value;
    }

    /**
     * Translate ms3_* lexicon key; null when not a key or untranslated.
     */
    private function translateMs3Key(?string $value): ?string
    {
        if ($value === null || $value === '' || !str_starts_with($value, 'ms3_')) {
            return null;
        }

        $translated = $this->modx->lexicon($value);

        return $translated !== $value ? $translated : null;
    }

    private function resolveModelLabel(string $model): string
    {
        $lexiconKey = 'ms3_model_' . strtolower(str_replace('ms', '', $model));
        $label = $this->modx->lexicon($lexiconKey);

        return $label !== $lexiconKey ? $label : $model;
    }

    /**
     * @return array{success: true, data: mixed, message?: string, created?: true}
     */
    private function ok(mixed $data = null, ?string $message = null, bool $created = false): array
    {
        $result = [
            'success' => true,
            'data' => $data,
        ];
        if ($message !== null) {
            $result['message'] = $message;
        }
        if ($created) {
            $result['created'] = true;
        }

        return $result;
    }

    /**
     * @param 'bad_request'|'not_found'|'server_error' $error
     * @return array{success: false, message: string, error: string}
     */
    private function fail(string $message, string $error = 'bad_request'): array
    {
        return [
            'success' => false,
            'message' => $message,
            'error' => $error,
        ];
    }
}
