<?php

namespace MiniShop3\Services\Customer;

use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductOption;

/**
 * OrdersPageService - сервис страницы истории заказов клиента
 *
 * Отображает полную информацию о заказах клиента:
 * - Список всех заказов (с пагинацией)
 * - Детальная информация о конкретном заказе
 * - Фильтрация по статусу
 *
 * Пример использования в сниппете:
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
     * Получить сырые данные страницы заказов
     *
     * @return array Данные заказов
     */
    public function getData(): array
    {
        // Получить ID конкретного заказа для детального просмотра
        $orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

        if ($orderId) {
            return $this->getOrderDetailsData($orderId);
        }

        return $this->getOrdersListData();
    }

    /**
     * Рендерить страницу истории заказов
     *
     * @return string HTML содержимое
     */
    public function render(): string
    {
        // Получить ID конкретного заказа для детального просмотра
        $orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

        if ($orderId) {
            return $this->renderOrderDetails($orderId);
        }

        return $this->renderOrdersList();
    }

    /**
     * Рендерить список заказов
     *
     * @return string HTML содержимое
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

        // Параметры пагинации
        $limit = (int)$this->modx->getOption('limit', $this->scriptProperties, 20);
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        // Фильтр по статусу
        $statusFilter = isset($_GET['status']) ? (int)$_GET['status'] : null;

        // Условия выборки
        $where = ['customer_id' => $this->customerId];
        if ($statusFilter) {
            $where['status_id'] = $statusFilter;
        }

        // Подсчет общего количества
        $total = $this->modx->getCount(msOrder::class, $where);

        // Получить заказы с JOIN к статусу
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

            // Форматирование даты
            $orderData['createdon_formatted'] = date('d.m.Y H:i', strtotime($orderData['createdon']));

            // Форматирование стоимости
            $orderData['cost_formatted'] = $this->ms3->format->price($orderData['cost']);

            // Рендеринг строки заказа
            $chunk = $this->pdoFetch->getChunk($orderTpl, $orderData);
            $ordersData[] = is_string($chunk) ? $chunk : '';
        }

        // Получить список статусов для фильтра
        $statuses = $this->modx->getIterator(msOrderStatus::class, [], ['sortby' => 'rank']);
        $statusesData = [];
        /** @var msOrderStatus $status */
        foreach ($statuses as $status) {
            $statusesData[] = [
                'id' => $status->get('id'),
                'name' => $status->get('name'),
                'color' => $status->get('color'),
                'selected' => $status->get('id') == $statusFilter,
            ];
        }

        // Пагинация
        $pagination = $this->buildPagination($total, $limit, $offset);

        // Подготовить данные для шаблона
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
     * Рендерить детальную информацию о заказе
     *
     * @param int $orderId ID заказа
     * @return string HTML содержимое
     */
    protected function renderOrderDetails(int $orderId): string
    {
        // Загрузить заказ и проверить владельца
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

        // Получить товары заказа
        $products = $this->getOrderProducts($orderId);

        // Получить связанные данные
        $delivery = $order->getOne('Delivery');
        $payment = $order->getOne('Payment');
        $address = $order->getOne('Address');
        $status = $order->getOne('Status');

        // Подготовить данные для шаблона (как в ms3_get_order.php)
        $data = [
            'order' => array_merge($order->toArray(), [
                'status_name' => $status ? $status->get('name') : '',
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
     * Получить товары заказа с опциями
     *
     * @param int $orderId ID заказа
     * @return array Массив товаров
     */
    protected function getOrderProducts(int $orderId): array
    {
        // Запрос товаров с JOIN к msProduct
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

            // Вычислить старую цену
            $old_price = $productData['original_price'] > $productData['price']
                ? $productData['original_price']
                : $productData['old_price'];

            $discount_price = $old_price > 0 ? $old_price - $productData['price'] : 0;

            // Форматирование цен
            $productData['old_price'] = $this->ms3->format->price($old_price);
            $productData['price'] = $this->ms3->format->price($productData['price']);
            $productData['cost'] = $this->ms3->format->price($productData['cost']);
            $productData['weight'] = $this->ms3->format->weight($productData['weight']);
            $productData['discount_price'] = $this->ms3->format->price($discount_price);
            $productData['discount_cost'] = $this->ms3->format->price($productData['count'] * $discount_price);

            // Добавить опции товара
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
     * Получить данные списка заказов (без рендеринга)
     *
     * @return array Данные списка заказов
     */
    protected function getOrdersListData(): array
    {
        // Параметры пагинации
        $limit = (int)$this->modx->getOption('limit', $this->scriptProperties, 20);
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        // Фильтр по статусу
        $statusFilter = isset($_GET['status']) ? (int)$_GET['status'] : null;

        // Условия выборки
        $where = ['customer_id' => $this->customerId];
        if ($statusFilter) {
            $where['status_id'] = $statusFilter;
        }

        // Подсчет общего количества
        $total = $this->modx->getCount(msOrder::class, $where);

        // Получить заказы с JOIN к статусу
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

            // Форматирование даты
            $orderData['createdon_formatted'] = date('d.m.Y H:i', strtotime($orderData['createdon']));

            // Форматирование стоимости
            $orderData['cost_formatted'] = $this->ms3->format->price($orderData['cost']);

            $ordersData[] = $orderData;
        }

        // Получить список статусов для фильтра
        $statuses = $this->modx->getIterator(msOrderStatus::class, [], ['sortby' => 'rank']);
        $statusesData = [];
        /** @var msOrderStatus $status */
        foreach ($statuses as $status) {
            $statusesData[] = [
                'id' => $status->get('id'),
                'name' => $status->get('name'),
                'color' => $status->get('color'),
                'selected' => $status->get('id') == $statusFilter,
            ];
        }

        // Пагинация
        $pagination = $this->buildPagination($total, $limit, $offset);

        // Подготовить данные
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
     * Получить данные детального просмотра заказа (без рендеринга)
     *
     * @param int $orderId ID заказа
     * @return array Данные заказа
     */
    protected function getOrderDetailsData(int $orderId): array
    {
        // Загрузить заказ и проверить владельца
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

        // Получить товары заказа
        $products = $this->getOrderProducts($orderId);

        // Получить связанные данные
        $delivery = $order->getOne('Delivery');
        $payment = $order->getOne('Payment');
        $address = $order->getOne('Address');
        $status = $order->getOne('Status');

        // Подготовить данные
        return [
            'order' => array_merge($order->toArray(), [
                'status_name' => $status ? $status->get('name') : '',
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
     * Построить пагинацию
     *
     * @param int $total Общее количество
     * @param int $limit Лимит на страницу
     * @param int $offset Текущий офсет
     * @return array Данные пагинации
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
}
