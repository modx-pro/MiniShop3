<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msOption;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Option\OptionCategoryService;
use MiniShop3\Services\Option\OptionService;
use MODX\Revolution\modCategory;
use MODX\Revolution\modResource;
use MODX\Revolution\modX;

/**
 * REST API for product options (Manager UI).
 *
 * Replaces legacy ExtJS processors under src/Processors/Settings/Option/*. CRUD + category
 * assignment + option type introspection. Category-link writes (add/remove) still go
 * through msOption::setCategories() and OptionCategoryService to keep the msProductOption
 * sync-to-products behavior in msCategoryOption lifecycle hooks intact.
 */
class OptionsController
{
    protected modX $modx;
    protected OptionService $optionService;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->optionService = $modx->services->get('ms3_option_service');
    }

    /**
     * GET /api/mgr/options
     *
     * @param array $params start, limit, query, modcategory_id, category_id, categories[]
     */
    public function getList(array $params = []): array
    {
        $start = (int)($params['start'] ?? 0);
        $limit = (int)($params['limit'] ?? 20);
        $query = trim($params['query'] ?? '');
        $modcategoryId = isset($params['modcategory_id']) ? (int)$params['modcategory_id'] : null;
        $categoryId = isset($params['category_id']) ? (int)$params['category_id'] : null;
        $categories = $this->decodeIntArray($params['categories'] ?? null);

        $criteria = [];
        if ($query !== '') {
            $criteria[] = [
                'msOption.key:LIKE' => "%{$query}%",
                'OR:msOption.caption:LIKE' => "%{$query}%",
            ];
        }
        if ($modcategoryId !== null) {
            $criteria['msOption.modcategory_id'] = $modcategoryId;
        }

        $q = $this->modx->newQuery(msOption::class, $criteria);

        if ($categoryId) {
            $q->innerJoin(msCategoryOption::class, 'CategoryOption', [
                'CategoryOption.option_id = msOption.id',
                'CategoryOption.category_id' => $categoryId,
            ]);
        } elseif ($categories !== []) {
            $q->innerJoin(msCategoryOption::class, 'CategoryOption', [
                'CategoryOption.option_id = msOption.id',
                'CategoryOption.category_id:IN' => $categories,
            ]);
            $q->groupby('msOption.id');
        }

        $total = $this->modx->getCount(msOption::class, $q);

        $q->sortby('msOption.key', 'ASC');
        if ($limit > 0) {
            $q->limit($limit, $start);
        }

        $results = [];
        foreach ($this->modx->getIterator(msOption::class, $q) as $option) {
            $results[] = $this->formatOption($option);
        }

        return Response::success([
            'results' => $results,
            'total' => $total,
        ])->getData();
    }

    /**
     * GET /api/mgr/options/{id}
     */
    public function get(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);
        if (!$id) {
            return Response::error('Option ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $option = $this->modx->getObject(msOption::class, $id);
        if (!$option) {
            return Response::error('Option not found', HttpStatus::NOT_FOUND)->getData();
        }

        $data = $this->formatOption($option);
        $data['categories'] = $this->getOptionCategoriesMap($id);

        return Response::success($data)->getData();
    }

    /**
     * POST /api/mgr/options
     */
    public function create(array $data = []): array
    {
        $key = trim((string)($data['key'] ?? ''));
        if ($key === '') {
            return Response::error('Option key is required', HttpStatus::BAD_REQUEST, ['errors' => ['key']])->getData();
        }
        $key = str_replace('.', '_', $key);

        if ($this->modx->getCount(msOption::class, ['key' => $key]) > 0) {
            return Response::error("Option with key '{$key}' already exists", HttpStatus::UNPROCESSABLE_ENTITY, ['errors' => ['key']])->getData();
        }

        $option = $this->modx->newObject(msOption::class);
        $this->applyWritableFields($option, array_merge($data, ['key' => $key]));

        if (!$option->save()) {
            return Response::error('Failed to create option', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        $this->syncCategoriesFromPayload($option, $data['categories'] ?? null);

        return Response::success(
            array_merge($this->formatOption($option), [
                'categories' => $this->getOptionCategoriesMap((int)$option->get('id')),
            ]),
            'Option created'
        )->getData();
    }

    /**
     * PUT /api/mgr/options/{id}
     */
    public function update(array $data = []): array
    {
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            return Response::error('Option ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $option = $this->modx->getObject(msOption::class, $id);
        if (!$option) {
            return Response::error('Option not found', HttpStatus::NOT_FOUND)->getData();
        }

        $oldKey = $option->get('key');
        if (isset($data['key'])) {
            $newKey = str_replace('.', '_', trim((string)$data['key']));
            if ($newKey === '') {
                return Response::error('Option key is required', HttpStatus::BAD_REQUEST, ['errors' => ['key']])->getData();
            }
            if ($newKey !== $oldKey
                && $this->modx->getCount(msOption::class, ['key' => $newKey, 'id:!=' => $id]) > 0) {
                return Response::error("Option with key '{$newKey}' already exists", HttpStatus::UNPROCESSABLE_ENTITY, ['errors' => ['key']])->getData();
            }
            $data['key'] = $newKey;
        }

        $this->applyWritableFields($option, $data);

        if (!$option->save()) {
            return Response::error('Failed to save option', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        $this->syncCategoriesFromPayload($option, $data['categories'] ?? null);

        // Propagate key rename to msProductOption rows.
        $newKey = $option->get('key');
        if ($oldKey !== $newKey) {
            $this->optionService->getSync()->updateOptionKey($oldKey, $newKey);
        }

        return Response::success(
            array_merge($this->formatOption($option), [
                'categories' => $this->getOptionCategoriesMap((int)$option->get('id')),
            ]),
            'Option updated'
        )->getData();
    }

    /**
     * DELETE /api/mgr/options/{id}
     */
    public function delete(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);
        if (!$id) {
            return Response::error('Option ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $option = $this->modx->getObject(msOption::class, $id);
        if (!$option) {
            return Response::error('Option not found', HttpStatus::NOT_FOUND)->getData();
        }

        if (!$option->remove()) {
            return Response::error('Failed to delete option', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([], 'Option deleted')->getData();
    }

    /**
     * DELETE /api/mgr/options/bulk
     *
     * @param array $data ids: int[]
     */
    public function bulkDelete(array $data = []): array
    {
        $ids = $this->decodeIntArray($data['ids'] ?? null);
        if ($ids === []) {
            return Response::error('No valid option IDs provided', HttpStatus::BAD_REQUEST)->getData();
        }

        $deleted = 0;
        $failed = 0;
        foreach ($ids as $id) {
            $option = $this->modx->getObject(msOption::class, $id);
            if (!$option) {
                $failed++;
                continue;
            }
            $option->remove() ? $deleted++ : $failed++;
        }

        if ($deleted === 0) {
            return Response::error('No options were deleted', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([
            'deleted' => $deleted,
            'failed' => $failed,
        ], "Deleted {$deleted} options")->getData();
    }

    /**
     * POST /api/mgr/options/bulk/assign
     *
     * Assign given options to given categories. Existing links stay, missing ones are created.
     *
     * @param array $data options: int[], categories: int[]
     */
    public function bulkAssign(array $data = []): array
    {
        $optionIds = $this->decodeIntArray($data['options'] ?? null);
        $categoryIds = $this->decodeIntArray($data['categories'] ?? null);

        if ($optionIds === [] || $categoryIds === []) {
            return Response::error('Both options and categories are required', HttpStatus::BAD_REQUEST)->getData();
        }

        $assigned = 0;
        foreach ($optionIds as $optionId) {
            $result = $this->optionService->assignOptionToCategories($optionId, $categoryIds);
            $assigned += count($result);
        }

        return Response::success(['assigned' => $assigned], "Assigned {$assigned} links")->getData();
    }

    /**
     * GET /api/mgr/options/types
     *
     * Returns available option types (class short names lcfirst'd) and their display captions.
     */
    public function getTypes(): array
    {
        $this->modx->lexicon->load('minishop3:manager');

        $typesDir = __DIR__ . '/../../Options/Types/';
        $types = [];
        foreach (glob($typesDir . '*.php') as $file) {
            $name = basename($file, '.php');
            if ($name === 'msOptionType') {
                continue;
            }
            $key = lcfirst($name);
            $types[] = [
                'name' => $key,
                'caption' => $this->modx->lexicon('ms3_ft_' . $key) ?: $name,
            ];
        }

        usort($types, static fn($a, $b) => strcmp($a['caption'], $b['caption']));

        return Response::success(['results' => $types, 'total' => count($types)])->getData();
    }

    /**
     * GET /api/mgr/options/tree
     *
     * MODX resource tree for category selection. Optional ?option_id= to flag which
     * categories already have the option linked (for checkbox UI).
     *
     * @param array $params parent (default 0), option_id (optional), categories[] (prechecked)
     */
    public function getTree(array $params = []): array
    {
        $parent = (int)($params['parent'] ?? 0);
        $optionId = isset($params['option_id']) ? (int)$params['option_id'] : 0;
        $preChecked = $this->decodeIntArray($params['categories'] ?? null);

        $checkedSet = [];
        if ($optionId > 0) {
            foreach ($this->getOptionCategoriesMap($optionId) as $catId => $flag) {
                if ($flag) {
                    $checkedSet[$catId] = true;
                }
            }
        }
        foreach ($preChecked as $catId) {
            $checkedSet[$catId] = true;
        }

        // Only msCategory nodes (same rule as legacy ExtJS Processors\Category\GetNodes).
        // leaf is derived from a child-count subquery: a node is a leaf when it has no msCategory children.
        $q = $this->modx->newQuery(modResource::class);
        $q->leftJoin(modResource::class, 'Child', [
            'modResource.id = Child.parent',
            'Child.class_key' => msCategory::class,
            'Child.deleted' => 0,
        ]);
        $q->where([
            'modResource.parent' => $parent,
            'modResource.deleted' => 0,
            'modResource.class_key' => msCategory::class,
        ]);
        $q->select('modResource.id, modResource.pagetitle, modResource.menutitle, '
            . 'modResource.parent, modResource.published, modResource.hidemenu, modResource.class_key, '
            . 'COUNT(Child.id) AS childrenCount');
        $q->groupby('modResource.id');
        $q->sortby('modResource.menuindex', 'ASC');

        $nodes = [];
        if ($q->prepare() && $q->stmt->execute()) {
            while ($row = $q->stmt->fetch(\PDO::FETCH_ASSOC)) {
                $id = (int)$row['id'];
                $nodes[] = [
                    'id' => $id,
                    'label' => $row['menutitle'] !== '' ? $row['menutitle'] : $row['pagetitle'],
                    'leaf' => (int)$row['childrenCount'] === 0,
                    'checked' => isset($checkedSet[$id]),
                    'class_key' => $row['class_key'],
                    'published' => (int)$row['published'],
                    'hidemenu' => (int)$row['hidemenu'],
                ];
            }
        }

        return Response::success(['results' => $nodes, 'total' => count($nodes)])->getData();
    }

    /**
     * GET /api/mgr/options/modcategories
     *
     * Flat list of MODX categories (from modCategory, not resource tree) for grouping
     * dropdown in option form.
     */
    public function getModcategories(array $params = []): array
    {
        $query = trim((string)($params['query'] ?? ''));
        $limit = (int)($params['limit'] ?? 500);

        $criteria = [];
        if ($query !== '') {
            $criteria['category:LIKE'] = "%{$query}%";
        }

        $total = $this->modx->getCount(modCategory::class, $criteria);
        $q = $this->modx->newQuery(modCategory::class, $criteria);
        $q->sortby('category', 'ASC');
        if ($limit > 0) {
            $q->limit($limit);
        }

        $results = [];
        foreach ($this->modx->getIterator(modCategory::class, $q) as $cat) {
            $results[] = [
                'id' => (int)$cat->get('id'),
                'category' => $cat->get('category'),
            ];
        }

        return Response::success(['results' => $results, 'total' => $total])->getData();
    }

    /**
     * GET /api/mgr/options/suggestions
     *
     * Distinct values previously saved for an option key across all products. Used as
     * the autocomplete source for the free-form `comboOptions` type on the product form.
     *
     * @param array $params key (required), query (optional, substring match), limit (default 50)
     */
    public function getSuggestions(array $params = []): array
    {
        $key = trim((string)($params['key'] ?? ''));
        if ($key === '') {
            return Response::error('key is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $query = trim((string)($params['query'] ?? ''));
        $limit = (int)($params['limit'] ?? 50);
        if ($limit <= 0 || $limit > 500) {
            $limit = 50;
        }

        $c = $this->modx->newQuery(\MiniShop3\Model\msProductOption::class);
        $c->where(['key' => $key, 'value:!=' => '']);
        if ($query !== '') {
            $c->where(['value:LIKE' => "%{$query}%"]);
        }
        $c->select('DISTINCT value');
        $c->sortby('value', 'ASC');
        $c->limit($limit);

        $results = [];
        if ($c->prepare() && $c->stmt->execute()) {
            $results = $c->stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        }

        return Response::success(['results' => $results, 'total' => count($results)])->getData();
    }

    /* ---------------- Internal ---------------- */

    /**
     * Return representative fields + properties for list/detail responses.
     */
    protected function formatOption(msOption $option): array
    {
        return [
            'id' => (int)$option->get('id'),
            'key' => $option->get('key'),
            'caption' => $option->get('caption'),
            'description' => $option->get('description'),
            'measure_unit' => $option->get('measure_unit'),
            'modcategory_id' => $option->get('modcategory_id') !== null ? (int)$option->get('modcategory_id') : null,
            'type' => $option->get('type'),
            'properties' => $option->get('properties') ?: [],
        ];
    }

    /**
     * @return array<int, int> category_id → 1 (true) map (matches legacy ExtJS format)
     */
    protected function getOptionCategoriesMap(int $optionId): array
    {
        $map = [];
        $q = $this->modx->newQuery(msCategoryOption::class, ['option_id' => $optionId]);
        $q->select('category_id');
        if ($q->prepare() && $q->stmt->execute()) {
            foreach ($q->stmt->fetchAll(\PDO::FETCH_COLUMN) as $cid) {
                $map[(int)$cid] = 1;
            }
        }

        return $map;
    }

    /**
     * Apply only writable fields from incoming payload onto the msOption object.
     *
     * modcategory_id is NOT NULL in the schema but user may clear the dropdown,
     * so we coerce null → 0 for that one field.
     */
    protected function applyWritableFields(msOption $option, array $data): void
    {
        $allowed = ['key', 'caption', 'description', 'measure_unit', 'modcategory_id', 'type', 'properties'];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if ($field === 'modcategory_id' && ($value === null || $value === '')) {
                $value = 0;
            }
            $option->set($field, $value);
        }
    }

    /**
     * Sync option↔category links from payload.
     *
     * Accepts:
     *   - {id: bool, ...} map (legacy ExtJS shape)
     *   - [id, id, ...] list (modern)
     * Missing ids in the payload detach; present ids attach. Null payload = no change.
     */
    protected function syncCategoriesFromPayload(msOption $option, $payload): void
    {
        if ($payload === null) {
            return;
        }

        [$enabled, $disabled] = $this->splitCategoriesPayload($payload);
        $optionId = (int)$option->get('id');

        if ($enabled !== []) {
            $option->setCategories($enabled);
        }
        if ($disabled !== []) {
            /** @var OptionCategoryService $categoryService */
            $categoryService = $this->optionService->getCategory();
            $categoryService->removeFromCategories($optionId, $disabled);
        }
    }

    /**
     * @return array{0: int[], 1: int[]} [enabled, disabled]
     */
    protected function splitCategoriesPayload($payload): array
    {
        $enabled = [];
        $disabled = [];

        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?: [];
        }

        if (!is_array($payload)) {
            return [$enabled, $disabled];
        }

        // Detect shape: assoc map vs flat list.
        $isAssoc = array_keys($payload) !== range(0, count($payload) - 1);

        if ($isAssoc) {
            foreach ($payload as $id => $checked) {
                $id = (int)$id;
                if ($id <= 0) {
                    continue;
                }
                $checked ? $enabled[] = $id : $disabled[] = $id;
            }
        } else {
            foreach ($payload as $id) {
                $id = (int)$id;
                if ($id > 0) {
                    $enabled[] = $id;
                }
            }
        }

        return [$enabled, $disabled];
    }

    /**
     * Decode ids array whether it came as JSON string, comma string, or array.
     *
     * @return int[]
     */
    protected function decodeIntArray($input): array
    {
        if ($input === null || $input === '') {
            return [];
        }

        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if (is_array($decoded)) {
                $input = $decoded;
            } else {
                $input = explode(',', $input);
            }
        }

        if (!is_array($input)) {
            return [];
        }

        $out = [];
        foreach ($input as $v) {
            $v = (int)$v;
            if ($v > 0) {
                $out[] = $v;
            }
        }

        return array_values(array_unique($out));
    }
}
