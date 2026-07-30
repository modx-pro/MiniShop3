<?php

declare(strict_types=1);

namespace MiniShop3\Services\Settings;

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Model\msPayment;
use MiniShop3\Model\msVendor;
use MODX\Revolution\modX;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;

/**
 * Canonical combo/dropdown lists for Settings entities (Vendor / Delivery / Payment).
 *
 * Shared by Manager REST dropdowns and Settings\*\\GetList processors (combo=true)
 * so ExtJS combos and Vue REST do not maintain two independent list queries (#346).
 */
final class SettingsComboListService
{
    public function __construct(private modX $modx)
    {
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function listVendorsForCombo(int $includeId = 0, string $query = ''): array
    {
        return $this->toComboRows($this->iterateVendors($includeId, $query));
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function listActiveDeliveriesForCombo(int $includeId = 0, string $query = ''): array
    {
        return $this->toComboRows($this->iterateActiveDeliveries($includeId, $query));
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function listActivePaymentsForCombo(int $includeId = 0, int $deliveryId = 0, string $query = ''): array
    {
        return $this->toComboRows($this->iterateActivePayments($includeId, $deliveryId, $query));
    }

    /**
     * @return iterable<msDelivery>
     */
    public function iterateActiveDeliveries(int $includeId = 0, string $query = ''): iterable
    {
        $c = $this->modx->newQuery(msDelivery::class);
        $c->where($this->activeOrIncludeCriteria($includeId));
        $c->sortby('position', 'ASC');
        $this->applyTextQuery($c, $query, ['name', 'description', 'class']);

        yield from $this->modx->getIterator(msDelivery::class, $c);
    }

    /**
     * @return iterable<msVendor>
     */
    private function iterateVendors(int $includeId = 0, string $query = ''): iterable
    {
        if ($includeId > 0) {
            $c = $this->modx->newQuery(msVendor::class, ['id' => $includeId]);
            $c->select('id,name');
            yield from $this->modx->getIterator(msVendor::class, $c);

            return;
        }

        $c = $this->modx->newQuery(msVendor::class);
        $c->select('id,name');
        $c->sortby('name', 'ASC');
        $this->applyTextQuery($c, $query, ['name', 'description', 'country', 'email', 'address']);

        yield from $this->modx->getIterator(msVendor::class, $c);
    }

    /**
     * @return iterable<msPayment>
     */
    public function iterateActivePayments(int $includeId = 0, int $deliveryId = 0, string $query = ''): iterable
    {
        $c = $this->modx->newQuery(msPayment::class);
        $c->where($this->activeOrIncludeCriteria($includeId));

        if ($deliveryId > 0) {
            $c->innerJoin(
                msDeliveryMember::class,
                'Member',
                'Member.payment_id = msPayment.id AND Member.delivery_id = ' . $deliveryId
            );
        }

        $c->sortby('position', 'ASC');
        $this->applyTextQuery($c, $query, ['name', 'description', 'class']);

        yield from $this->modx->getIterator(msPayment::class, $c);
    }

    /**
     * Pin a single record by id when the client is not browsing a paged list.
     * Matches ExtJS combo: includeId only when limit is unset/0.
     */
    public static function resolvePinnedIncludeId(int $id, mixed $limit = null): int
    {
        if ($id <= 0) {
            return 0;
        }

        if ($limit !== null && $limit !== '' && (int) $limit > 0) {
            return 0;
        }

        return $id;
    }

    /**
     * @param iterable<xPDOObject> $objects
     * @return list<array{id: int, name: string}>
     */
    private function toComboRows(iterable $objects): array
    {
        $rows = [];
        foreach ($objects as $object) {
            $rows[] = [
                'id' => (int) $object->get('id'),
                'name' => (string) $object->get('name'),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function activeOrIncludeCriteria(int $includeId): array
    {
        if ($includeId > 0) {
            return [
                'active' => 1,
                'OR:id:=' => $includeId,
            ];
        }

        return ['active' => 1];
    }

    /**
     * @param list<string> $fields
     */
    private function applyTextQuery(xPDOQuery $c, string $query, array $fields): void
    {
        $query = trim($query);
        if ($query === '' || $fields === []) {
            return;
        }

        $criteria = [];
        foreach ($fields as $i => $field) {
            $key = $i === 0 ? "{$field}:LIKE" : "OR:{$field}:LIKE";
            $criteria[$key] = "%{$query}%";
        }
        $c->where($criteria);
    }
}
