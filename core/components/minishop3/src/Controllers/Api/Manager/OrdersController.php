<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Model\msPayment;
use MiniShop3\Router\Response;
use MiniShop3\Services\FilterConfigManager;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modX;

/**
 * API controller for order management (Manager API)
 *
 * Handles CRUD operations for orders in admin panel.
 *
 * @package MiniShop3\Controllers\Api\Manager
 */
class OrdersController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Get list of orders with pagination, search and filters
     * GET /api/mgr/orders
     *
     * @param array $params URL parameters (start, limit, query, status_id, customer, context, date_start, date_end)
     * @return array Response
     */
    public function getList(array $params = []): array
    {
        $start = (int)($params['start'] ?? 0);
        $limit = (int)($params['limit'] ?? 20);
        $query = trim($params['query'] ?? '');
        $sort = $params['sort'] ?? 'id';
        $dir = strtoupper($params['dir'] ?? 'DESC');

        $gridConfig = $this->modx->services->get('ms3_grid_config');
        $gridFields = $gridConfig ? $gridConfig->getGridConfig('orders') : [];

        $c = $this->modx->newQuery(msOrder::class);

        $c->leftJoin(msOrderStatus::class, 'Status');
        $c->leftJoin(msDelivery::class, 'Delivery');
        $c->leftJoin(msPayment::class, 'Payment');
        $c->leftJoin(msOrderAddress::class, 'Address', '`Address`.order_id = msOrder.id');

        $showDrafts = $this->modx->getOption('ms3_order_show_drafts', null, false);
        if (!$showDrafts) {
            $statusDrafts = $this->modx->getOption('ms3_status_draft', null, 1);
            $c->where(['status_id:!=' => $statusDrafts]);
        }

        if (!empty($query)) {
            if (is_numeric($query)) {
                $c->andCondition([
                    'id' => $query,
                    'OR:Address.phone:LIKE' => "%{$query}%",
                ]);
            } else {
                $c->where([
                    'num:LIKE' => "{$query}%",
                    'OR:order_comment:LIKE' => "%{$query}%",
                    'OR:Address.comment:LIKE' => "%{$query}%",
                    'OR:Address.first_name:LIKE' => "%{$query}%",
                    'OR:Address.last_name:LIKE' => "%{$query}%",
                    'OR:Address.email:LIKE' => "%{$query}%",
                    'OR:Address.phone:LIKE' => "%{$query}%",
                ]);
            }
        }

        foreach ($params as $key => $value) {
            if (strpos($key, 'filter_') === 0 && !empty($value)) {
                $fieldName = substr($key, 7);
                $this->applyFilter($c, $fieldName, $value);
            }
        }

        // Direct filter params (from filter config)
        if ($statusId = ($params['status_id'] ?? null)) {
            $c->where(['status_id' => (int)$statusId]);
        }
        if ($deliveryId = ($params['delivery_id'] ?? null)) {
            $c->where(['delivery_id' => (int)$deliveryId]);
        }
        if ($paymentId = ($params['payment_id'] ?? null)) {
            $c->where(['payment_id' => (int)$paymentId]);
        }
        if ($contextKey = ($params['context_key'] ?? null)) {
            $c->where(['context' => $contextKey]);
        }

        // Date range filters
        if ($dateFrom = ($params['createdon_from'] ?? $params['date_start'] ?? null)) {
            $c->andCondition([
                'msOrder.createdon:>=' => date('Y-m-d 00:00:00', strtotime($dateFrom)),
            ], null, 1);
        }
        if ($dateTo = ($params['createdon_to'] ?? $params['date_end'] ?? null)) {
            $c->andCondition([
                'msOrder.createdon:<=' => date('Y-m-d 23:59:59', strtotime($dateTo)),
            ], null, 1);
        }

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

        $exclude = ['status_id', 'delivery_id', 'payment_id'];
        $c->select(
            $this->modx->getSelectColumns(msOrder::class, 'msOrder', '', $exclude, true) . ',
            `msOrder`.status_id, `msOrder`.delivery_id, `msOrder`.payment_id,
            `Address`.first_name, `Address`.last_name, `Address`.phone, `Address`.email,
            `Status`.name as `status_name`, `Status`.color,
            `Delivery`.name as `delivery_name`,
            `Payment`.name as `payment_name`'
        );
        $c->groupby('msOrder.id');

        $sortField = $this->mapSortField($sort);
        $c->sortby($sortField, $dir);

        if ($limit > 0) {
            $c->limit($limit, $start);
        }

        $c->prepare();
        $rows = $c->stmt->execute() ? $c->stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        $results = [];
        foreach ($rows as $row) {
            $results[] = $this->formatOrder($row);
        }

        $stats = $this->getOrdersStats($params);

        return Response::success([
            'results' => $results,
            'total' => $total,
            'stats' => $stats
        ])->getData();
    }

    /**
     * Get specific order
     * GET /api/mgr/orders/{id}
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function get(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Order ID is required', 400)->getData();
        }

        $order = $this->modx->getObject(msOrder::class, $id);

        if (!$order) {
            return Response::error('Order not found', 404)->getData();
        }

        $status = $order->getOne('Status');
        $delivery = $order->getOne('Delivery');
        $payment = $order->getOne('Payment');
        $address = $this->modx->getObject(msOrderAddress::class, ['order_id' => $id]);

        $data = $order->toArray();
        $data['status_name'] = $status ? $status->get('name') : '';
        $data['color'] = $status ? $status->get('color') : '';
        $data['delivery_name'] = $delivery ? $delivery->get('name') : '';
        $data['payment_name'] = $payment ? $payment->get('name') : '';

        if ($address) {
            $data['first_name'] = $address->get('first_name');
            $data['last_name'] = $address->get('last_name');
            $data['phone'] = $address->get('phone');
            $data['email'] = $address->get('email');
        }

        return Response::success($this->formatOrder($data))->getData();
    }

    /**
     * Delete order
     * DELETE /api/mgr/orders/{id}
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function delete(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Order ID is required', 400)->getData();
        }

        $order = $this->modx->getObject(msOrder::class, $id);

        if (!$order) {
            return Response::error('Order not found', 404)->getData();
        }

        $addresses = $this->modx->getIterator(msOrderAddress::class, ['order_id' => $id]);
        foreach ($addresses as $address) {
            $address->remove();
        }

        $products = $this->modx->getIterator(\MiniShop3\Model\msOrderProduct::class, ['order_id' => $id]);
        foreach ($products as $product) {
            $product->remove();
        }

        if (!$order->remove()) {
            return Response::error('Failed to delete order', 500)->getData();
        }

        return Response::success([], 'Order deleted successfully')->getData();
    }

    /**
     * Update order
     * PUT /api/mgr/orders/{id}
     *
     * @param array $params URL and body parameters
     * @return array Response
     */
    public function update(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Order ID is required', 400)->getData();
        }

        $order = $this->modx->getObject(msOrder::class, $id);

        if (!$order) {
            return Response::error('Order not found', 404)->getData();
        }

        $oldStatusId = $order->get('status_id');

        if (isset($params['status_id'])) {
            $order->set('status_id', (int)$params['status_id']);
        }
        if (isset($params['delivery_id'])) {
            $order->set('delivery_id', (int)$params['delivery_id']);
        }
        if (isset($params['payment_id'])) {
            $order->set('payment_id', (int)$params['payment_id']);
        }
        if (isset($params['order_comment'])) {
            $order->set('order_comment', $params['order_comment']);
        }

        $order->set('updatedon', date('Y-m-d H:i:s'));

        if (!$order->save()) {
            return Response::error('Failed to update order', 500)->getData();
        }

        $address = $this->modx->getObject(msOrderAddress::class, ['order_id' => $id]);
        if ($address) {
            $addressFields = ['first_name', 'last_name', 'phone', 'email', 'city', 'street', 'building', 'room'];
            foreach ($addressFields as $field) {
                if (isset($params[$field])) {
                    $address->set($field, $params[$field]);
                }
            }
            $address->save();
        }

        if ($oldStatusId != $order->get('status_id')) {
            $this->logStatusChange($order, $oldStatusId, $order->get('status_id'));
        }

        return Response::success($order->toArray(), 'Order updated successfully')->getData();
    }

    /**
     * Get order products
     * GET /api/mgr/orders/{id}/products
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function getProducts(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Order ID is required', 400)->getData();
        }

        $c = $this->modx->newQuery(\MiniShop3\Model\msOrderProduct::class);
        $c->where(['order_id' => $id]);
        $c->leftJoin(\MiniShop3\Model\msProduct::class, 'Product', 'msOrderProduct.product_id = Product.id');
        $c->select($this->modx->getSelectColumns(\MiniShop3\Model\msOrderProduct::class, 'msOrderProduct'));
        $c->select(['Product.pagetitle']);

        $products = [];
        $collection = $this->modx->getIterator(\MiniShop3\Model\msOrderProduct::class, $c);
        foreach ($collection as $product) {
            $data = $product->toArray();
            $data['pagetitle'] = $product->get('pagetitle');
            $products[] = $data;
        }

        return Response::success(['results' => $products])->getData();
    }

    /**
     * Get order logs (history)
     * GET /api/mgr/orders/{id}/logs
     *
     * @param array $params URL parameters (id)
     * @return array Response
     */
    public function getLogs(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Order ID is required', 400)->getData();
        }

        $c = $this->modx->newQuery(\MiniShop3\Model\msOrderLog::class);
        $c->where(['order_id' => $id]);
        $c->sortby('timestamp', 'DESC');

        $logs = [];
        $collection = $this->modx->getIterator(\MiniShop3\Model\msOrderLog::class, $c);
        foreach ($collection as $log) {
            $data = $log->toArray();
            if ($data['user_id']) {
                $user = $this->modx->getObject(\MODX\Revolution\modUser::class, $data['user_id']);
                $data['user_name'] = $user ? $user->get('username') : 'Unknown';
            }
            $logs[] = $data;
        }

        return Response::success(['results' => $logs])->getData();
    }

    /**
     * Log status change
     *
     * @param msOrder $order Order object
     * @param int $oldStatusId Old status ID
     * @param int $newStatusId New status ID
     */
    protected function logStatusChange(msOrder $order, int $oldStatusId, int $newStatusId): void
    {
        $oldStatus = $this->modx->getObject(msOrderStatus::class, $oldStatusId);
        $newStatus = $this->modx->getObject(msOrderStatus::class, $newStatusId);

        $log = $this->modx->newObject(\MiniShop3\Model\msOrderLog::class);
        $log->set('order_id', $order->get('id'));
        $log->set('user_id', $this->modx->user->get('id'));
        $log->set('timestamp', date('Y-m-d H:i:s'));
        $log->set('action', 'status');
        $log->set('entry', sprintf(
            'Status changed: %s → %s',
            $oldStatus ? $oldStatus->get('name') : $oldStatusId,
            $newStatus ? $newStatus->get('name') : $newStatusId
        ));
        $log->save();
    }

    /**
     * Get filters configuration
     * GET /api/mgr/orders/filters
     *
     * @param array $params URL parameters
     * @return array Response with filters config
     */
    public function getFilters(array $params = []): array
    {
        $filterManager = new FilterConfigManager($this->modx);
        $filters = $filterManager->getFilters('orders', true);

        // Sort by position
        uasort($filters, fn($a, $b) => ($a['position'] ?? 100) <=> ($b['position'] ?? 100));

        return Response::success(['filters' => $filters])->getData();
    }

    /**
     * Get orders statistics
     *
     * @param array $params Filter parameters
     * @return array Statistics data
     */
    protected function getOrdersStats(array $params = []): array
    {
        $month = $this->modx->newQuery(msOrder::class);
        $statuses = [2, 3];
        $month->where(['status_id:IN' => $statuses]);
        $month->where('createdon BETWEEN NOW() - INTERVAL 30 DAY AND NOW()');
        $month->select('SUM(msOrder.cost) as sum, COUNT(msOrder.id) as total');
        $month->prepare();
        $month->stmt->execute();
        $monthData = $month->stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'month_sum' => number_format(round($monthData['sum'] ?? 0), 0, '.', ' '),
            'month_total' => number_format($monthData['total'] ?? 0, 0, '.', ' '),
        ];
    }

    /**
     * Apply filter to query
     *
     * @param \xPDO\Om\xPDOQuery $c Query object
     * @param string $fieldName Field name
     * @param mixed $value Filter value
     */
    protected function applyFilter($c, string $fieldName, $value): void
    {
        switch ($fieldName) {
            case 'status':
            case 'status_id':
                $c->where(['status_id' => (int)$value]);
                break;
            case 'delivery':
            case 'delivery_id':
                $c->where(['delivery_id' => (int)$value]);
                break;
            case 'payment':
            case 'payment_id':
                $c->where(['payment_id' => (int)$value]);
                break;
            case 'context':
                $c->where(['context' => $value]);
                break;
            case 'customer':
                $c->where([
                    'Address.first_name:LIKE' => "%{$value}%",
                    'OR:Address.last_name:LIKE' => "%{$value}%",
                ]);
                break;
            case 'email':
                $c->where(['Address.email:LIKE' => "%{$value}%"]);
                break;
            case 'phone':
                $c->where(['Address.phone:LIKE' => "%{$value}%"]);
                break;
            case 'num':
                $c->where(['num:LIKE' => "{$value}%"]);
                break;
            default:
                break;
        }
    }

    /**
     * Map sort field to database column
     *
     * @param string $sort Sort field name
     * @return string Database column
     */
    protected function mapSortField(string $sort): string
    {
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
            'status_name' => 'Status.name',
            'delivery_id' => 'msOrder.delivery_id',
            'delivery_name' => 'Delivery.name',
            'payment_id' => 'msOrder.payment_id',
            'payment_name' => 'Payment.name',
            'context' => 'msOrder.context',
        ];

        return $mapping[$sort] ?? 'msOrder.id';
    }

    /**
     * Format order data for API response
     *
     * @param array $data Order data
     * @return array Formatted data
     */
    protected function formatOrder(array $data): array
    {
        $ms3 = $this->modx->services->get('ms3');

        if (!empty($data['status_name']) && str_starts_with($data['status_name'], 'ms3_order_status_')) {
            $translated = $this->modx->lexicon($data['status_name']);
            if ($translated !== $data['status_name']) {
                $data['status_name'] = $translated;
            }
        }

        $data['customer'] = trim(implode(' ', [
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
        ]));

        if ($ms3) {
            if (isset($data['cost'])) {
                $data['cost_formatted'] = $ms3->format->price($data['cost']);
            }
            if (isset($data['cart_cost'])) {
                $data['cart_cost_formatted'] = $ms3->format->price($data['cart_cost']);
            }
            if (isset($data['delivery_cost'])) {
                $data['delivery_cost_formatted'] = $ms3->format->price($data['delivery_cost']);
            }
            if (isset($data['weight'])) {
                $data['weight_formatted'] = $ms3->format->weight($data['weight']);
            }
        }

        return $data;
    }
}
