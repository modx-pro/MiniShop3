<?php

namespace MiniShop3\Services\Customer;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductOption;

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
        $orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

        if ($orderId) {
            return $this->getOrderDetailsData($orderId);
        }

        return $this->getOrdersListData();
    }

    /**
     * Render order history page
     *
     * @return string HTML content
     */
    public function render(): string
    {
        $orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

        if ($orderId) {
            return $this->renderOrderDetails($orderId);
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

        // Get orders with JOIN to status
        $query = $this->modx->newQuery(msOrder::class);
        $query->where($where);
        $query->leftJoin(msOrderStatus::class, 'Status', 'Status.id = msOrder.status_id');
        $query->select($this->modx->getSelectColumns(msOrder::class, 'msOrder'));
        $query->select([
            'Status.name as status_name',
            'Status.color as status_color',
        ]);
        $query->sortby('msOrder.createdon', 'DESC');
        $query->limit($limit, $offset);

        /** @var msOrder[] $orders */
        $orders = $this->modx->getCollection(msOrder::class, $query);

        $ordersData = [];
        foreach ($orders as $order) {
            $orderData = $order->toArray();

            $orderData['createdon_formatted'] = date('d.m.Y H:i', strtotime($orderData['createdon']));

            $orderData['cost_formatted'] = $this->ms3->format->price($orderData['cost']);

            if (!empty($orderData['status_name'])) {
                $orderData['status_name'] = $this->translateStatusName($orderData['status_name']);
            }

            $chunk = $this->pdoFetch->getChunk($orderTpl, $orderData);
            $ordersData[] = is_string($chunk) ? $chunk : '';
        }

        $statusQuery = $this->modx->newQuery(msOrderStatus::class);
        $statusQuery->where(['id:!=' => 1]);
        $statusQuery->sortby('position', 'ASC');
        $statuses = $this->modx->getIterator(msOrderStatus::class, $statusQuery);
        $statusesData = [];
        /** @var msOrderStatus $status */
        foreach ($statuses as $status) {
            $statusesData[] = [
                'id' => $status->get('id'),
                'name' => $this->translateStatusName($status->get('name')),
                'color' => $status->get('color'),
                'selected' => $status->get('id') == $statusFilter,
            ];
        }

        $pagination = $this->buildPagination($total, $limit, $offset);

        $data = [
            'orders' => implode("\n", $ordersData),
            'orders_count' => count($ordersData),
            'total' => $total,
            'statuses' => $statusesData,
            'pagination' => $pagination,
            'customer' => $this->customer->toArray(),
        ];

        $chunk = $this->pdoFetch->getChunk($tpl, $data);
        return is_string($chunk) ? $chunk : '';
    }

    /**
     * Render detailed order information
     *
     * @param int $orderId Order ID
     * @return string HTML content
     */
    protected function renderOrderDetails(int $orderId): string
    {
        /** @var msOrder $order */
        $order = $this->modx->getObject(msOrder::class, [
            'id' => $orderId,
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

        $products = $this->getOrderProducts($orderId);

        $delivery = $order->getOne('Delivery');
        $payment = $order->getOne('Payment');
        $address = $order->getOne('Address');
        $status = $order->getOne('Status');

        $data = [
            'order' => array_merge($order->toArray(), [
                'status_name' => $status ? $this->translateStatusName($status->get('name')) : '',
                'status_color' => $status ? $status->get('color') : '',
                'createdon_formatted' => date('d.m.Y H:i', strtotime($order->get('createdon'))),
            ]),
            'products' => $products,
            'delivery' => $delivery ? $delivery->toArray() : [],
            'payment' => $payment ? $payment->toArray() : [],
            'address' => $address ? $address->toArray() : [],
            'total' => [
                'cost' => $this->ms3->format->price($order->get('cost')),
                'cart_cost' => $this->ms3->format->price($order->get('cart_cost')),
                'delivery_cost' => $this->ms3->format->price($order->get('delivery_cost')),
                'weight' => $this->ms3->format->weight($order->get('weight')),
            ],
            'customer' => $this->customer->toArray(),
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
        $query->leftJoin(msProduct::class, 'Product', 'Product.id = msOrderProduct.product_id');
        $query->leftJoin(msProductData::class, 'Data', 'Data.id = msOrderProduct.product_id');
        $query->select($this->modx->getSelectColumns(msOrderProduct::class, 'msOrderProduct'));
        $query->select([
            'Product.pagetitle',
            'Product.uri',
            'Data.article',
            'Data.price as original_price',
        ]);
        $query->sortby('msOrderProduct.id', 'ASC');

        /** @var msOrderProduct[] $orderProducts */
        $orderProducts = $this->modx->getCollection(msOrderProduct::class, $query);

        $products = [];
        foreach ($orderProducts as $orderProduct) {
            $productData = $orderProduct->toArray();

            $old_price = $productData['original_price'] > $productData['price']
                ? $productData['original_price']
                : $productData['old_price'];

            $discount_price = $old_price > 0 ? $old_price - $productData['price'] : 0;

            $productData['old_price'] = $this->ms3->format->price($old_price);
            $productData['price'] = $this->ms3->format->price($productData['price']);
            $productData['cost'] = $this->ms3->format->price($productData['cost']);
            $productData['weight'] = $this->ms3->format->weight($productData['weight']);
            $productData['discount_price'] = $this->ms3->format->price($discount_price);
            $productData['discount_cost'] = $this->ms3->format->price($productData['count'] * $discount_price);

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

        // Get orders with JOIN to status
        $query = $this->modx->newQuery(msOrder::class);
        $query->where($where);
        $query->leftJoin(msOrderStatus::class, 'Status', 'Status.id = msOrder.status_id');
        $query->select($this->modx->getSelectColumns(msOrder::class, 'msOrder'));
        $query->select([
            'Status.name as status_name',
            'Status.color as status_color',
        ]);
        $query->sortby('msOrder.createdon', 'DESC');
        $query->limit($limit, $offset);

        /** @var msOrder[] $orders */
        $orders = $this->modx->getCollection(msOrder::class, $query);

        $ordersData = [];
        foreach ($orders as $order) {
            $orderData = $order->toArray();

            $orderData['createdon_formatted'] = date('d.m.Y H:i', strtotime($orderData['createdon']));

            $orderData['cost_formatted'] = $this->ms3->format->price($orderData['cost']);

            if (!empty($orderData['status_name'])) {
                $orderData['status_name'] = $this->translateStatusName($orderData['status_name']);
            }

            $ordersData[] = $orderData;
        }

        $statusQuery = $this->modx->newQuery(msOrderStatus::class);
        $statusQuery->where(['id:!=' => 1]);
        $statusQuery->sortby('position', 'ASC');
        $statuses = $this->modx->getIterator(msOrderStatus::class, $statusQuery);
        $statusesData = [];
        /** @var msOrderStatus $status */
        foreach ($statuses as $status) {
            $statusesData[] = [
                'id' => $status->get('id'),
                'name' => $this->translateStatusName($status->get('name')),
                'color' => $status->get('color'),
                'selected' => $status->get('id') == $statusFilter,
            ];
        }

        $pagination = $this->buildPagination($total, $limit, $offset);

        return [
            'orders' => $ordersData,
            'orders_count' => count($ordersData),
            'total' => $total,
            'statuses' => $statusesData,
            'pagination' => $pagination,
            'customer' => $this->customer->toArray(),
        ];
    }

    /**
     * Get order details data (without rendering)
     *
     * @param int $orderId Order ID
     * @return array Order data
     */
    protected function getOrderDetailsData(int $orderId): array
    {
        /** @var msOrder $order */
        $order = $this->modx->getObject(msOrder::class, [
            'id' => $orderId,
            'customer_id' => $this->customerId,
        ]);

        if (!$order) {
            return [
                'error' => $this->modx->lexicon('ms3_err_order_nf'),
                'customer' => $this->customer->toArray(),
            ];
        }

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
            ]),
            'products' => $products,
            'delivery' => $delivery ? $delivery->toArray() : [],
            'payment' => $payment ? $payment->toArray() : [],
            'address' => $address ? $address->toArray() : [],
            'total' => [
                'cost' => $this->ms3->format->price($order->get('cost')),
                'cart_cost' => $this->ms3->format->price($order->get('cart_cost')),
                'delivery_cost' => $this->ms3->format->price($order->get('delivery_cost')),
                'weight' => $this->ms3->format->weight($order->get('weight')),
            ],
            'customer' => $this->customer->toArray(),
        ];
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
