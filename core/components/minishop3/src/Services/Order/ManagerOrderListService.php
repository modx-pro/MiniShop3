<?php

namespace MiniShop3\Services\Order;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Services\FilterConfigManager;
use MiniShop3\Services\Grid\ManagerListFilterPolicy;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

class ManagerOrderListService
{
    public const DIRECT_FILTER_KEYS = [
        'query', // handled separately in getList(), not via applyDirectFilters
        'status_id',
        'delivery_id',
        'payment_id',
        'context_key',
        'createdon_from', // date keys handled in applyDirectFilters(), not in FIELD_MAP
        'createdon_to',
    ];

    public const DIRECT_FILTER_INT_KEYS = [
        'status_id',
        'delivery_id',
        'payment_id',
    ];

    /** Param key => msOrder column for direct (unprefixed) filter params. */
    public const DIRECT_FILTER_FIELD_MAP = [
        'status_id' => 'status_id',
        'delivery_id' => 'delivery_id',
        'payment_id' => 'payment_id',
        'context_key' => 'context',
    ];

    public const ADDRESS_FILTER_KEYS = [
        'customer',
        'email',
        'phone',
    ];

    protected modX $modx;
    protected ManagerOrderPresenter $presenter;

    public function __construct(modX $modx, ?ManagerOrderPresenter $presenter = null)
    {
        $this->modx = $modx;
        $this->presenter = $presenter ?? new ManagerOrderPresenter($modx);
    }

    public static function getDirectFilterKeys(): array
    {
        return self::DIRECT_FILTER_KEYS;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{results: array<int, array<string, mixed>>, total: int, stats?: array<string, string>}
     */
    public function getList(array $params): array
    {
        $start = (int)($params['start'] ?? 0);
        $limit = (int)($params['limit'] ?? 20);
        $query = trim((string)($params['query'] ?? ''));
        $sort = (string)($params['sort'] ?? 'id');
        $dir = strtoupper((string)($params['dir'] ?? 'DESC'));

        $gridConfig = $this->modx->services->get('ms3_grid_config');
        // Get ALL fields including hidden (for relation JOINs)
        $gridFields = $gridConfig ? $gridConfig->getGridConfig('orders', true) : [];

        $c = $this->modx->newQuery(msOrder::class);

        $needsAddressJoin = ManagerOrderListQueryService::needsAddressJoin($params, $query, $gridFields, $sort);
        if ($needsAddressJoin) {
            $c->leftJoin(msOrderAddress::class, 'Address', '`Address`.order_id = msOrder.id');
        }

        // Dynamic JOINs from relation fields in grid config
        $relationGroups = $gridConfig ? $gridConfig->extractRelationFields($gridFields) : [];
        foreach ($relationGroups as $group) {
            if (!empty($group['modelClass'])) {
                $c->leftJoin(
                    $group['modelClass'],
                    $group['alias'],
                    "`{$group['alias']}`.id = msOrder.{$group['foreignKey']}"
                );
            }
        }

        $this->applyDraftVisibilityFilter($c, $params);

        if ($query !== '') {
            if (is_numeric($query)) {
                $condition = ['id' => $query];
                if ($needsAddressJoin) {
                    $condition['OR:Address.phone:LIKE'] = "%{$query}%";
                }
                $c->andCondition($condition);
            } else {
                $where = [
                    'num:LIKE' => "{$query}%",
                    'OR:order_comment:LIKE' => "%{$query}%",
                ];
                if ($needsAddressJoin) {
                    $where['OR:Address.comment:LIKE'] = "%{$query}%";
                    $where['OR:Address.first_name:LIKE'] = "%{$query}%";
                    $where['OR:Address.last_name:LIKE'] = "%{$query}%";
                    $where['OR:Address.email:LIKE'] = "%{$query}%";
                    $where['OR:Address.phone:LIKE'] = "%{$query}%";
                }
                $c->where($where);
            }
        }

        foreach ($params as $key => $value) {
            if (str_starts_with((string)$key, 'filter_') && $value !== '' && $value !== null) {
                $fieldName = substr((string)$key, 7);
                $this->applyFilter($c, $fieldName, $value);
            }
        }

        $this->applyDirectFilters($c, $params);

        // Legacy params for backward compatibility
        if ($customer = ($params['customer'] ?? null)) {
            $c->where(['customer_id' => (int)$customer]);
        }
        if ($context = ($params['context'] ?? null)) {
            $c->where(['context' => $context]);
        }

        $countQuery = clone $c;
        $countQuery->select('COUNT(DISTINCT msOrder.id)');
        $countQuery->prepare();
        $countQuery->stmt->execute();
        $total = (int)$countQuery->stmt->fetchColumn();

        // Build SELECT: base model fields + optional address + dynamic relation fields
        $selectParts = [
            $this->modx->getSelectColumns(msOrder::class, 'msOrder'),
        ];
        if ($needsAddressJoin) {
            $selectParts[] = '`Address`.first_name, `Address`.last_name, `Address`.phone, `Address`.email';
        }

        // Add SELECT for relation fields
        foreach ($relationGroups as $group) {
            foreach ($group['fields'] as $fieldDef) {
                $selectParts[] = "`{$group['alias']}`.{$fieldDef['displayField']} as `{$fieldDef['name']}`";
            }
        }

        $c->select(implode(', ', $selectParts));

        $sortField = $this->mapSortField($sort);
        $c->sortby($sortField, $dir);

        if ($limit > 0) {
            $c->limit($limit, $start);
        }

        $c->prepare();
        $rows = $c->stmt->execute() ? $c->stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        $results = [];
        foreach ($rows as $row) {
            $results[] = $this->presenter->formatOrder($row);
        }

        $payload = [
            'results' => $results,
            'total' => $total,
        ];
        if (ManagerOrderListQueryService::shouldIncludeStats($params)) {
            $payload['stats'] = $this->getOrdersStats($params);
        }

        return $payload;
    }

    /**
     * @return array{filters: array<string, mixed>}
     */
    public function getFilters(): array
    {
        $filterManager = new FilterConfigManager($this->modx);
        $filters = $filterManager->getFilters('orders', true);

        // Sort by position
        uasort($filters, fn($a, $b) => ($a['position'] ?? 100) <=> ($b['position'] ?? 100));

        return ['filters' => $filters];
    }

    /**
     * Statistics are calculated only for orders with statuses
     * specified in ms3_status_for_stat setting (e.g. "2,3" for paid/completed).
     *
     * @param array<string, mixed> $params
     * @return array{month_sum: string, month_total: string}
     */
    public function getOrdersStats(array $params = []): array
    {
        $c = $this->modx->newQuery(msOrder::class);

        if (ManagerOrderListQueryService::hasAddressFilter($params)) {
            $c->leftJoin(msOrderAddress::class, 'Address', '`Address`.order_id = msOrder.id');
        }

        // Filter by statuses for statistics (ms3_status_for_stat)
        // Only count orders with these statuses (e.g. paid, completed)
        $statusForStat = $this->modx->getOption('ms3_status_for_stat', null, '2,3');
        if (!empty($statusForStat)) {
            $statuses = array_map('intval', array_filter(explode(',', (string)$statusForStat)));
            if (!empty($statuses)) {
                $c->where(['status_id:IN' => $statuses]);
            }
        }

        // Apply filter_ prefixed params
        foreach ($params as $key => $value) {
            if (str_starts_with((string)$key, 'filter_') && $value !== '' && $value !== null) {
                $fieldName = substr((string)$key, 7);
                $this->applyFilter($c, $fieldName, $value);
            }
        }

        $this->applyDirectFilters($c, $params);

        // Calculate sum and count
        $c->select('SUM(msOrder.cost) as sum, COUNT(msOrder.id) as total');
        $c->prepare();
        $c->stmt->execute();
        $data = $c->stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'month_sum' => number_format(round($data['sum'] ?? 0), 0, '.', ' '),
            'month_total' => number_format($data['total'] ?? 0, 0, '.', ' '),
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function hasAddressFilter(array $params): bool
    {
        return ManagerOrderListQueryService::hasAddressFilter($params);
    }

    /**
     * Whether draft orders should be included in the manager orders list query.
     *
     * When `show_drafts` is present in request params it overrides `ms3_order_show_drafts`.
     * The Vue orders grid always sends this flag (initialized from ms3.config.order_show_drafts).
     *
     * @param array<string, mixed> $params
     */
    protected function shouldShowDrafts(array $params): bool
    {
        $default = (bool)$this->modx->getOption('ms3_order_show_drafts', null, false);

        if (!array_key_exists('show_drafts', $params)) {
            return $default;
        }

        $value = $params['show_drafts'];
        if ($value === '' || $value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Exclude draft status from getList query unless drafts are explicitly shown.
     *
     * @param array<string, mixed> $params
     */
    protected function applyDraftVisibilityFilter(xPDOQuery $c, array $params): void
    {
        if ($this->shouldShowDrafts($params)) {
            return;
        }

        $statusDrafts = (int)$this->modx->getOption('ms3_status_draft', null, 1) ?: 1;
        $c->where(['status_id:!=' => $statusDrafts]);
    }

    /**
     * @param mixed $value
     */
    protected function applyFilter(xPDOQuery $c, string $fieldName, mixed $value): void
    {
        ManagerListFilterPolicy::applyOrderFilter($c, $fieldName, $value);
    }

    /**
     * Apply direct (unprefixed) filter params to an orders query.
     *
     * @param array<string, mixed> $params
     */
    protected function applyDirectFilters(xPDOQuery $c, array $params): void
    {
        foreach (self::DIRECT_FILTER_FIELD_MAP as $paramKey => $fieldName) {
            $value = $params[$paramKey] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            if (in_array($paramKey, self::DIRECT_FILTER_INT_KEYS, true)) {
                $value = (int)$value;
            }

            $c->where([$fieldName => $value]);
        }

        $dateFrom = $params['createdon_from'] ?? $params['date_start'] ?? null;
        if ($dateFrom) {
            $c->where([
                'msOrder.createdon:>=' => date('Y-m-d 00:00:00', strtotime((string)$dateFrom)),
            ]);
        }

        $dateTo = $params['createdon_to'] ?? $params['date_end'] ?? null;
        if ($dateTo) {
            $c->where([
                'msOrder.createdon:<=' => date('Y-m-d 23:59:59', strtotime((string)$dateTo)),
            ]);
        }
    }

    /**
     * Map sort field to database column.
     */
    protected function mapSortField(string $sort): string
    {
        // Model fields mapping
        $mapping = [
            'id' => 'msOrder.id',
            'num' => 'msOrder.num',
            'cost' => 'msOrder.cost',
            'cart_cost' => 'msOrder.cart_cost',
            'delivery_cost' => 'msOrder.delivery_cost',
            'weight' => 'msOrder.weight',
            'createdon' => 'msOrder.createdon',
            'updatedon' => 'msOrder.updatedon',
            'status_id' => 'msOrder.status_id',
            'delivery_id' => 'msOrder.delivery_id',
            'payment_id' => 'msOrder.payment_id',
            'context' => 'msOrder.context',
        ];

        // For relation fields (status_name, delivery_name, etc.) - sort by the alias
        // This works because we SELECT them AS `field_name`
        return $mapping[$sort] ?? 'msOrder.id';
    }
}
