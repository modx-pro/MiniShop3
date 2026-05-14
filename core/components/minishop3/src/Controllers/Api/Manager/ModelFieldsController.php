<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msModelField;
use MiniShop3\Model\msModelFieldSection;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\ComboConfigManager;
use MODX\Revolution\modX;

/**
 * API controller for managing model fields configuration
 *
 * Handles CRUD operations for msModelField and msModelFieldSection in admin panel.
 * Allows configuration of visible fields for msOrder, msOrderAddress, msOrderProduct.
 *
 * @package MiniShop3\Controllers\Api\Manager
 */
class ModelFieldsController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Get model fields list
     * GET /api/mgr/model-fields
     *
     * @param array $params URL parameters (model, start, limit)
     * @return array Response
     */
    public function getList(array $params = []): array
    {
        // Load lexicon for label translation
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

        // Load sections for this model
        $sectionModel = $params['model'] ?? null;
        $sections = [];
        if ($sectionModel) {
            $sections = $this->getSectionsForModel($sectionModel);
        }

        $results = [];
        foreach ($this->modx->getIterator(msModelField::class, $query) as $field) {
            $results[] = $this->formatField($field, $sections);
        }

        return Response::success([
            'results' => $results,
            'total' => $total,
            'sections' => $sections
        ])->getData();
    }

    /**
     * Get sections for a specific model
     *
     * @param string $model
     * @return array
     */
    protected function getSectionsForModel(string $model): array
    {
        $sections = [];
        $query = $this->modx->newQuery(msModelFieldSection::class);
        $query->where(['model' => $model]);
        $query->sortby('sort_order', 'ASC');

        foreach ($this->modx->getIterator(msModelFieldSection::class, $query) as $section) {
            $sections[] = $this->formatSection($section);
        }

        return $sections;
    }

    /**
     * Get specific model field
     * GET /api/mgr/model-fields/{id}
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function get(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Field ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $field = $this->modx->getObject(msModelField::class, $id);

        if (!$field) {
            return Response::error('Field not found', HttpStatus::NOT_FOUND)->getData();
        }

        return Response::success($this->formatField($field))->getData();
    }

    /**
     * Create new model field
     * POST /api/mgr/model-fields
     *
     * @param array $params Field data
     * @return array Response
     */
    public function create(array $params = []): array
    {
        $model = $params['model'] ?? '';
        $name = $params['name'] ?? '';

        if (empty($model) || empty($name)) {
            return Response::error('Model and name are required', HttpStatus::BAD_REQUEST)->getData();
        }

        if (!in_array($model, msModelField::getAvailableModels())) {
            return Response::error('Invalid model type', HttpStatus::BAD_REQUEST)->getData();
        }

        // Check if field already exists
        $existing = $this->modx->getObject(msModelField::class, [
            'model' => $model,
            'name' => $name,
        ]);

        if ($existing) {
            return Response::error('Field with this name already exists for this model', HttpStatus::BAD_REQUEST)->getData();
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
            return Response::error('Failed to create field', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success($this->formatField($field), 'Field created successfully', HttpStatus::CREATED)->getData();
    }

    /**
     * Update model field
     * PUT /api/mgr/model-fields/{id}
     *
     * @param array $params Field data with id
     * @return array Response
     */
    public function update(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Field ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $field = $this->modx->getObject(msModelField::class, $id);

        if (!$field) {
            return Response::error('Field not found', HttpStatus::NOT_FOUND)->getData();
        }

        // Check uniqueness if name or model changed
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
                    return Response::error('Field with this name already exists for this model', HttpStatus::BAD_REQUEST)->getData();
                }
            }
        }

        if (isset($params['model']) && !in_array($params['model'], msModelField::getAvailableModels())) {
            return Response::error('Invalid model type', HttpStatus::BAD_REQUEST)->getData();
        }

        $updateFields = ['model', 'name', 'label', 'xtype', 'visible', 'required', 'sort_order', 'section_id', 'width', 'placeholder', 'description', 'config'];
        foreach ($updateFields as $fieldName) {
            if (array_key_exists($fieldName, $params)) {
                $value = $params[$fieldName];
                if (in_array($fieldName, ['visible', 'required'])) {
                    $value = (bool)$value;
                } elseif (in_array($fieldName, ['sort_order', 'width'])) {
                    $value = (int)$value;
                } elseif ($fieldName === 'section_id') {
                    $value = $value ? (int)$value : null;
                }
                $field->set($fieldName, $value);
            }
        }

        if (!$field->save()) {
            return Response::error('Failed to update field', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success($this->formatField($field), 'Field updated successfully')->getData();
    }

    /**
     * Delete model field
     * DELETE /api/mgr/model-fields/{id}
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function delete(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Field ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $field = $this->modx->getObject(msModelField::class, $id);

        if (!$field) {
            return Response::error('Field not found', HttpStatus::NOT_FOUND)->getData();
        }

        if (!$field->remove()) {
            return Response::error('Failed to delete field', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success(null, 'Field deleted successfully')->getData();
    }

    /**
     * Get available models for dropdown
     * GET /api/mgr/model-fields/models
     *
     * @return array Response
     */
    public function getModels(): array
    {
        $models = [];
        foreach (msModelField::getAvailableModels() as $model) {
            $lexiconKey = 'ms3_model_' . strtolower(str_replace('ms', '', $model));
            $label = $this->modx->lexicon($lexiconKey);
            if ($label === $lexiconKey) {
                $label = $model;
            }
            $models[] = [
                'value' => $model,
                'label' => $label,
            ];
        }

        return Response::success(['models' => $models])->getData();
    }

    /**
     * Get visible fields for a model (for rendering forms), grouped by sections
     * GET /api/mgr/model-fields/visible/{model}
     *
     * @param array $params URL parameters (model)
     * @return array Response
     */
    public function getVisibleFields(array $params = []): array
    {
        $model = $params['model'] ?? '';

        if (empty($model)) {
            return Response::error('Model is required', HttpStatus::BAD_REQUEST)->getData();
        }

        if (!in_array($model, msModelField::getAvailableModels())) {
            return Response::error('Invalid model type', HttpStatus::BAD_REQUEST)->getData();
        }

        // `comboOptions` may include status/payment/delivery names stored as lexicon keys.
        // Load all topics needed both for field labels (`vue`) and option labels.
        $this->modx->lexicon->load('minishop3:vue');
        $this->modx->lexicon->load('minishop3:default');
        $this->modx->lexicon->load('minishop3:manager');

        // Get sections for the model
        $sections = $this->getSectionsForModel($model);
        $sectionsById = [];
        foreach ($sections as $section) {
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
        foreach ($this->modx->getIterator(msModelField::class, $query) as $field) {
            $data = $field->toArray();

            // Translate label if it's a lexicon key
            $label = $data['label'] ?? $data['name'];
            if (!empty($label) && str_starts_with($label, 'ms3_')) {
                $translated = $this->modx->lexicon($label);
                if ($translated !== $label) {
                    $label = $translated;
                }
            }
            $data['label_display'] = $label;

            // Translate description if it's a lexicon key
            $description = $data['description'] ?? '';
            if (!empty($description) && str_starts_with($description, 'ms3_')) {
                $translated = $this->modx->lexicon($description);
                if ($translated !== $description) {
                    $description = $translated;
                }
            }
            $data['description_display'] = $description;

            // Add section info
            if (!empty($data['section_id']) && isset($sectionsById[$data['section_id']])) {
                $data['section'] = $sectionsById[$data['section_id']];
            }

            $results[] = $data;
        }

        // Resolve combo options for all fields with combo xtype
        $comboManager = new ComboConfigManager($this->modx);
        $fieldsWithProperties = [];
        foreach ($results as $fieldData) {
            if (in_array($fieldData['xtype'] ?? '', ['combo', 'combobox', 'select', 'dropdown'])) {
                $fieldsWithProperties[] = [
                    'name' => $fieldData['name'],
                    'properties' => $fieldData['config'] ?? null,
                ];
            }
        }
        // Get combo options with metadata (compareField, valueField)
        $comboOptionsWithMeta = $comboManager->resolveAllOptionsWithMeta($model, $fieldsWithProperties);

        return Response::success([
            'results' => $results,
            'total' => count($results),
            'model' => $model,
            'sections' => array_values($sectionsById),
            'comboOptions' => $comboOptionsWithMeta,
        ])->getData();
    }

    /**
     * Update field sort orders (batch update for drag-n-drop reordering)
     * PUT /api/mgr/model-fields/ranks
     *
     * @param array $params Array of {id, sort_order}
     * @return array Response
     */
    public function updateRanks(array $params = []): array
    {
        $ranks = $params['ranks'] ?? [];

        if (empty($ranks) || !is_array($ranks)) {
            return Response::error('Ranks array is required', HttpStatus::BAD_REQUEST)->getData();
        }

        foreach ($ranks as $item) {
            if (!isset($item['id']) || !isset($item['sort_order'])) {
                continue;
            }

            $field = $this->modx->getObject(msModelField::class, (int)$item['id']);
            if ($field) {
                $field->set('sort_order', (int)$item['sort_order']);
                $field->save();
            }
        }

        return Response::success(null, 'Ranks updated successfully')->getData();
    }

    /**
     * Format field for API response
     *
     * @param msModelField $field
     * @param array $sections Sections array for section_name lookup
     * @return array
     */
    protected function formatField(msModelField $field, array $sections = []): array
    {
        $data = $field->toArray();

        // Translate label if it's a lexicon key
        if (!empty($data['label']) && str_starts_with($data['label'], 'ms3_')) {
            $translated = $this->modx->lexicon($data['label']);
            if ($translated !== $data['label']) {
                $data['label_translated'] = $translated;
            }
        }

        // Translate description if it's a lexicon key
        if (!empty($data['description']) && str_starts_with($data['description'], 'ms3_')) {
            $translated = $this->modx->lexicon($data['description']);
            if ($translated !== $data['description']) {
                $data['description_translated'] = $translated;
            }
        }

        // Translate model name
        $modelLexiconKey = 'ms3_model_' . strtolower(str_replace('ms', '', $data['model']));
        $modelLabel = $this->modx->lexicon($modelLexiconKey);
        if ($modelLabel !== $modelLexiconKey) {
            $data['model_label'] = $modelLabel;
        } else {
            $data['model_label'] = $data['model'];
        }

        // Add section name if available
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
     * Format section for API response
     *
     * @param msModelFieldSection $section
     * @return array
     */
    protected function formatSection(msModelFieldSection $section): array
    {
        $data = $section->toArray();

        // Translate label using lexicon_key if available
        if (!empty($data['lexicon_key'])) {
            $translated = $this->modx->lexicon($data['lexicon_key']);
            if ($translated !== $data['lexicon_key']) {
                $data['label'] = $translated;
            }
        }

        // If no label, use section_key as fallback
        if (empty($data['label'])) {
            $data['label'] = ucfirst(str_replace('_', ' ', $data['section_key']));
        }

        return $data;
    }

    // =========================================================================
    // SECTION CRUD OPERATIONS
    // =========================================================================

    /**
     * Get sections list for a model
     * GET /api/mgr/model-fields/sections/{model}
     *
     * @param array $params URL parameters (model)
     * @return array Response
     */
    public function getSections(array $params = []): array
    {
        $model = $params['model'] ?? '';

        if (empty($model)) {
            return Response::error('Model is required', HttpStatus::BAD_REQUEST)->getData();
        }

        if (!in_array($model, msModelFieldSection::getAvailableModels())) {
            return Response::error('Invalid model type', HttpStatus::BAD_REQUEST)->getData();
        }

        $this->modx->lexicon->load('minishop3:vue');

        $sections = $this->getSectionsForModel($model);

        return Response::success([
            'results' => $sections,
            'total' => count($sections),
            'model' => $model,
        ])->getData();
    }

    /**
     * Create new section
     * POST /api/mgr/model-fields/sections
     *
     * @param array $params Section data
     * @return array Response
     */
    public function createSection(array $params = []): array
    {
        $model = $params['model'] ?? '';
        $sectionKey = $params['section_key'] ?? '';

        if (empty($model) || empty($sectionKey)) {
            return Response::error('Model and section_key are required', HttpStatus::BAD_REQUEST)->getData();
        }

        if (!in_array($model, msModelFieldSection::getAvailableModels())) {
            return Response::error('Invalid model type', HttpStatus::BAD_REQUEST)->getData();
        }

        // Check if section already exists
        $existing = $this->modx->getObject(msModelFieldSection::class, [
            'model' => $model,
            'section_key' => $sectionKey,
        ]);

        if ($existing) {
            return Response::error('Section with this key already exists for this model', HttpStatus::BAD_REQUEST)->getData();
        }

        $section = $this->modx->newObject(msModelFieldSection::class);
        $section->fromArray([
            'model' => $model,
            'section_key' => $sectionKey,
            'label' => $params['label'] ?? null,
            'lexicon_key' => $params['lexicon_key'] ?? null,
            'hidden' => isset($params['hidden']) ? (bool)$params['hidden'] : false,
            'sort_order' => (int)($params['sort_order'] ?? 0),
            'is_default' => isset($params['is_default']) ? (bool)$params['is_default'] : false,
        ]);

        if (!$section->save()) {
            return Response::error('Failed to create section', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        $this->modx->lexicon->load('minishop3:vue');
        return Response::success($this->formatSection($section), 'Section created successfully', HttpStatus::CREATED)->getData();
    }

    /**
     * Update section
     * PUT /api/mgr/model-fields/sections/{id}
     *
     * @param array $params Section data with id
     * @return array Response
     */
    public function updateSection(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Section ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $section = $this->modx->getObject(msModelFieldSection::class, $id);

        if (!$section) {
            return Response::error('Section not found', HttpStatus::NOT_FOUND)->getData();
        }

        $updateFields = ['label', 'lexicon_key', 'hidden', 'sort_order'];
        foreach ($updateFields as $fieldName) {
            if (array_key_exists($fieldName, $params)) {
                $value = $params[$fieldName];
                if ($fieldName === 'hidden') {
                    $value = (bool)$value;
                } elseif ($fieldName === 'sort_order') {
                    $value = (int)$value;
                }
                $section->set($fieldName, $value);
            }
        }

        if (!$section->save()) {
            return Response::error('Failed to update section', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        $this->modx->lexicon->load('minishop3:vue');
        return Response::success($this->formatSection($section), 'Section updated successfully')->getData();
    }

    /**
     * Delete section
     * DELETE /api/mgr/model-fields/sections/{id}
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function deleteSection(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Section ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $section = $this->modx->getObject(msModelFieldSection::class, $id);

        if (!$section) {
            return Response::error('Section not found', HttpStatus::NOT_FOUND)->getData();
        }

        // Check if it's a default section
        if ($section->get('is_default')) {
            return Response::error('Cannot delete default section', HttpStatus::BAD_REQUEST)->getData();
        }

        // Set fields in this section to null
        $query = $this->modx->newQuery(msModelField::class);
        $query->where(['section_id' => $id]);
        foreach ($this->modx->getIterator(msModelField::class, $query) as $field) {
            $field->set('section_id', null);
            $field->save();
        }

        if (!$section->remove()) {
            return Response::error('Failed to delete section', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success(null, 'Section deleted successfully')->getData();
    }

    /**
     * Update section sort orders (batch update for drag-n-drop reordering)
     * PUT /api/mgr/model-fields/sections/ranks
     *
     * @param array $params Array of {id, sort_order}
     * @return array Response
     */
    public function updateSectionRanks(array $params = []): array
    {
        $ranks = $params['ranks'] ?? [];

        if (empty($ranks) || !is_array($ranks)) {
            return Response::error('Ranks array is required', HttpStatus::BAD_REQUEST)->getData();
        }

        foreach ($ranks as $item) {
            if (!isset($item['id']) || !isset($item['sort_order'])) {
                continue;
            }

            $section = $this->modx->getObject(msModelFieldSection::class, (int)$item['id']);
            if ($section) {
                $section->set('sort_order', (int)$item['sort_order']);
                $section->save();
            }
        }

        return Response::success(null, 'Section ranks updated successfully')->getData();
    }

    // =========================================================================
    // COMBO OPTIONS
    // =========================================================================

    /**
     * Get combo options for a model's fields
     * GET /api/mgr/model-fields/combo-options/{model}
     *
     * Returns resolved options for all combo/select fields of a model.
     * Uses ComboConfigManager to resolve options from file configs and DB properties.
     *
     * @param array $params URL parameters (model)
     * @return array Response
     */
    public function getComboOptions(array $params = []): array
    {
        $model = $params['model'] ?? '';

        if (empty($model)) {
            return Response::error('Model is required', HttpStatus::BAD_REQUEST)->getData();
        }

        if (!in_array($model, msModelField::getAvailableModels())) {
            return Response::error('Invalid model type', HttpStatus::BAD_REQUEST)->getData();
        }

        // Get fields with combo xtypes (select, combobox, dropdown, etc.)
        $query = $this->modx->newQuery(msModelField::class);
        $query->where([
            'model' => $model,
            'xtype:IN' => ['combo', 'combobox', 'select', 'dropdown'],
        ]);

        $fieldsWithProperties = [];
        foreach ($this->modx->getIterator(msModelField::class, $query) as $field) {
            $fieldsWithProperties[] = [
                'name' => $field->get('name'),
                'properties' => $field->get('config'),
            ];
        }

        // Resolve options via ComboConfigManager (with metadata for proper field comparison)
        $comboManager = new ComboConfigManager($this->modx);
        $options = $comboManager->resolveAllOptionsWithMeta($model, $fieldsWithProperties);

        return Response::success([
            'model' => $model,
            'options' => $options,
        ])->getData();
    }

    /**
     * Get combo options for a specific field
     * GET /api/mgr/model-fields/combo-options/{model}/{field_name}
     *
     * @param array $params URL parameters (model, field_name)
     * @return array Response
     */
    public function getFieldComboOptions(array $params = []): array
    {
        $model = $params['model'] ?? '';
        $fieldName = $params['field_name'] ?? '';

        if (empty($model) || empty($fieldName)) {
            return Response::error('Model and field_name are required', HttpStatus::BAD_REQUEST)->getData();
        }

        if (!in_array($model, msModelField::getAvailableModels())) {
            return Response::error('Invalid model type', HttpStatus::BAD_REQUEST)->getData();
        }

        // Get field from database
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

        // Resolve options via ComboConfigManager
        $comboManager = new ComboConfigManager($this->modx);
        $options = $comboManager->resolveOptions($model, $fieldName, $dbProperties);

        return Response::success([
            'model' => $model,
            'field_name' => $fieldName,
            'options' => $options,
        ])->getData();
    }
}
