<?php

namespace MiniShop3\Services\ModelField;

use MiniShop3\Model\msModelField;
use MiniShop3\Model\msModelFieldSection;
use MODX\Revolution\modX;

/**
 * CRUD and formatting for msModelFieldSection.
 */
class ModelFieldSectionService
{
    private const UPDATE_FIELDS = ['label', 'lexicon_key', 'hidden', 'sort_order'];

    private modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSectionsForModel(string $model): array
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
     * @return array{success: bool, data?: array<string, mixed>, message?: string, created?: true}
     */
    public function getSections(array $params = []): array
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
        $sections = $this->getSectionsForModel($model);

        return $this->ok([
            'results' => $sections,
            'total' => count($sections),
            'model' => $model,
        ]);
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>, message?: string, created?: true}
     */
    public function createSection(array $params = []): array
    {
        $model = $params['model'] ?? '';
        $sectionKey = $params['section_key'] ?? '';

        if (empty($model) || empty($sectionKey)) {
            return $this->fail('Model and section_key are required');
        }

        $invalid = $this->rejectInvalidModel($model);
        if ($invalid !== null) {
            return $invalid;
        }

        $existing = $this->modx->getObject(msModelFieldSection::class, [
            'model' => $model,
            'section_key' => $sectionKey,
        ]);
        if ($existing) {
            return $this->fail('Section with this key already exists for this model');
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
            return $this->fail('Failed to create section', 'server_error');
        }

        $this->modx->lexicon->load('minishop3:vue');

        return $this->ok($this->formatSection($section), 'Section created successfully', true);
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>, message?: string, created?: true}
     */
    public function updateSection(array $params = []): array
    {
        $section = $this->requireSection((int)($params['id'] ?? 0));
        if (!$section instanceof msModelFieldSection) {
            return $section;
        }

        foreach (self::UPDATE_FIELDS as $fieldName) {
            if (!array_key_exists($fieldName, $params)) {
                continue;
            }
            $value = $params[$fieldName];
            if ($fieldName === 'hidden') {
                $value = (bool)$value;
            } elseif ($fieldName === 'sort_order') {
                $value = (int)$value;
            }
            $section->set($fieldName, $value);
        }

        if (!$section->save()) {
            return $this->fail('Failed to update section', 'server_error');
        }

        $this->modx->lexicon->load('minishop3:vue');

        return $this->ok($this->formatSection($section), 'Section updated successfully');
    }

    /**
     * @return array{success: bool, data?: mixed, message?: string, created?: true}
     */
    public function deleteSection(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);
        $section = $this->requireSection($id);
        if (!$section instanceof msModelFieldSection) {
            return $section;
        }

        if ($section->get('is_default')) {
            return $this->fail('Cannot delete default section');
        }

        $query = $this->modx->newQuery(msModelField::class);
        $query->where(['section_id' => $id]);
        foreach ($this->modx->getIterator(msModelField::class, $query) as $field) {
            $field->set('section_id', null);
            $field->save();
        }

        if (!$section->remove()) {
            return $this->fail('Failed to delete section', 'server_error');
        }

        return $this->ok(null, 'Section deleted successfully');
    }

    /**
     * @return array{success: bool, data?: mixed, message?: string, created?: true}
     */
    public function updateSectionRanks(array $params = []): array
    {
        $ranks = $params['ranks'] ?? [];
        if (empty($ranks) || !is_array($ranks)) {
            return $this->fail('Ranks array is required');
        }

        foreach ($ranks as $item) {
            if (!isset($item['id'], $item['sort_order'])) {
                continue;
            }

            $section = $this->modx->getObject(msModelFieldSection::class, (int)$item['id']);
            if ($section) {
                $section->set('sort_order', (int)$item['sort_order']);
                $section->save();
            }
        }

        return $this->ok(null, 'Section ranks updated successfully');
    }

    /**
     * @return array<string, mixed>
     */
    public function formatSection(msModelFieldSection $section): array
    {
        $data = $section->toArray();

        if (!empty($data['lexicon_key'])) {
            $translated = $this->modx->lexicon($data['lexicon_key']);
            if ($translated !== $data['lexicon_key']) {
                $data['label'] = $translated;
            }
        }

        if (empty($data['label'])) {
            $data['label'] = ucfirst(str_replace('_', ' ', $data['section_key']));
        }

        return $data;
    }

    /**
     * @return msModelFieldSection|array{success: false, message: string, error: string}
     */
    private function requireSection(int $id): msModelFieldSection|array
    {
        if (!$id) {
            return $this->fail('Section ID is required');
        }

        $section = $this->modx->getObject(msModelFieldSection::class, $id);
        if (!$section instanceof msModelFieldSection) {
            return $this->fail('Section not found', 'not_found');
        }

        return $section;
    }

    /**
     * @return array{success: false, message: string, error: string}|null
     */
    private function rejectInvalidModel(string $model): ?array
    {
        if (!in_array($model, msModelFieldSection::getAvailableModels())) {
            return $this->fail('Invalid model type');
        }

        return null;
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
