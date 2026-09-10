<?php

declare(strict_types=1);

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerGroup;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MODX\Revolution\modUserGroup;
use MODX\Revolution\modX;

/**
 * REST API for customer groups (Manager API, no Vue in #669).
 *
 * Links msCustomerGroup → MODX modUserGroup for catalog resource-group ACL.
 */
class CustomerGroupsController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * GET /api/mgr/customer-groups
     *
     * @param array<string, mixed> $params
     */
    public function getList(array $params = []): array
    {
        $start = (int) ($params['start'] ?? 0);
        $limit = (int) ($params['limit'] ?? 0);
        $query = trim((string) ($params['query'] ?? ''));

        $criteria = [];
        if ($query !== '') {
            $criteria[] = [
                'name:LIKE' => "%{$query}%",
            ];
        }

        $total = $this->modx->getCount(msCustomerGroup::class, $criteria);

        $q = $this->modx->newQuery(msCustomerGroup::class, $criteria);
        $q->sortby('name', 'ASC');
        $q->sortby('id', 'ASC');
        if ($limit > 0) {
            $q->limit($limit, $start);
        }

        $results = [];
        foreach ($this->modx->getIterator(msCustomerGroup::class, $q) as $group) {
            $results[] = $this->formatGroup($group);
        }

        if ($results !== []) {
            $counts = $this->getCustomerCountByGroup(array_column($results, 'id'));
            foreach ($results as &$row) {
                $row['customers_count'] = $counts[$row['id']] ?? 0;
            }
            unset($row);
        }

        return Response::success([
            'results' => $results,
            'total' => $total,
        ])->getData();
    }

    /**
     * GET /api/mgr/customer-groups/{id}
     *
     * @param array<string, mixed> $params
     */
    public function get(array $params = []): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::error(
                $this->lexicon('ms3_err_customer_group_id_required'),
                HttpStatus::BAD_REQUEST,
            )->getData();
        }

        /** @var msCustomerGroup|null $group */
        $group = $this->modx->getObject(msCustomerGroup::class, $id);
        if ($group === null) {
            return Response::error(
                $this->lexicon('ms3_err_customer_group_not_found'),
                HttpStatus::NOT_FOUND,
            )->getData();
        }

        $data = $this->formatGroup($group);
        $counts = $this->getCustomerCountByGroup([$id]);
        $data['customers_count'] = $counts[$id] ?? 0;

        return Response::success($data)->getData();
    }

    /**
     * POST /api/mgr/customer-groups
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data = []): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return Response::error(
                $this->lexicon('ms3_err_customer_group_name_required'),
                HttpStatus::BAD_REQUEST,
            )->getData();
        }

        $userGroupId = (int) ($data['user_group_id'] ?? 0);
        if ($userGroupId <= 0 || !$this->modUserGroupExists($userGroupId)) {
            return Response::error(
                $this->lexicon('ms3_err_customer_group_user_group_invalid'),
                HttpStatus::BAD_REQUEST,
            )->getData();
        }

        /** @var msCustomerGroup $group */
        $group = $this->modx->newObject(msCustomerGroup::class);
        $group->set('name', $name);
        $group->set('user_group_id', $userGroupId);
        if (array_key_exists('active', $data)) {
            $group->set('active', (bool) $data['active']);
        }

        if (!$group->save()) {
            return Response::error(
                $this->lexicon('ms3_err_customer_group_save'),
                HttpStatus::INTERNAL_SERVER_ERROR,
            )->getData();
        }

        return Response::success(
            $this->formatGroup($group),
            $this->lexicon('ms3_customer_group_created'),
        )->getData();
    }

    /**
     * PUT /api/mgr/customer-groups/{id}
     *
     * @param array<string, mixed> $data
     */
    public function update(array $data = []): array
    {
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            return Response::error(
                $this->lexicon('ms3_err_customer_group_id_required'),
                HttpStatus::BAD_REQUEST,
            )->getData();
        }

        /** @var msCustomerGroup|null $group */
        $group = $this->modx->getObject(msCustomerGroup::class, $id);
        if ($group === null) {
            return Response::error(
                $this->lexicon('ms3_err_customer_group_not_found'),
                HttpStatus::NOT_FOUND,
            )->getData();
        }

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                return Response::error(
                    $this->lexicon('ms3_err_customer_group_name_required'),
                    HttpStatus::BAD_REQUEST,
                )->getData();
            }
            $group->set('name', $name);
        }

        if (array_key_exists('user_group_id', $data)) {
            $userGroupId = (int) $data['user_group_id'];
            if ($userGroupId <= 0 || !$this->modUserGroupExists($userGroupId)) {
                return Response::error(
                    $this->lexicon('ms3_err_customer_group_user_group_invalid'),
                    HttpStatus::BAD_REQUEST,
                )->getData();
            }
            $group->set('user_group_id', $userGroupId);
        }

        if (array_key_exists('active', $data)) {
            $group->set('active', (bool) $data['active']);
        }

        if (!$group->save()) {
            return Response::error(
                $this->lexicon('ms3_err_customer_group_save'),
                HttpStatus::INTERNAL_SERVER_ERROR,
            )->getData();
        }

        return Response::success(
            $this->formatGroup($group),
            $this->lexicon('ms3_customer_group_updated'),
        )->getData();
    }

    /**
     * DELETE /api/mgr/customer-groups/{id}
     *
     * @param array<string, mixed> $params
     */
    public function delete(array $params = []): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::error(
                $this->lexicon('ms3_err_customer_group_id_required'),
                HttpStatus::BAD_REQUEST,
            )->getData();
        }

        /** @var msCustomerGroup|null $group */
        $group = $this->modx->getObject(msCustomerGroup::class, $id);
        if ($group === null) {
            return Response::error(
                $this->lexicon('ms3_err_customer_group_not_found'),
                HttpStatus::NOT_FOUND,
            )->getData();
        }

        if (!$this->detachCustomersFromGroup($id)) {
            return Response::error(
                $this->lexicon('ms3_err_customer_group_detach'),
                HttpStatus::INTERNAL_SERVER_ERROR,
            )->getData();
        }

        if (!$group->remove()) {
            return Response::error(
                $this->lexicon('ms3_err_customer_group_delete'),
                HttpStatus::INTERNAL_SERVER_ERROR,
            )->getData();
        }

        return Response::success([], $this->lexicon('ms3_customer_group_deleted'))->getData();
    }

    /**
     * @return array{id: int, name: string, user_group_id: int, active: bool, created_at: ?string, updated_at: ?string}
     */
    protected function formatGroup(msCustomerGroup $group): array
    {
        return [
            'id' => (int) $group->get('id'),
            'name' => (string) $group->get('name'),
            'user_group_id' => (int) $group->get('user_group_id'),
            'active' => (bool) $group->get('active'),
            'created_at' => $group->get('created_at'),
            'updated_at' => $group->get('updated_at'),
        ];
    }

    /**
     * @param list<int> $groupIds
     *
     * @return array<int, int>
     */
    protected function getCustomerCountByGroup(array $groupIds): array
    {
        $groupIds = array_values(array_filter(array_map('intval', $groupIds), static fn (int $id): bool => $id > 0));
        if ($groupIds === []) {
            return [];
        }

        $q = $this->modx->newQuery(msCustomer::class);
        $q->select('msCustomer.customer_group_id AS gid, COUNT(msCustomer.id) AS cnt');
        $q->where(['msCustomer.customer_group_id:IN' => $groupIds]);
        $q->groupby('msCustomer.customer_group_id');

        $result = [];
        if ($q->prepare() && $q->stmt->execute()) {
            foreach ($q->stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $result[(int) $row['gid']] = (int) $row['cnt'];
            }
        }

        return $result;
    }

    protected function modUserGroupExists(int $userGroupId): bool
    {
        return $this->modx->getCount(modUserGroup::class, ['id' => $userGroupId]) > 0;
    }

    protected function detachCustomersFromGroup(int $groupId): bool
    {
        $affected = $this->modx->updateCollection(
            msCustomer::class,
            ['customer_group_id' => null],
            ['customer_group_id' => $groupId],
        );

        return $affected !== false;
    }

    protected function lexicon(string $key): string
    {
        $this->modx->lexicon->load('minishop3:default');

        return (string) $this->modx->lexicon($key);
    }
}
