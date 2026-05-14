<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msOption;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Option\OptionService;
use MODX\Revolution\modX;

/**
 * REST API for per-category option links (Manager UI).
 *
 * Replaces legacy processors under src/Processors/Category/Option/*. Handles the
 * category → options tab: list, add, update (value/active/required/position/caption/description),
 * delete, reorder, bulk operations, duplicate from another category.
 *
 * Note: msCategoryOption::afterSave() and remove() contain the product-sync business logic
 * (cascade to msProductOption). We go through the model, not raw SQL, so those hooks fire.
 */
class CategoryOptionsController
{
    protected modX $modx;
    protected OptionService $optionService;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->optionService = $modx->services->get('ms3_option_service');
    }

    /**
     * GET /api/mgr/categories/{category_id}/options
     */
    public function getList(array $params = []): array
    {
        $categoryId = (int)($params['category_id'] ?? 0);
        if (!$categoryId) {
            return Response::error('category_id is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $query = trim((string)($params['query'] ?? ''));

        $criteria = ['category_id' => $categoryId];
        if ($query !== '') {
            // Wrap OR-conditions into a nested array so xPDO groups them as (a OR b OR c OR d)
            // and the AND with category_id stays correct.
            $criteria[] = [
                'Option.key:LIKE' => "%{$query}%",
                'OR:Option.caption:LIKE' => "%{$query}%",
                'OR:msCategoryOption.caption:LIKE' => "%{$query}%",
                'OR:msCategoryOption.description:LIKE' => "%{$query}%",
            ];
        }

        $q = $this->modx->newQuery(msCategoryOption::class, $criteria);
        $q->innerJoin(msOption::class, 'Option');
        $q->select($this->modx->getSelectColumns(msCategoryOption::class, 'msCategoryOption'));
        $q->select(
            'Option.key, Option.type, '
            . 'Option.caption AS global_caption, Option.description AS global_description, '
            . 'Option.measure_unit, Option.properties AS option_properties'
        );

        $total = $this->modx->getCount(msCategoryOption::class, $q);
        $q->sortby('msCategoryOption.position', 'ASC');

        $results = [];
        if ($q->prepare() && $q->stmt->execute()) {
            while ($row = $q->stmt->fetch(\PDO::FETCH_ASSOC)) {
                $results[] = $this->formatRow($row);
            }
        }

        return Response::success([
            'results' => $results,
            'total' => $total,
        ])->getData();
    }

    /**
     * POST /api/mgr/categories/{category_id}/options
     *
     * @param array $data option_id, value, active, required, caption, description
     */
    public function create(array $data = []): array
    {
        $categoryId = (int)($data['category_id'] ?? 0);
        $optionId = (int)($data['option_id'] ?? 0);
        if (!$categoryId || !$optionId) {
            return Response::error('category_id and option_id are required', HttpStatus::BAD_REQUEST)->getData();
        }

        if ($this->modx->getCount(msCategoryOption::class, ['category_id' => $categoryId, 'option_id' => $optionId]) > 0) {
            return Response::error('Option already assigned to this category', HttpStatus::UNPROCESSABLE_ENTITY)->getData();
        }

        $position = $this->modx->getCount(msCategoryOption::class, ['category_id' => $categoryId]);

        $ok = $this->optionService->addOptionToCategory(
            $optionId,
            $categoryId,
            (string)($data['value'] ?? ''),
            (bool)($data['active'] ?? true),
            $position,
            isset($data['caption']) && $data['caption'] !== '' ? (string)$data['caption'] : null,
            isset($data['description']) && $data['description'] !== '' ? (string)$data['description'] : null
        );

        if (!$ok) {
            return Response::error('Failed to add option to category', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        if (!empty($data['required'])) {
            $link = $this->modx->getObject(msCategoryOption::class, [
                'option_id' => $optionId,
                'category_id' => $categoryId,
            ]);
            if ($link) {
                $link->set('required', true);
                $link->save();
            }
        }

        return Response::success([
            'category_id' => $categoryId,
            'option_id' => $optionId,
        ], 'Option linked to category')->getData();
    }

    /**
     * PUT /api/mgr/categories/{category_id}/options/{option_id}
     *
     * Supports partial update of: value, active, required, position, caption, description.
     */
    public function update(array $data = []): array
    {
        $categoryId = (int)($data['category_id'] ?? 0);
        $optionId = (int)($data['option_id'] ?? 0);
        if (!$categoryId || !$optionId) {
            return Response::error('category_id and option_id are required', HttpStatus::BAD_REQUEST)->getData();
        }

        $link = $this->modx->getObject(msCategoryOption::class, [
            'category_id' => $categoryId,
            'option_id' => $optionId,
        ]);
        if (!$link) {
            return Response::error('Link not found', HttpStatus::NOT_FOUND)->getData();
        }

        $allowed = ['value', 'active', 'required', 'position', 'caption', 'description'];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if (($field === 'caption' || $field === 'description') && $value === '') {
                $value = null;
            }
            $link->set($field, $value);
        }

        if (!$link->save()) {
            return Response::error('Failed to update link', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([], 'Link updated')->getData();
    }

    /**
     * DELETE /api/mgr/categories/{category_id}/options/{option_id}
     */
    public function delete(array $params = []): array
    {
        $categoryId = (int)($params['category_id'] ?? 0);
        $optionId = (int)($params['option_id'] ?? 0);
        if (!$categoryId || !$optionId) {
            return Response::error('category_id and option_id are required', HttpStatus::BAD_REQUEST)->getData();
        }

        $ok = $this->optionService->removeOptionFromCategory($optionId, $categoryId);
        if (!$ok) {
            return Response::error('Failed to remove link', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([], 'Link removed')->getData();
    }

    /**
     * POST /api/mgr/categories/{category_id}/options/sort
     *
     * @param array $data option_ids: int[] (new order)
     */
    public function sort(array $data = []): array
    {
        $categoryId = (int)($data['category_id'] ?? 0);
        $optionIds = $data['option_ids'] ?? [];
        if (is_string($optionIds)) {
            $decoded = json_decode($optionIds, true);
            $optionIds = is_array($decoded) ? $decoded : [];
        }
        if (!$categoryId || !is_array($optionIds) || $optionIds === []) {
            return Response::error('category_id and option_ids are required', HttpStatus::BAD_REQUEST)->getData();
        }

        $position = 0;
        foreach ($optionIds as $optionId) {
            $optionId = (int)$optionId;
            if ($optionId <= 0) {
                continue;
            }
            $link = $this->modx->getObject(msCategoryOption::class, [
                'category_id' => $categoryId,
                'option_id' => $optionId,
            ]);
            if ($link) {
                $link->set('position', $position++);
                $link->save();
            }
        }

        return Response::success(['sorted' => $position], "Reordered {$position} options")->getData();
    }

    /**
     * POST /api/mgr/categories/{category_id}/options/bulk
     *
     * @param array $data action: activate|deactivate|require|unrequire|remove, option_ids: int[]
     */
    public function bulk(array $data = []): array
    {
        $categoryId = (int)($data['category_id'] ?? 0);
        $action = (string)($data['action'] ?? '');
        $optionIds = $data['option_ids'] ?? [];
        if (is_string($optionIds)) {
            $decoded = json_decode($optionIds, true);
            $optionIds = is_array($decoded) ? $decoded : [];
        }
        $optionIds = array_values(array_filter(array_map('intval', is_array($optionIds) ? $optionIds : [])));

        if (!$categoryId || $optionIds === []) {
            return Response::error('category_id and option_ids are required', HttpStatus::BAD_REQUEST)->getData();
        }

        $allowedActions = ['activate', 'deactivate', 'require', 'unrequire', 'remove'];
        if (!in_array($action, $allowedActions, true)) {
            return Response::error("Unknown action '{$action}'", HttpStatus::BAD_REQUEST)->getData();
        }

        $affected = 0;
        foreach ($optionIds as $optionId) {
            if ($action === 'remove') {
                if ($this->optionService->removeOptionFromCategory($optionId, $categoryId)) {
                    $affected++;
                }
                continue;
            }

            $link = $this->modx->getObject(msCategoryOption::class, [
                'category_id' => $categoryId,
                'option_id' => $optionId,
            ]);
            if (!$link) {
                continue;
            }

            switch ($action) {
                case 'activate':
                    $link->set('active', true);
                    break;
                case 'deactivate':
                    $link->set('active', false);
                    break;
                case 'require':
                    $link->set('required', true);
                    break;
                case 'unrequire':
                    $link->set('required', false);
                    break;
            }
            if ($link->save()) {
                $affected++;
            }
        }

        return Response::success(['affected' => $affected], "Updated {$affected} links")->getData();
    }

    /**
     * POST /api/mgr/categories/{category_id}/options/duplicate
     *
     * Copy options from source category to the target. Existing links in target stay.
     *
     * @param array $data category_from: int
     */
    public function duplicate(array $data = []): array
    {
        $categoryTo = (int)($data['category_id'] ?? 0);
        $categoryFrom = (int)($data['category_from'] ?? 0);
        if (!$categoryTo || !$categoryFrom) {
            return Response::error('category_id and category_from are required', HttpStatus::BAD_REQUEST)->getData();
        }
        if ($categoryTo === $categoryFrom) {
            return Response::error('Source and target categories must be different', HttpStatus::BAD_REQUEST)->getData();
        }

        $fromCategory = $this->modx->getObject(msCategory::class, $categoryFrom);
        $toCategory = $this->modx->getObject(msCategory::class, $categoryTo);
        if (!$fromCategory || !$toCategory) {
            return Response::error('Category not found', HttpStatus::NOT_FOUND)->getData();
        }

        $copied = 0;
        $skipped = 0;
        $links = $fromCategory->getMany('CategoryOptions');
        /** @var msCategoryOption $srcLink */
        foreach ($links as $srcLink) {
            $optionId = (int)$srcLink->get('option_id');
            $existing = $this->modx->getObject(msCategoryOption::class, [
                'option_id' => $optionId,
                'category_id' => $categoryTo,
            ]);
            if ($existing) {
                $skipped++;
                continue;
            }

            /** @var msCategoryOption $newLink */
            $newLink = $this->modx->newObject(msCategoryOption::class);
            $newLink->fromArray($srcLink->toArray(), '', true, true);
            $newLink->set('category_id', $categoryTo);
            // Reset PK: fromArray with rebuild=true clears it, but be explicit for composite keys.
            if ($newLink->save()) {
                $copied++;
            }
        }

        return Response::success(
            ['copied' => $copied, 'skipped' => $skipped],
            "Copied {$copied} options (skipped {$skipped} duplicates)"
        )->getData();
    }

    /* ---------------- Internal ---------------- */

    /**
     * Format a msCategoryOption row joined with msOption fields.
     *
     * Emits both the per-category override and the global msOption caption/description as well
     * as the effective value that the storefront actually shows: non-empty override wins,
     * otherwise the global value is used.
     */
    protected function formatRow(array $row): array
    {
        $overrideCaption = $row['caption'] ?? null;
        $overrideDescription = $row['description'] ?? null;
        $globalCaption = (string)($row['global_caption'] ?? '');
        $globalDescription = (string)($row['global_description'] ?? '');

        // Use the same merge helper as the storefront overlay (OptionLoaderService): non-empty
        // trimmed override wins, otherwise fall back to global. Keeps admin/grid in sync with
        // the value users actually see on site — trailing whitespace doesn't sneak through.
        $loader = $this->optionService->getLoader();
        $effectiveCaption = $loader->mergeCaptionDescription(
            $overrideCaption !== null ? (string)$overrideCaption : null,
            $globalCaption
        );

        return [
            'id' => (int)$row['id'],
            'option_id' => (int)$row['option_id'],
            'category_id' => (int)$row['category_id'],
            'position' => (int)($row['position'] ?? 0),
            'active' => (bool)($row['active'] ?? false),
            'required' => (bool)($row['required'] ?? false),
            'value' => $row['value'],
            'key' => $row['key'],
            'type' => $row['type'],
            'measure_unit' => $row['measure_unit'] ?? null,
            'caption' => $effectiveCaption,
            'global_caption' => $globalCaption,
            'global_description' => $globalDescription,
            'category_caption' => $overrideCaption,
            'category_description' => $overrideDescription,
            'properties' => $this->decodeJson($row['option_properties'] ?? null),
        ];
    }

    protected function decodeJson($v): array
    {
        if (is_array($v)) {
            return $v;
        }
        if (is_string($v) && $v !== '') {
            $decoded = json_decode($v, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }
}
