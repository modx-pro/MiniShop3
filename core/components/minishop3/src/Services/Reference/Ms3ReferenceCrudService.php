<?php

namespace MiniShop3\Services\Reference;

use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Grid\ManagerListFilterPolicy;
use MiniShop3\Utils\PriceAdjustment;
use MODX\Revolution\modX;
use xPDO\Om\xPDOObject;

/**
 * Shared Manager API CRUD + M2M linking for delivery/payment references.
 */
class Ms3ReferenceCrudService
{
    public function __construct(
        protected modX $modx,
        protected ReferenceResourceConfig $config,
    ) {
    }

    public function getList(array $params = []): array
    {
        $start = (int) ($params['start'] ?? 0);
        $limit = (int) ($params['limit'] ?? 20);
        $query = trim((string) ($params['query'] ?? ''));
        // limit=0 means "all rows" (PaymentsGrid loads deliveries with limit:0).
        $returnAll = $limit === 0;

        $criteria = [];
        if ($query !== '') {
            $criteria[] = [
                'name:LIKE' => "%{$query}%",
                'OR:description:LIKE' => "%{$query}%",
            ];
        }

        foreach ($params as $key => $value) {
            if (!str_starts_with((string) $key, 'filter_') || $value === '' || $value === null) {
                continue;
            }
            ManagerListFilterPolicy::applyCriteriaFilter(
                $criteria,
                substr((string) $key, 7),
                $value,
                $this->config->filterMap
            );
        }

        $model = $this->config->modelClass;
        $total = $this->modx->getCount($model, $criteria);

        $q = $this->modx->newQuery($model, $criteria);
        if (!$returnAll) {
            $q->limit($limit, $start);
        }
        $q->sortby('position', 'ASC');

        $results = [];
        foreach ($this->modx->getIterator($model, $q) as $object) {
            $results[] = $this->formatItem($object);
        }

        return Response::success([
            'results' => $results,
            'total' => $total,
        ])->getData();
    }

    public function get(array $params = []): array
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$id) {
            return $this->errorRequiredId();
        }

        $object = $this->modx->getObject($this->config->modelClass, $id);
        if (!$object) {
            return $this->errorNotFound();
        }

        $data = $this->formatItem($object);
        if ($this->config->embedLinksKey !== null) {
            $data[$this->config->embedLinksKey] = $this->collectPeerIds($id);
        }

        return Response::success($data)->getData();
    }

    public function create(array $data = []): array
    {
        if (empty($data['name'])) {
            return Response::error(
                "{$this->config->labelSingular} name is required",
                HttpStatus::BAD_REQUEST
            )->getData();
        }

        $object = $this->modx->newObject($this->config->modelClass);
        $this->applyAllowedFields($object, $data);

        if (!isset($data['position'])) {
            $object->set('position', $this->nextPosition());
        }

        if (!$object->save()) {
            return Response::error(
                "Failed to create {$this->labelLower()}",
                HttpStatus::INTERNAL_SERVER_ERROR
            )->getData();
        }

        $this->maybeReplaceEmbeddedLinks((int) $object->get('id'), $data);

        return Response::success(
            $this->formatItem($object),
            "{$this->config->labelSingular} created successfully"
        )->getData();
    }

    public function update(array $data = []): array
    {
        $id = (int) ($data['id'] ?? 0);
        if (!$id) {
            return $this->errorRequiredId();
        }

        $object = $this->modx->getObject($this->config->modelClass, $id);
        if (!$object) {
            return $this->errorNotFound();
        }

        $this->applyAllowedFields($object, $data);

        if (!$object->save()) {
            return Response::error(
                "Failed to save {$this->labelLower()}",
                HttpStatus::INTERNAL_SERVER_ERROR
            )->getData();
        }

        $this->maybeReplaceEmbeddedLinks($id, $data);

        return Response::success(
            $this->formatItem($object),
            "{$this->config->labelSingular} updated successfully"
        )->getData();
    }

    public function delete(array $params = []): array
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$id) {
            return $this->errorRequiredId();
        }

        $object = $this->modx->getObject($this->config->modelClass, $id);
        if (!$object) {
            return $this->errorNotFound();
        }

        $this->removeMembersForOwnId($id);

        if (!$object->remove()) {
            return Response::error(
                "Failed to delete {$this->labelLower()}",
                HttpStatus::INTERNAL_SERVER_ERROR
            )->getData();
        }

        return Response::success([], "{$this->config->labelSingular} deleted successfully")->getData();
    }

    public function sort(array $data = []): array
    {
        $ids = $data['ids'] ?? [];
        if ($ids === [] || !is_array($ids)) {
            return Response::error(
                "{$this->config->labelSingular} IDs array is required for sorting",
                HttpStatus::BAD_REQUEST
            )->getData();
        }

        $position = 0;
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $object = $this->modx->getObject($this->config->modelClass, $id);
            if ($object) {
                $object->set('position', $position);
                $object->save();
                $position++;
            }
        }

        return Response::success(
            [],
            "{$this->config->labelPlural} reordered successfully"
        )->getData();
    }

    public function bulkDelete(array $data = []): array
    {
        $ids = $data['ids'] ?? [];
        if ($ids === [] || !is_array($ids)) {
            return Response::error(
                "{$this->config->labelSingular} IDs array is required",
                HttpStatus::BAD_REQUEST
            )->getData();
        }

        $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0));
        if ($ids === []) {
            return Response::error(
                "No valid {$this->labelLower()} IDs provided",
                HttpStatus::BAD_REQUEST
            )->getData();
        }

        $deleted = 0;
        $failed = 0;
        foreach ($ids as $id) {
            $object = $this->modx->getObject($this->config->modelClass, $id);
            if (!$object) {
                $failed++;
                continue;
            }
            $this->removeMembersForOwnId($id);
            if ($object->remove()) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        if ($deleted === 0) {
            return Response::error(
                "Failed to delete {$this->labelLowerPlural()}",
                HttpStatus::INTERNAL_SERVER_ERROR
            )->getData();
        }

        return Response::success(
            ['deleted' => $deleted, 'failed' => $failed],
            "Deleted {$deleted} {$this->labelLowerPlural()}"
        )->getData();
    }

    public function updatePositions(array $data = []): array
    {
        $positions = $data['positions'] ?? [];
        if ($positions === [] || !is_array($positions)) {
            return Response::error('Positions array is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $updated = 0;
        foreach ($positions as $id => $position) {
            $object = $this->modx->getObject($this->config->modelClass, (int) $id);
            if (!$object) {
                continue;
            }
            $object->set('position', (int) $position);
            if ($object->save()) {
                $updated++;
            }
        }

        return Response::success(
            ['updated' => $updated],
            "Updated {$updated} positions"
        )->getData();
    }

    public function listLinks(array $params = []): array
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$id) {
            return $this->errorRequiredId();
        }

        $results = [];
        foreach ($this->iterateMembersForOwnId($id) as $member) {
            $results[] = [
                'delivery_id' => (int) $member->get('delivery_id'),
                'payment_id' => (int) $member->get('payment_id'),
            ];
        }

        return Response::success(['results' => $results])->getData();
    }

    public function addLink(array $data = []): array
    {
        $ownId = (int) ($data[$this->config->memberOwnFk] ?? 0);
        $peerId = (int) ($data[$this->config->memberPeerFk] ?? 0);

        if (!$ownId || !$peerId) {
            return Response::error(
                "{$this->config->labelSingular} ID and {$this->config->peerLabelSingular} ID are required",
                HttpStatus::BAD_REQUEST
            )->getData();
        }

        $criteria = $this->memberLinkCriteria($ownId, $peerId);

        if ($this->modx->getObject(msDeliveryMember::class, $criteria)) {
            return Response::success(
                [],
                "{$this->config->peerLabelSingular} already linked to {$this->labelLower()}"
            )->getData();
        }

        $member = $this->modx->newObject(msDeliveryMember::class);
        $member->fromArray($criteria);

        if (!$member->save()) {
            return Response::error(
                "Failed to add {$this->peerLabelLower()} to {$this->labelLower()}",
                HttpStatus::INTERNAL_SERVER_ERROR
            )->getData();
        }

        return Response::success(
            [],
            "{$this->config->peerLabelSingular} added to {$this->labelLower()}"
        )->getData();
    }

    public function removeLink(array $params = []): array
    {
        $ownId = (int) ($params['id'] ?? 0);
        $peerId = (int) ($params[$this->config->memberPeerFk] ?? 0);

        if (!$ownId || !$peerId) {
            return Response::error(
                "{$this->config->labelSingular} ID and {$this->config->peerLabelSingular} ID are required",
                HttpStatus::BAD_REQUEST
            )->getData();
        }

        $member = $this->modx->getObject(
            msDeliveryMember::class,
            $this->memberLinkCriteria($ownId, $peerId)
        );
        if (!$member) {
            return Response::error(
                "{$this->config->peerLabelSingular} link not found",
                HttpStatus::NOT_FOUND
            )->getData();
        }

        if (!$member->remove()) {
            return Response::error(
                "Failed to remove {$this->peerLabelLower()} from {$this->labelLower()}",
                HttpStatus::INTERNAL_SERVER_ERROR
            )->getData();
        }

        return Response::success(
            [],
            "{$this->config->peerLabelSingular} removed from {$this->labelLower()}"
        )->getData();
    }

    /**
     * Replace all peer links for an own-side id (delivery write path).
     *
     * @param list<int|string> $peerIds
     */
    public function replaceLinks(int $ownId, array $peerIds): void
    {
        $this->removeMembersForOwnId($ownId);

        foreach ($peerIds as $peerId) {
            $peerId = (int) $peerId;
            if ($peerId <= 0) {
                continue;
            }
            $member = $this->modx->newObject(msDeliveryMember::class);
            $member->set($this->config->memberOwnFk, $ownId);
            $member->set($this->config->memberPeerFk, $peerId);
            $member->save();
        }
    }

    /**
     * @return list<int>
     */
    public function collectPeerIds(int $ownId): array
    {
        $ids = [];
        foreach ($this->iterateMembersForOwnId($ownId) as $member) {
            $ids[] = (int) $member->get($this->config->memberPeerFk);
        }

        return $ids;
    }

    public function formatItem(xPDOObject $object): array
    {
        $data = [
            'id' => (int) $object->get('id'),
        ];
        $floatFields = array_fill_keys($this->config->floatFields, true);

        foreach ($this->config->allowedFields as $field) {
            $raw = $object->get($field);
            if ($field === 'position') {
                $data[$field] = (int) $raw;
            } elseif ($field === 'active') {
                $data[$field] = (bool) $raw;
            } elseif (isset($floatFields[$field])) {
                $data[$field] = (float) $raw;
            } else {
                $data[$field] = $raw;
            }
        }

        return $data;
    }

    protected function applyAllowedFields(xPDOObject $object, array $data): void
    {
        foreach ($this->config->allowedFields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $object->set($field, $this->prepareFieldValue($field, $data[$field]));
        }
    }

    protected function prepareFieldValue(string $field, mixed $value): mixed
    {
        return $field === 'price' ? PriceAdjustment::normalize($value) : $value;
    }

    protected function nextPosition(): int
    {
        $q = $this->modx->newQuery($this->config->modelClass);
        $q->sortby('position', 'DESC');
        $q->limit(1);
        $last = $this->modx->getObject($this->config->modelClass, $q);

        return $last ? ((int) $last->get('position') + 1) : 1;
    }

    protected function maybeReplaceEmbeddedLinks(int $ownId, array $data): void
    {
        $key = $this->config->embedLinksKey;
        if ($key === null || !isset($data[$key]) || !is_array($data[$key])) {
            return;
        }

        $this->replaceLinks($ownId, $data[$key]);
    }

    /**
     * @return array<string, int>
     */
    protected function memberLinkCriteria(int $ownId, int $peerId): array
    {
        return [
            $this->config->memberOwnFk => $ownId,
            $this->config->memberPeerFk => $peerId,
        ];
    }

    /**
     * @return iterable<object>
     */
    protected function iterateMembersForOwnId(int $ownId): iterable
    {
        return $this->modx->getIterator(
            msDeliveryMember::class,
            [$this->config->memberOwnFk => $ownId]
        );
    }

    protected function removeMembersForOwnId(int $ownId): void
    {
        foreach ($this->iterateMembersForOwnId($ownId) as $member) {
            $member->remove();
        }
    }

    protected function errorRequiredId(): array
    {
        return Response::error(
            "{$this->config->labelSingular} ID is required",
            HttpStatus::BAD_REQUEST
        )->getData();
    }

    protected function errorNotFound(): array
    {
        return Response::error(
            "{$this->config->labelSingular} not found",
            HttpStatus::NOT_FOUND
        )->getData();
    }

    protected function labelLower(): string
    {
        return strtolower($this->config->labelSingular);
    }

    protected function labelLowerPlural(): string
    {
        return strtolower($this->config->labelPlural);
    }

    protected function peerLabelLower(): string
    {
        return strtolower($this->config->peerLabelSingular);
    }
}
