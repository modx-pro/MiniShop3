<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msOption;
use MiniShop3\Model\msOptionGroup;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * REST API for option groups (Manager UI).
 *
 * Manages msOptionGroup — a dedicated grouping model for product options, replacing the
 * legacy `msOption.modcategory_id → modCategory` reference (#10). Provides CRUD + reorder
 * + per-group option count for the "Groups" tab on the product options admin page.
 */
class OptionGroupsController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * GET /api/mgr/option-groups
     *
     * @param array $params start, limit, query
     */
    public function getList(array $params = []): array
    {
        $start = (int)($params['start'] ?? 0);
        $limit = (int)($params['limit'] ?? 0); // 0 = no limit (typical: tabs need full list)
        $query = trim((string)($params['query'] ?? ''));

        $criteria = [];
        if ($query !== '') {
            $criteria[] = [
                'name:LIKE' => "%{$query}%",
                'OR:description:LIKE' => "%{$query}%",
            ];
        }

        $total = $this->modx->getCount(msOptionGroup::class, $criteria);

        $q = $this->modx->newQuery(msOptionGroup::class, $criteria);
        $q->sortby('sort_order', 'ASC');
        $q->sortby('id', 'ASC');
        if ($limit > 0) {
            $q->limit($limit, $start);
        }

        $results = [];
        foreach ($this->modx->getIterator(msOptionGroup::class, $q) as $group) {
            $results[] = $this->formatGroup($group);
        }

        // Attach options_count via single grouped query (avoids N+1).
        // Filter by ids on the current page so paginated calls don't aggregate the whole table.
        if (!empty($results)) {
            $counts = $this->getOptionsCountByGroup(array_column($results, 'id'));
            foreach ($results as &$row) {
                $row['options_count'] = $counts[$row['id']] ?? 0;
            }
            unset($row);
        }

        return Response::success([
            'results' => $results,
            'total' => $total,
        ])->getData();
    }

    /**
     * GET /api/mgr/option-groups/{id}
     */
    public function get(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);
        if (!$id) {
            return Response::error('Option group ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        /** @var msOptionGroup|null $group */
        $group = $this->modx->getObject(msOptionGroup::class, $id);
        if (!$group) {
            return Response::error('Option group not found', HttpStatus::NOT_FOUND)->getData();
        }

        $data = $this->formatGroup($group);
        $counts = $this->getOptionsCountByGroup([$id]);
        $data['options_count'] = $counts[$id] ?? 0;

        return Response::success($data)->getData();
    }

    /**
     * POST /api/mgr/option-groups
     */
    public function create(array $data = []): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            return Response::error('Option group name is required', HttpStatus::BAD_REQUEST)->getData();
        }

        /** @var msOptionGroup $group */
        $group = $this->modx->newObject(msOptionGroup::class);
        $group->set('name', $name);
        $group->set('description', $data['description'] ?? null);

        // Default sort_order to end of list when not provided.
        if (array_key_exists('sort_order', $data) && is_numeric($data['sort_order'])) {
            $group->set('sort_order', (int)$data['sort_order']);
        } else {
            $group->set('sort_order', $this->nextSortOrder());
        }

        if (!$group->save()) {
            return Response::error('Failed to create option group', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success($this->formatGroup($group), 'Option group created')->getData();
    }

    /**
     * PUT /api/mgr/option-groups/{id}
     */
    public function update(array $data = []): array
    {
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            return Response::error('Option group ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        /** @var msOptionGroup|null $group */
        $group = $this->modx->getObject(msOptionGroup::class, $id);
        if (!$group) {
            return Response::error('Option group not found', HttpStatus::NOT_FOUND)->getData();
        }

        if (array_key_exists('name', $data)) {
            $name = trim((string)$data['name']);
            if ($name === '') {
                return Response::error('Option group name cannot be empty', HttpStatus::BAD_REQUEST)->getData();
            }
            $group->set('name', $name);
        }
        if (array_key_exists('description', $data)) {
            $group->set('description', $data['description']);
        }
        if (array_key_exists('sort_order', $data) && is_numeric($data['sort_order'])) {
            $group->set('sort_order', (int)$data['sort_order']);
        }

        if (!$group->save()) {
            return Response::error('Failed to update option group', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success($this->formatGroup($group), 'Option group updated')->getData();
    }

    /**
     * DELETE /api/mgr/option-groups/{id}
     *
     * Options assigned to the group keep their data but lose the grouping
     * (`option_group_id` becomes NULL — falls into "no group" bucket).
     */
    public function delete(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);
        if (!$id) {
            return Response::error('Option group ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        /** @var msOptionGroup|null $group */
        $group = $this->modx->getObject(msOptionGroup::class, $id);
        if (!$group) {
            return Response::error('Option group not found', HttpStatus::NOT_FOUND)->getData();
        }

        // Detach options (set option_group_id = NULL) before removing the group.
        // After switching Options from composites → aggregates the model no longer
        // cascade-deletes options, but we still detach explicitly so the group
        // stops being referenced before it vanishes. Bail out if detach actually
        // failed (DB error) — don't silently drop the group with options still
        // pointing at a now-missing id.
        if (!$this->detachOptionsFromGroup($id)) {
            return Response::error('Failed to detach options before group removal', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        if (!$group->remove()) {
            return Response::error('Failed to delete option group', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([], 'Option group deleted')->getData();
    }

    /**
     * DELETE /api/mgr/option-groups/bulk
     *
     * @param array $data ids[]
     */
    public function bulkDelete(array $data = []): array
    {
        $ids = $data['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            return Response::error('Option group IDs array is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $ids = array_filter(array_map('intval', $ids), static fn($id) => $id > 0);
        if (empty($ids)) {
            return Response::error('No valid option group IDs provided', HttpStatus::BAD_REQUEST)->getData();
        }

        $deleted = 0;
        $failed = 0;

        foreach ($ids as $id) {
            /** @var msOptionGroup|null $group */
            $group = $this->modx->getObject(msOptionGroup::class, $id);
            if (!$group) {
                $failed++;
                continue;
            }
            if (!$this->detachOptionsFromGroup($id)) {
                $failed++;
                continue;
            }
            if ($group->remove()) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        if ($deleted === 0) {
            return Response::error('Failed to delete option groups', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([
            'deleted' => $deleted,
            'failed' => $failed,
        ], "Deleted {$deleted} option groups")->getData();
    }

    /**
     * PUT /api/mgr/option-groups/positions
     *
     * Drag-n-drop reorder. Body: { positions: { id: position, ... } } or { ids: [id, id, id, ...] }.
     */
    public function updatePositions(array $data = []): array
    {
        // Accept both shapes: associative map { id: position } and ordered list [id, id, ...].
        if (!empty($data['positions']) && is_array($data['positions'])) {
            $updated = 0;
            foreach ($data['positions'] as $id => $position) {
                /** @var msOptionGroup|null $group */
                $group = $this->modx->getObject(msOptionGroup::class, (int)$id);
                if ($group) {
                    $group->set('sort_order', (int)$position);
                    if ($group->save()) {
                        $updated++;
                    }
                }
            }
            return Response::success(['updated' => $updated], "Updated {$updated} positions")->getData();
        }

        if (!empty($data['ids']) && is_array($data['ids'])) {
            $position = 0;
            $updated = 0;
            foreach ($data['ids'] as $id) {
                /** @var msOptionGroup|null $group */
                $group = $this->modx->getObject(msOptionGroup::class, (int)$id);
                if ($group) {
                    $group->set('sort_order', $position++);
                    if ($group->save()) {
                        $updated++;
                    }
                }
            }
            return Response::success(['updated' => $updated], "Updated {$updated} positions")->getData();
        }

        return Response::error('Positions array or ordered ids list is required', HttpStatus::BAD_REQUEST)->getData();
    }

    /* ---------------- Internal ---------------- */

    /**
     * @return array{id: int, name: string, description: ?string, sort_order: int, created_at: ?string, updated_at: ?string}
     */
    protected function formatGroup(msOptionGroup $group): array
    {
        return [
            'id' => (int)$group->get('id'),
            'name' => (string)$group->get('name'),
            'description' => $group->get('description'),
            'sort_order' => (int)$group->get('sort_order'),
            'created_at' => $group->get('created_at'),
            'updated_at' => $group->get('updated_at'),
        ];
    }

    /**
     * Aggregated counts of options per group. Filter by ids if provided.
     *
     * @param int[]|null $groupIds
     * @return array<int, int> groupId => count
     */
    protected function getOptionsCountByGroup(?array $groupIds = null): array
    {
        $q = $this->modx->newQuery(msOption::class);
        $q->select('msOption.option_group_id AS gid, COUNT(msOption.id) AS cnt');
        $q->where(['msOption.option_group_id:!=' => null]);
        if (!empty($groupIds)) {
            $q->where(['msOption.option_group_id:IN' => $groupIds]);
        }
        $q->groupby('msOption.option_group_id');

        $result = [];
        if ($q->prepare() && $q->stmt->execute()) {
            foreach ($q->stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $result[(int)$row['gid']] = (int)$row['cnt'];
            }
        }

        return $result;
    }

    /**
     * Set option_group_id = NULL for all options previously in this group.
     *
     * @return bool false if the update statement itself failed (return value of
     *              xPDO::updateCollection() was false); true otherwise — including
     *              the case when zero rows matched (the group had no options).
     */
    protected function detachOptionsFromGroup(int $groupId): bool
    {
        $affected = $this->modx->updateCollection(
            msOption::class,
            ['option_group_id' => null],
            ['option_group_id' => $groupId]
        );

        return $affected !== false;
    }

    /**
     * Next sort_order = max(sort_order) + 1 (or 0 if table empty).
     */
    protected function nextSortOrder(): int
    {
        $q = $this->modx->newQuery(msOptionGroup::class);
        $q->select('MAX(sort_order) AS max_order');
        if ($q->prepare() && $q->stmt->execute()) {
            $row = $q->stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && $row['max_order'] !== null) {
                return (int)$row['max_order'] + 1;
            }
        }
        return 0;
    }
}
