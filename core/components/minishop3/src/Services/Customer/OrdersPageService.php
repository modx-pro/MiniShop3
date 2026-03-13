<?php

namespace MiniShop3\Services\Customer;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Model\msProductData;
use MODX\Revolution\modResource;

/**
 * OrdersPageService - customer order history page service
 *
 * Displays complete information about customer orders:
 * - List of all orders (with pagination)
 * - Detailed information about specific order
 * - Filtering by status
 *
 * Example usage in snippet:
 * ```php
 * [[!msCustomer?
 *   &service=`orders`
 *   &tpl=`ms3_customer_orders`
 *   &orderTpl=`ms3_customer_order_row`
 *   &limit=`20`
 * ]]
 * ```
 *
 * @package MiniShop3\Services\Customer
 */
class OrdersPageService extends CustomerPageService
{
    /**
     * Get raw order page data
     *
     * @return array Order data
     */
    public function getData(): array
    {
        $orderUuid = isset($_GET['order']) ? (string)$_GET['order'] : null;

        if ($orderUuid && !preg_match('/^[0-9a-f\-]{36}$/i', $orderUuid)) {
            $orderUuid = null;
        }

        if ($orderUuid) {
            return $this->getOrderDetailsData($orderUuid);
        }

        return $this->getOrdersListData();
    }

    /**
     * Customer-facing API base URL (for cancel order, etc.)
     *
     * Uses MS3 config so custom ms3_assets_url / ms3_action_url are respected.
     *
     * @return string
     */
    protected function getCustomerApiUrl(): string
    {
        return $this->ms3->config['actionUrl'];
    }

    /**
     * Render order history page
     *
     * @return string HTML content
     */
    public function render(): string
    {
        $orderUuid = isset($_GET['order']) ? (string)$_GET['order'] : null;

        if ($orderUuid && !preg_match('/^[0-9a-f\-]{36}$/i', $orderUuid)) {
            $orderUuid = null;
        }

        if ($orderUuid) {
            return $this->renderOrderDetails($orderUuid);
        }

        return $this->renderOrdersList();
    }

    /**
     * Render order list
     *
     * @return string HTML content
     */
    protected function renderOrdersList(): string
    {
        $tpl = $this->modx->getOption(
            'tpl',
            $this->scriptProperties,
            'tpl.msCustomer.orders'
        );

        $orderTpl = $this->modx->getOption(
            'orderTpl',
            $this->scriptProperties,
            'tpl.msCustomer.order.row'
        );

        $limit = (int)$this->modx->getOption('limit', $this->scriptProperties, 20);
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        $statusFilter = isset($_GET['status']) ? (int)$_GET['status'] : null;

        $where = [
            'customer_id' => $this->customerId,
            'status_id:!=' => 1,
        ];
        if ($statusFilter) {
            $where['status_id'] = $statusFilter;
        }

        // Count total orders
        $total = $this->modx->getCount(msOrder::class, $where);

        [$statusMap, $statusesData] = $this->loadStatusData($statusFilter);

        // Get orders
        $query = $this->modx->newQuery(msOrder::class);
        $query->where($where);
        $query->sortby('msOrder.createdon', 'DESC');
        $query->limit($limit, $offset);

        /** @var msOrder[] $orders */
        $orders = $this->modx->getCollection(msOrder::class, $query);

        $ordersData = [];
        foreach ($orders as $order) {
            $orderData = $order->toArray();

            $orderData['createdon_formatted'] = date('d.m.Y H:i', strtotime($orderData['createdon']));
            $orderData['cost_formatted'] = $this->ms3->format->price($orderData['cost']);
            $orderData['cost_with_currency'] = $this->ms3->format->price($orderData['cost'], true);

            $statusId = (int) $orderData['status_id'];
            $orderData['status_name'] = $statusMap[$statusId]['name'] ?? '';
            $orderData['status_color'] = $statusMap[$statusId]['color'] ?? '';

            $orderData['can_cancel'] = $this->isOrderCancellableByCustomer($order);

            $chunk = $this->pdoFetch->getChunk($orderTpl, $orderData);
            $ordersData[] = is_string($chunk) ? $chunk : '';
        }

        $resourceId = $this->modx->resource->get('id');
        $pageUrl = $this->modx->makeUrl($resourceId, '', '', 'full');

        $pagination = $this->buildPagination($total, $limit, $offset);
        $this->enrichPaginationWithUrls($pagination, $resourceId, $statusFilter);

        $data = [
            'orders' => implode("\n", $ordersData),
            'orders_count' => count($ordersData),
            'total' => $total,
            'statuses' => $statusesData,
            'pagination' => $pagination,
            'customer' => $this->customer->toArray(),
            'api_url' => $this->getCustomerApiUrl(),
            'assets_url' => $this->ms3->config['assetsUrl'],
            'page_url' => $pageUrl,
        ];

        $chunk = $this->pdoFetch->getChunk($tpl, $data);
        return is_string($chunk) ? $chunk : '';
    }

    /**
     * Render detailed order information
     *
     * @param string $orderUuid Order UUID
     * @return string HTML content
     */
    protected function renderOrderDetails(string $orderUuid): string
    {
        /** @var msOrder $order */
        $order = $this->modx->getObject(msOrder::class, [
            'uuid' => $orderUuid,
            'customer_id' => $this->customerId,
        ]);

        if (!$order) {
            return $this->modx->lexicon('ms3_err_order_nf');
        }

        $tpl = $this->modx->getOption(
            'detailTpl',
            $this->scriptProperties,
            'tpl.msCustomer.order.details'
        );

        $orderId = (int) $order->get('id');
        $products = $this->getOrderProducts($orderId);

        $delivery = $order->getOne('Delivery');
        $payment = $order->getOne('Payment');
        $address = $order->getOne('Address');
        $status = $order->getOne('Status');

        $orderArray = array_merge($order->toArray(), [
            'status_name' => $status ? $this->translateStatusName($status->get('name')) : '',
            'status_color' => $status ? $status->get('color') : '',
            'createdon_formatted' => date('d.m.Y H:i', strtotime($order->get('createdon'))),
            'can_cancel' => $this->isOrderCancellableByCustomer($order),
        ]);

        $data = [
            'order' => $orderArray,
            'products' => $products,
            'delivery' => $delivery ? $delivery->toArray() : [],
            'payment' => $payment ? $payment->toArray() : [],
            'address' => $address ? $address->toArray() : [],
            'total' => [
                'cost' => $this->ms3->format->price($order->get('cost')),
                'cost_formatted' => $this->ms3->format->price($order->get('cost'), true),
                'cart_cost' => $this->ms3->format->price($order->get('cart_cost')),
                'cart_cost_formatted' => $this->ms3->format->price($order->get('cart_cost'), true),
                'delivery_cost' => $this->ms3->format->price($order->get('delivery_cost')),
                'delivery_cost_formatted' => $this->ms3->format->price($order->get('delivery_cost'), true),
                'weight' => $this->ms3->format->weight($order->get('weight')),
                'weight_formatted' => $this->ms3->format->weightWithUnit($order->get('weight')),
            ],
            'customer' => $this->customer->toArray(),
            'api_url' => $this->getCustomerApiUrl(),
            'assets_url' => $this->ms3->config['assetsUrl'],
        ];

        $chunk = $this->pdoFetch->getChunk($tpl, $data);
        return is_string($chunk) ? $chunk : '';
    }

    /**
     * Get order products with options
     *
     * @param int $orderId Order ID
     * @return array Array of products
     */
    protected function getOrderProducts(int $orderId): array
    {
        $query = $this->modx->newQuery(msOrderProduct::class);
        $query->where(['order_id' => $orderId]);
        $query->sortby('msOrderProduct.id', 'ASC');

        /** @var msOrderProduct[] $orderProducts */
        $orderProducts = $this->modx->getCollection(msOrderProduct::class, $query);

        // Collect product IDs for batch loading
        $productIds = [];
        foreach ($orderProducts as $op) {
            $productIds[] = (int) $op->get('product_id');
        }
        $productIds = array_unique($productIds);

        // Batch load product info (pagetitle, uri) and product data (article, old_price)
        $productMap = [];
        $dataMap = [];
        if (!empty($productIds)) {
            $resQuery = $this->modx->newQuery(modResource::class);
            $resQuery->where(['id:IN' => $productIds]);
            foreach ($this->modx->getIterator(modResource::class, $resQuery) as $res) {
                $productMap[$res->get('id')] = [
                    'pagetitle' => $res->get('pagetitle'),
                    'uri' => $res->get('uri'),
                ];
            }

            $dataQuery = $this->modx->newQuery(msProductData::class);
            $dataQuery->where(['id:IN' => $productIds]);
            foreach ($this->modx->getIterator(msProductData::class, $dataQuery) as $data) {
                $dataMap[$data->get('id')] = [
                    'article' => $data->get('article'),
                    'original_price' => (float) $data->get('price'),
                    'old_price' => (float) $data->get('old_price'),
                ];
            }
        }

        $products = [];
        foreach ($orderProducts as $orderProduct) {
            $productData = $orderProduct->toArray();
            $pid = (int) $productData['product_id'];

            $productData['pagetitle'] = $productMap[$pid]['pagetitle'] ?? $productData['name'];
            $productData['uri'] = $productMap[$pid]['uri'] ?? '';
            $productData['article'] = $dataMap[$pid]['article'] ?? '';

            $originalPrice = $dataMap[$pid]['original_price'] ?? 0;
            $catalogOldPrice = $dataMap[$pid]['old_price'] ?? 0;

            $old_price = $originalPrice > $productData['price']
                ? $originalPrice
                : $catalogOldPrice;

            $discount_price = $old_price > 0 ? $old_price - $productData['price'] : 0;

            $rawPrice = (float) $productData['price'];
            $rawCost = (float) $productData['cost'];
            $rawWeight = (float) $productData['weight'];

            $productData['old_price'] = $this->ms3->format->price($old_price);
            $productData['price'] = $this->ms3->format->price($rawPrice);
            $productData['cost'] = $this->ms3->format->price($rawCost);
            $productData['weight'] = $this->ms3->format->weight($rawWeight);
            $productData['discount_price'] = $this->ms3->format->price($discount_price);
            $productData['discount_cost'] = $this->ms3->format->price($productData['count'] * $discount_price);

            // Pre-formatted fields with currency/unit for display in chunks
            $productData['price_formatted'] = $this->ms3->format->price($rawPrice, true);
            $productData['cost_formatted'] = $this->ms3->format->price($rawCost, true);
            $productData['weight_formatted'] = $this->ms3->format->weightWithUnit($rawWeight);

            if (!empty($productData['options']) && is_array($productData['options'])) {
                foreach ($productData['options'] as $option => $value) {
                    $productData['option.' . $option] = $value;
                }
            }

            $products[] = $productData;
        }

        return $products;
    }

    /**
     * Get order list data (without rendering)
     *
     * @return array Order list data
     */
    protected function getOrdersListData(): array
    {
        $limit = (int)$this->modx->getOption('limit', $this->scriptProperties, 20);
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        $statusFilter = isset($_GET['status']) ? (int)$_GET['status'] : null;

        $where = [
            'customer_id' => $this->customerId,
            'status_id:!=' => 1,
        ];
        if ($statusFilter) {
            $where['status_id'] = $statusFilter;
        }

        // Count total orders
        $total = $this->modx->getCount(msOrder::class, $where);

        [$statusMap, $statusesData] = $this->loadStatusData($statusFilter);

        // Get orders
        $query = $this->modx->newQuery(msOrder::class);
        $query->where($where);
        $query->sortby('msOrder.createdon', 'DESC');
        $query->limit($limit, $offset);

        /** @var msOrder[] $orders */
        $orders = $this->modx->getCollection(msOrder::class, $query);

        $ordersData = [];
        foreach ($orders as $order) {
            $orderData = $order->toArray();

            $orderData['createdon_formatted'] = date('d.m.Y H:i', strtotime($orderData['createdon']));
            $orderData['cost_formatted'] = $this->ms3->format->price($orderData['cost']);
            $orderData['cost_with_currency'] = $this->ms3->format->price($orderData['cost'], true);

            $statusId = (int) $orderData['status_id'];
            $orderData['status_name'] = $statusMap[$statusId]['name'] ?? '';
            $orderData['status_color'] = $statusMap[$statusId]['color'] ?? '';

            $orderData['can_cancel'] = $this->isOrderCancellableByCustomer($order);

            $ordersData[] = $orderData;
        }

        $resourceId = $this->modx->resource->get('id');
        $pageUrl = $this->modx->makeUrl($resourceId, '', '', 'full');

        $pagination = $this->buildPagination($total, $limit, $offset);
        $this->enrichPaginationWithUrls($pagination, $resourceId, $statusFilter);

        return [
            'orders' => $ordersData,
            'orders_count' => count($ordersData),
            'total' => $total,
            'statuses' => $statusesData,
            'pagination' => $pagination,
            'customer' => $this->customer->toArray(),
            'page_url' => $pageUrl,
        ];
    }

    /**
     * Get order details data (without rendering)
     *
     * @param string $orderUuid Order UUID
     * @return array Order data
     */
    protected function getOrderDetailsData(string $orderUuid): array
    {
        /** @var msOrder $order */
        $order = $this->modx->getObject(msOrder::class, [
            'uuid' => $orderUuid,
            'customer_id' => $this->customerId,
        ]);

        if (!$order) {
            return [
                'error' => $this->modx->lexicon('ms3_err_order_nf'),
                'customer' => $this->customer->toArray(),
            ];
        }

        $orderId = (int) $order->get('id');
        $products = $this->getOrderProducts($orderId);

        $delivery = $order->getOne('Delivery');
        $payment = $order->getOne('Payment');
        $address = $order->getOne('Address');
        $status = $order->getOne('Status');

        return [
            'order' => array_merge($order->toArray(), [
                'status_name' => $status ? $this->translateStatusName($status->get('name')) : '',
                'status_color' => $status ? $status->get('color') : '',
                'createdon_formatted' => date('d.m.Y H:i', strtotime($order->get('createdon'))),
                'can_cancel' => $this->isOrderCancellableByCustomer($order),
            ]),
            'products' => $products,
            'delivery' => $delivery ? $delivery->toArray() : [],
            'payment' => $payment ? $payment->toArray() : [],
            'address' => $address ? $address->toArray() : [],
            'total' => [
                'cost' => $this->ms3->format->price($order->get('cost')),
                'cost_formatted' => $this->ms3->format->price($order->get('cost'), true),
                'cart_cost' => $this->ms3->format->price($order->get('cart_cost')),
                'cart_cost_formatted' => $this->ms3->format->price($order->get('cart_cost'), true),
                'delivery_cost' => $this->ms3->format->price($order->get('delivery_cost')),
                'delivery_cost_formatted' => $this->ms3->format->price($order->get('delivery_cost'), true),
                'weight' => $this->ms3->format->weight($order->get('weight')),
                'weight_formatted' => $this->ms3->format->weightWithUnit($order->get('weight')),
            ],
            'customer' => $this->customer->toArray(),
            'api_url' => $this->getCustomerApiUrl(),
            'assets_url' => $this->ms3->config['assetsUrl'],
        ];
    }

    /**
     * Add absolute URLs to pagination entries
     *
     * Builds URLs via makeUrl() so that:
     * - active status filter is preserved across pages
     * - URLs are valid regardless of friendly_urls setting
     *
     * @param array $pagination Pagination data from buildPagination()
     * @param int $resourceId Current resource ID
     * @param int|null $statusFilter Active status filter ID
     */
    private function enrichPaginationWithUrls(array &$pagination, int $resourceId, ?int $statusFilter): void
    {
        $filterParams = $statusFilter ? ['status' => $statusFilter] : [];

        foreach ($pagination['pages'] as &$page) {
            $params = $page['offset'] > 0 ? array_merge($filterParams, ['offset' => $page['offset']]) : $filterParams;
            $page['url'] = $this->modx->makeUrl($resourceId, '', http_build_query($params), 'full');
        }
        unset($page);

        if ($pagination['has_prev']) {
            $params = $pagination['prev_offset'] > 0
                ? array_merge($filterParams, ['offset' => $pagination['prev_offset']])
                : $filterParams;
            $pagination['prev_url'] = $this->modx->makeUrl($resourceId, '', http_build_query($params), 'full');
        }

        if ($pagination['has_next']) {
            $pagination['next_url'] = $this->modx->makeUrl(
                $resourceId,
                '',
                http_build_query(array_merge($filterParams, ['offset' => $pagination['next_offset']])),
                'full'
            );
        }
    }

    /**
     * Build pagination
     *
     * @param int $total Total count
     * @param int $limit Limit per page
     * @param int $offset Current offset
     * @return array Pagination data
     */
    protected function buildPagination(int $total, int $limit, int $offset): array
    {
        $totalPages = ceil($total / $limit);
        $currentPage = floor($offset / $limit) + 1;

        $pages = [];
        for ($i = 1; $i <= $totalPages; $i++) {
            $pages[] = [
                'num' => $i,
                'offset' => ($i - 1) * $limit,
                'active' => $i == $currentPage,
            ];
        }

        return [
            'total' => $total,
            'total_pages' => $totalPages,
            'current_page' => $currentPage,
            'limit' => $limit,
            'offset' => $offset,
            'pages' => $pages,
            'has_prev' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages,
            'prev_offset' => max(0, $offset - $limit),
            'next_offset' => min(($totalPages - 1) * $limit, $offset + $limit),
        ];
    }

    /**
     * Whether the customer is allowed to cancel this order (by status)
     *
     * @param msOrder $order
     * @return bool
     */
    protected function isOrderCancellableByCustomer(msOrder $order): bool
    {
        $orderStatusService = $this->modx->services->get('ms3_order_status');
        $allowedIds = $orderStatusService->getAllowedCancelStatusIds();
        return in_array((int) $order->get('status_id'), $allowedIds, true);
    }

    /**
     * Pre-load all order statuses into a map and filter dropdown data
     *
     * @param int|null $statusFilter Active status filter ID
     * @return array{0: array, 1: array} [statusMap, statusesData]
     */
    private function loadStatusData(?int $statusFilter): array
    {
        $statusQuery = $this->modx->newQuery(msOrderStatus::class);
        $statusQuery->sortby('position', 'ASC');
        $allStatuses = $this->modx->getIterator(msOrderStatus::class, $statusQuery);

        $statusMap = [];
        $statusesData = [];
        /** @var msOrderStatus $status */
        foreach ($allStatuses as $status) {
            $sid = $status->get('id');
            $statusMap[$sid] = [
                'name' => $this->translateStatusName($status->get('name')),
                'color' => $status->get('color'),
            ];
            if ($sid != 1) {
                $statusesData[] = [
                    'id' => $sid,
                    'name' => $statusMap[$sid]['name'],
                    'color' => $status->get('color'),
                    'selected' => $sid == $statusFilter,
                ];
            }
        }

        return [$statusMap, $statusesData];
    }

    /**
     * Translate status name
     *
     * If name is lexicon key (ms3_order_status_*), returns translation.
     * If it's plain text (custom status), returns as is.
     *
     * @param string $name Status name or lexicon key
     * @return string Translated name
     */
    protected function translateStatusName(string $name): string
    {
        if (str_starts_with($name, 'ms3_order_status_')) {
            $this->modx->lexicon->load('minishop3:manager');

            $translated = $this->modx->lexicon($name);

            if ($translated !== $name) {
                return $translated;
            }
        }

        return $name;
    }
}
