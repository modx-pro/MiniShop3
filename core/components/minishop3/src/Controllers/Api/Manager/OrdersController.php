<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Services\Order\OrderLogService;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msExtraField;
use MiniShop3\Model\msModelField;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msOrderLog;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Model\msPayment;
use MiniShop3\Router\Response;
use MiniShop3\Services\CustomerDuplicateChecker;
use MiniShop3\Services\CustomerFactory;
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
    protected ?OrderLogService $orderLog = null;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;

        // Ensure extra fields are loaded into xPDO map
        $this->loadExtraFieldsMap();
    }

    /**
     * Get OrderLogService (lazy loading from DI)
     *
     * @return OrderLogService
     */
    protected function getOrderLog(): OrderLogService
    {
        if ($this->orderLog === null) {
            if ($this->modx->services->has('ms3_order_log')) {
                $this->orderLog = $this->modx->services->get('ms3_order_log');
            } else {
                /** @var MiniShop3 $ms3 */
                $ms3 = $this->modx->services->get('ms3');
                $this->orderLog = new OrderLogService($this->modx, $ms3);
            }
        }
        return $this->orderLog;
    }

    /**
     * Load extra fields into xPDO map
     * This ensures dynamic columns added via Object Extension are available
     */
    protected function loadExtraFieldsMap(): void
    {
        $ms3 = $this->modx->services->get('ms3');
        if ($ms3) {
            $ms3->loadMap();
        }
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
        // Get ALL fields including hidden (for relation JOINs)
        $gridFields = $gridConfig ? $gridConfig->getGridConfig('orders', true) : [];

        $c = $this->modx->newQuery(msOrder::class);

        // Address JOIN is always needed for search and customer info
        $c->leftJoin(msOrderAddress::class, 'Address', '`Address`.order_id = msOrder.id');

        // Dynamic JOINs from relation fields in grid config
        $relationGroups = $gridConfig ? $gridConfig->extractRelationFields($gridFields) : [];
        foreach ($relationGroups as $group) {
            if (!empty($group['modelClass'])) {
                $c->leftJoin($group['modelClass'], $group['alias'], "`{$group['alias']}`.id = msOrder.{$group['foreignKey']}");
            }
        }

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
            $c->where([
                'msOrder.createdon:>=' => date('Y-m-d 00:00:00', strtotime($dateFrom)),
            ]);
        }
        if ($dateTo = ($params['createdon_to'] ?? $params['date_end'] ?? null)) {
            $c->where([
                'msOrder.createdon:<=' => date('Y-m-d 23:59:59', strtotime($dateTo)),
            ]);
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

        // Build SELECT: base model fields + address fields + dynamic relation fields
        $selectParts = [
            $this->modx->getSelectColumns(msOrder::class, 'msOrder'),
            '`Address`.first_name', '`Address`.last_name', '`Address`.phone', '`Address`.email',
        ];

        // Add SELECT for relation fields
        foreach ($relationGroups as $group) {
            foreach ($group['fields'] as $fieldDef) {
                $selectParts[] = "`{$group['alias']}`.{$fieldDef['displayField']} as `{$fieldDef['name']}`";
            }
        }

        $c->select(implode(', ', $selectParts));
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

        // Load extra fields for msOrder (stored as real DB columns)
        $orderExtraFields = $this->getExtraFieldKeys('MiniShop3\\Model\\msOrder');
        foreach ($orderExtraFields as $fieldKey) {
            $data[$fieldKey] = $order->get($fieldKey);
        }

        // Load all address fields dynamically
        if ($address) {
            $addressData = $address->toArray();
            // Exclude system fields that should not be exposed
            $excludeFields = ['id', 'order_id', 'createdon', 'updatedon'];
            foreach ($addressData as $key => $value) {
                if (!in_array($key, $excludeFields)) {
                    $data[$key] = $value;
                }
            }

            // Load extra fields for msOrderAddress (stored as real DB columns)
            $addressExtraFields = $this->getExtraFieldKeys('MiniShop3\\Model\\msOrderAddress');
            foreach ($addressExtraFields as $fieldKey) {
                $data[$fieldKey] = $address->get($fieldKey);
            }
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
     * Bulk delete orders
     * DELETE /api/mgr/orders/bulk
     *
     * @param array $data Request data (ids)
     * @return array Response
     */
    public function bulkDelete(array $data = []): array
    {
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return Response::error('Order IDs array is required', 400)->getData();
        }

        // Sanitize IDs
        $ids = array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            return Response::error('No valid order IDs provided', 400)->getData();
        }

        $deleted = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $order = $this->modx->getObject(msOrder::class, $id);

            if (!$order) {
                $failed++;
                continue;
            }

            // Delete related addresses
            $addresses = $this->modx->getIterator(msOrderAddress::class, ['order_id' => $id]);
            foreach ($addresses as $address) {
                $address->remove();
            }

            // Delete related products
            $products = $this->modx->getIterator(\MiniShop3\Model\msOrderProduct::class, ['order_id' => $id]);
            foreach ($products as $product) {
                $product->remove();
            }

            // Delete related logs
            $logs = $this->modx->getIterator(\MiniShop3\Model\msOrderLog::class, ['order_id' => $id]);
            foreach ($logs as $log) {
                $log->remove();
            }

            if ($order->remove()) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        if ($deleted === 0) {
            return Response::error('Failed to delete orders', 500)->getData();
        }

        return Response::success([
            'deleted' => $deleted,
            'failed' => $failed
        ], "Deleted {$deleted} orders")->getData();
    }

    /**
     * Create new order
     * POST /api/mgr/orders
     *
     * @param array $params Body parameters
     *   - create_customer: bool - Create customer from order data
     *   - force_create_customer: bool - Create customer even if duplicate found
     *   - customer_id: int - Existing customer ID (if not creating new)
     *   - first_name, last_name, email, phone: Customer/address data
     *   - delivery_id, payment_id: Optional delivery and payment
     * @return array Response
     */
    public function create(array $params = []): array
    {
        $createCustomer = !empty($params['create_customer']);
        $forceCreateCustomer = !empty($params['force_create_customer']);
        $customerId = (int) ($params['customer_id'] ?? 0);

        // Handle customer creation
        if ($createCustomer && $customerId === 0) {
            /** @var CustomerDuplicateChecker $duplicateChecker */
            $duplicateChecker = $this->modx->services->get('ms3_customer_duplicate_checker');

            // Check for duplicates (unless forcing creation)
            if (!$forceCreateCustomer && $duplicateChecker->hasCheckableData($params)) {
                $existingCustomer = $duplicateChecker->findDuplicate($params);

                if ($existingCustomer) {
                    // Return duplicate info for user decision
                    return Response::success([
                        'duplicate_found' => true,
                        'customer' => [
                            'id' => $existingCustomer->get('id'),
                            'first_name' => $existingCustomer->get('first_name'),
                            'last_name' => $existingCustomer->get('last_name'),
                            'email' => $existingCustomer->get('email'),
                            'phone' => $existingCustomer->get('phone'),
                            'orders_count' => $existingCustomer->get('orders_count'),
                            'total_spent' => $existingCustomer->get('total_spent'),
                        ],
                    ], 'Customer with matching data already exists')->getData();
                }
            }

            // Create new customer
            try {
                /** @var CustomerFactory $customerFactory */
                $customerFactory = $this->modx->services->get('ms3_customer_factory');
                $customer = $customerFactory->createFromOrderData($params);
                $customerId = $customer->get('id');

                $this->modx->log(
                    modX::LOG_LEVEL_INFO,
                    "[OrdersController] Created new customer #{$customerId} from order data"
                );
            } catch (\Exception $e) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[OrdersController] Failed to create customer: " . $e->getMessage()
                );
                return Response::error('Failed to create customer: ' . $e->getMessage(), 500)->getData();
            }
        }

        // Create new order
        $order = $this->modx->newObject(msOrder::class);

        // Generate UUID and token
        $order->set('uuid', (string) \Ramsey\Uuid\Uuid::uuid4());
        $order->set('token', md5(uniqid('ms3_mgr_', true)));

        // Set status to "New" (not draft)
        $statusNew = (int) $this->modx->getOption('ms3_status_new', null, 2);
        $order->set('status_id', $statusNew);

        // Context
        $order->set('context', $params['context'] ?? 'web');

        // Timestamps
        $order->set('createdon', time());
        $order->set('updatedon', time());

        // User who creates the order (manager)
        $order->set('user_id', $this->modx->user->get('id'));

        // Customer ID (from existing, selected, or newly created)
        $order->set('customer_id', $customerId);
        $order->set('delivery_id', (int) ($params['delivery_id'] ?? 0));
        $order->set('payment_id', (int) ($params['payment_id'] ?? 0));
        $order->set('order_comment', $params['order_comment'] ?? '');

        // Generate order number
        $order->set('num', $this->generateOrderNum());

        // Initial costs (will be recalculated after adding products)
        $order->set('cart_cost', 0);
        $order->set('delivery_cost', (float) ($params['delivery_cost'] ?? 0));
        $order->set('cost', (float) ($params['delivery_cost'] ?? 0));
        $order->set('weight', 0);

        if (!$order->save()) {
            return Response::error('Failed to create order', 500)->getData();
        }

        // Create empty address
        $address = $this->modx->newObject(msOrderAddress::class);
        $address->set('order_id', $order->get('id'));
        $address->set('createdon', time());

        // Address fields from params
        $addressFields = [
            'first_name', 'last_name', 'phone', 'email',
            'country', 'index', 'region', 'city', 'metro',
            'street', 'building', 'entrance', 'floor', 'room',
            'comment', 'text_address'
        ];
        foreach ($addressFields as $field) {
            if (isset($params[$field])) {
                $address->set($field, $params[$field]);
            }
        }
        $address->save();

        // Log order creation
        $this->getOrderLog()->addEntry(
            $order->get('id'),
            msOrderLog::ACTION_STATUS,
            [
                'old_status_id' => 0,
                'new_status_id' => $statusNew,
                'old_status_name' => '',
                'new_status_name' => $this->getStatusName($statusNew),
            ]
        );

        // Return created order with address data
        $orderData = $order->toArray();
        $orderData = array_merge($orderData, $address->toArray());
        $orderData['customer_created'] = $createCustomer && $customerId > 0;

        return Response::success($this->formatOrder($orderData), 'Order created successfully')->getData();
    }

    /**
     * Generate new order number
     *
     * @return string Order number like "2512/1"
     */
    protected function generateOrderNum(): string
    {
        $format = htmlspecialchars($this->modx->getOption('ms3_order_format_num', null, 'ym'));
        $separator = trim(
            preg_replace(
                "/[^,\/\-]/",
                '',
                $this->modx->getOption('ms3_order_format_num_separator', null, '/')
            )
        );
        $separator = $separator ?: '/';

        $cur = $format ? date($format) : date('ym');

        $count = 0;

        $c = $this->modx->newQuery(msOrder::class);
        $c->where(['num:LIKE' => "{$cur}%"]);
        $c->select('num');
        $c->sortby('id', 'DESC');
        $c->limit(1);
        if ($c->prepare() && $c->stmt->execute()) {
            $num = $c->stmt->fetchColumn();
            if ($num && strpos($num, $separator) !== false) {
                [, $count] = explode($separator, $num);
            }
        }
        $count = intval($count) + 1;

        return sprintf('%s%s%d', $cur, $separator, $count);
    }

    /**
     * Get status name by ID
     *
     * @param int $statusId Status ID
     * @return string Status name
     */
    protected function getStatusName(int $statusId): string
    {
        $status = $this->modx->getObject(msOrderStatus::class, $statusId);
        if (!$status) {
            return (string) $statusId;
        }

        $name = $status->get('name');
        if (str_starts_with($name, 'ms3_order_status_')) {
            $translated = $this->modx->lexicon($name);
            if ($translated !== $name) {
                return $translated;
            }
        }

        return $name;
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

        // Store old values for logging
        $oldStatusId = $order->get('status_id');
        $oldOrderValues = $order->toArray();

        // Get editable order fields from msModelField configuration
        $orderFields = $this->getModelFieldNames('msOrder');
        $changedOrderFields = [];

        foreach ($orderFields as $field) {
            if (array_key_exists($field, $params)) {
                $oldValue = $order->get($field);
                $newValue = $params[$field];
                if ($oldValue != $newValue) {
                    $changedOrderFields[$field] = ['old' => $oldValue, 'new' => $newValue];
                }
                $order->set($field, $newValue);
            }
        }

        // Handle extra fields for msOrder (stored as real DB columns via Object Extension)
        $orderExtraFields = $this->getExtraFieldKeys('MiniShop3\\Model\\msOrder');
        foreach ($orderExtraFields as $extraField) {
            if (array_key_exists($extraField, $params)) {
                $oldValue = $order->get($extraField);
                $newValue = $params[$extraField];
                if ($oldValue != $newValue) {
                    $changedOrderFields[$extraField] = ['old' => $oldValue, 'new' => $newValue];
                }
                $order->set($extraField, $newValue);
            }
        }

        $order->set('updatedon', date('Y-m-d H:i:s'));

        if (!$order->save()) {
            return Response::error('Failed to update order', 500)->getData();
        }

        // Log order field changes (excluding status_id which is logged separately)
        unset($changedOrderFields['status_id']);
        if (!empty($changedOrderFields)) {
            $this->getOrderLog()->addEntry(
                $id,
                msOrderLog::ACTION_FIELD,
                ['fields' => $changedOrderFields]
            );
        }

        // Handle address fields
        $address = $this->modx->getObject(msOrderAddress::class, ['order_id' => $id]);
        if ($address) {
            $oldAddressValues = $address->toArray();
            $changedAddressFields = [];

            // Get editable address fields from msModelField configuration
            $addressFields = $this->getModelFieldNames('msOrderAddress');
            foreach ($addressFields as $field) {
                if (array_key_exists($field, $params)) {
                    $oldValue = $address->get($field);
                    $newValue = $params[$field];
                    if ($oldValue != $newValue) {
                        $changedAddressFields[$field] = ['old' => $oldValue, 'new' => $newValue];
                    }
                    $address->set($field, $newValue);
                }
            }

            // Handle extra fields for msOrderAddress (stored as real DB columns via Object Extension)
            $addressExtraFields = $this->getExtraFieldKeys('MiniShop3\\Model\\msOrderAddress');
            foreach ($addressExtraFields as $extraField) {
                if (array_key_exists($extraField, $params)) {
                    $oldValue = $address->get($extraField);
                    $newValue = $params[$extraField];
                    if ($oldValue != $newValue) {
                        $changedAddressFields[$extraField] = ['old' => $oldValue, 'new' => $newValue];
                    }
                    $address->set($extraField, $newValue);
                }
            }

            $address->save();

            // Log address changes
            if (!empty($changedAddressFields)) {
                $this->getOrderLog()->addEntry(
                    $id,
                    msOrderLog::ACTION_ADDRESS,
                    ['fields' => $changedAddressFields]
                );
            }
        }

        // Log status change (using legacy method for backward compatibility with notifications)
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
     * Add product to order
     * POST /api/mgr/orders/{id}/products
     *
     * @param array $params URL parameters (id) and body data (product_id, count, price, weight, options)
     * @return array Response
     */
    public function addProduct(array $params = []): array
    {
        $orderId = (int)($params['id'] ?? 0);
        $productId = (int)($params['product_id'] ?? 0);

        if (!$orderId) {
            return Response::error('Order ID is required', 400)->getData();
        }

        if (!$productId) {
            return Response::error('Product ID is required', 400)->getData();
        }

        // Get the order
        $order = $this->modx->getObject(msOrder::class, $orderId);
        if (!$order) {
            return Response::error('Order not found', 404)->getData();
        }

        // Check order status (should not be final)
        $status = $this->modx->getObject(\MiniShop3\Model\msOrderStatus::class, $order->get('status_id'));
        if ($status && $status->get('final')) {
            return Response::error('Cannot add products to finalized order', 400)->getData();
        }

        // Get the product
        $product = $this->modx->getObject(\MiniShop3\Model\msProduct::class, $productId);
        if (!$product) {
            return Response::error('Product not found', 404)->getData();
        }

        // Get product data
        $productData = $this->modx->getObject(\MiniShop3\Model\msProductData::class, $productId);

        // Get values from params or product defaults
        $count = (int)($params['count'] ?? 1);
        if ($count < 1) {
            $count = 1;
        }

        $price = isset($params['price']) ? (float)$params['price'] : ($productData ? (float)$productData->get('price') : 0);
        $weight = isset($params['weight']) ? (float)$params['weight'] : ($productData ? (float)$productData->get('weight') : 0);
        $name = $params['name'] ?? $product->get('pagetitle');

        // Handle options
        $options = [];
        if (isset($params['options'])) {
            if (is_string($params['options'])) {
                $decoded = json_decode($params['options'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $options = $decoded;
                }
            } elseif (is_array($params['options'])) {
                $options = $params['options'];
            }
        }

        // Generate product_key (unique identifier for product variation)
        $productKey = md5($productId . json_encode($options));

        // Create order product record
        $orderProduct = $this->modx->newObject(\MiniShop3\Model\msOrderProduct::class);
        $orderProduct->set('order_id', $orderId);
        $orderProduct->set('product_id', $productId);
        $orderProduct->set('product_key', $productKey);
        $orderProduct->set('name', $name);
        $orderProduct->set('count', $count);
        $orderProduct->set('price', $price);
        $orderProduct->set('weight', $weight);
        $orderProduct->set('cost', $count * $price);
        $orderProduct->set('options', !empty($options) ? json_encode($options) : null);

        if (!$orderProduct->save()) {
            return Response::error('Failed to add product to order', 500)->getData();
        }

        // Log product addition
        $this->getOrderLog()->addEntry(
            $orderId,
            msOrderLog::ACTION_PRODUCTS,
            [
                'operation' => 'add',
                'product_id' => $productId,
                'product_name' => $name,
                'count' => $count,
                'price' => $price,
                'cost' => $count * $price,
            ]
        );

        // Recalculate order totals
        $this->recalculateOrderTotals($order);

        // Return created product data
        $result = $orderProduct->toArray();
        $result['pagetitle'] = $product->get('pagetitle');

        return Response::success($result, 'Product added to order successfully')->getData();
    }

    /**
     * Update order product
     * PUT /api/mgr/orders/{id}/products/{product_id}
     *
     * @param array $params URL parameters (id, product_id) and body data
     * @return array Response
     */
    public function updateProduct(array $params = []): array
    {
        $orderId = (int)($params['id'] ?? 0);
        $productId = (int)($params['product_id'] ?? 0);

        if (!$orderId || !$productId) {
            return Response::error('Order ID and Product ID are required', 400)->getData();
        }

        // Find the order product record
        $orderProduct = $this->modx->getObject(\MiniShop3\Model\msOrderProduct::class, [
            'id' => $productId,
            'order_id' => $orderId,
        ]);

        if (!$orderProduct) {
            return Response::error('Order product not found', 404)->getData();
        }

        // Get the order to recalculate totals
        $order = $this->modx->getObject(msOrder::class, $orderId);
        if (!$order) {
            return Response::error('Order not found', 404)->getData();
        }

        // Store old values for logging
        $oldValues = [
            'count' => $orderProduct->get('count'),
            'price' => $orderProduct->get('price'),
            'weight' => $orderProduct->get('weight'),
            'cost' => $orderProduct->get('cost'),
        ];

        // Allowed fields for update
        $allowedFields = ['count', 'price', 'weight', 'options'];
        $updated = false;
        $changes = [];

        foreach ($allowedFields as $field) {
            if (isset($params[$field])) {
                $oldValue = $orderProduct->get($field);
                $value = $params[$field];

                // Validate count
                if ($field === 'count') {
                    $value = max(1, (int)$value);
                }

                // Validate price/weight
                if (in_array($field, ['price', 'weight'])) {
                    $value = max(0, (float)$value);
                }

                // Handle options (JSON)
                if ($field === 'options' && is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }

                // Track changes for logging
                if ($oldValue != $value) {
                    $changes[$field] = ['old' => $oldValue, 'new' => $value];
                }

                $orderProduct->set($field, $value);
                $updated = true;
            }
        }

        if (!$updated) {
            return Response::error('No fields to update', 400)->getData();
        }

        // Recalculate cost
        $count = $orderProduct->get('count');
        $price = $orderProduct->get('price');
        $newCost = $count * $price;
        $orderProduct->set('cost', $newCost);

        // Add cost change if it changed
        if ($oldValues['cost'] != $newCost) {
            $changes['cost'] = ['old' => $oldValues['cost'], 'new' => $newCost];
        }

        if (!$orderProduct->save()) {
            return Response::error('Failed to update order product', 500)->getData();
        }

        // Log product update if there were changes
        if (!empty($changes)) {
            $this->getOrderLog()->addEntry(
                $orderId,
                msOrderLog::ACTION_PRODUCTS,
                [
                    'operation' => 'update',
                    'product_id' => $orderProduct->get('product_id'),
                    'product_name' => $orderProduct->get('name'),
                    'changes' => $changes,
                ]
            );
        }

        // Recalculate order totals
        $this->recalculateOrderTotals($order);

        return Response::success(
            $orderProduct->toArray(),
            'Order product updated successfully'
        )->getData();
    }

    /**
     * Delete order product
     * DELETE /api/mgr/orders/{id}/products/{product_id}
     *
     * @param array $params URL parameters (id, product_id)
     * @return array Response
     */
    public function deleteProduct(array $params = []): array
    {
        $orderId = (int)($params['id'] ?? 0);
        $productId = (int)($params['product_id'] ?? 0);

        if (!$orderId || !$productId) {
            return Response::error('Order ID and Product ID are required', 400)->getData();
        }

        // Find the order product record
        $orderProduct = $this->modx->getObject(\MiniShop3\Model\msOrderProduct::class, [
            'id' => $productId,
            'order_id' => $orderId,
        ]);

        if (!$orderProduct) {
            return Response::error('Order product not found', 404)->getData();
        }

        // Get the order to recalculate totals
        $order = $this->modx->getObject(msOrder::class, $orderId);
        if (!$order) {
            return Response::error('Order not found', 404)->getData();
        }

        // Check if this is the last product
        $productCount = $this->modx->getCount(\MiniShop3\Model\msOrderProduct::class, ['order_id' => $orderId]);
        if ($productCount <= 1) {
            return Response::error('Cannot delete the last product from order', 400)->getData();
        }

        // Store data for logging before removal
        $productData = [
            'product_id' => $orderProduct->get('product_id'),
            'product_name' => $orderProduct->get('name'),
            'count' => $orderProduct->get('count'),
            'price' => $orderProduct->get('price'),
            'cost' => $orderProduct->get('cost'),
        ];

        if (!$orderProduct->remove()) {
            return Response::error('Failed to delete order product', 500)->getData();
        }

        // Log product removal
        $this->getOrderLog()->addEntry(
            $orderId,
            msOrderLog::ACTION_PRODUCTS,
            [
                'operation' => 'remove',
                'product_id' => $productData['product_id'],
                'product_name' => $productData['product_name'],
                'count' => $productData['count'],
                'price' => $productData['price'],
                'cost' => $productData['cost'],
            ]
        );

        // Recalculate order totals
        $this->recalculateOrderTotals($order);

        return Response::success(null, 'Order product deleted successfully')->getData();
    }

    /**
     * Recalculate order totals (cart_cost, weight, cost)
     *
     * @param msOrder $order Order object
     */
    protected function recalculateOrderTotals(msOrder $order): void
    {
        $cartCost = 0;
        $weight = 0;

        $products = $this->modx->getIterator(\MiniShop3\Model\msOrderProduct::class, [
            'order_id' => $order->get('id'),
        ]);

        foreach ($products as $product) {
            $cartCost += (float)$product->get('cost');
            $weight += (float)$product->get('weight') * (int)$product->get('count');
        }

        $order->set('cart_cost', $cartCost);
        $order->set('weight', $weight);

        // Recalculate total cost (cart + delivery)
        $deliveryCost = (float)$order->get('delivery_cost');
        $order->set('cost', $cartCost + $deliveryCost);

        $order->save();
    }

    /**
     * Get order logs (history)
     * GET /api/mgr/orders/{id}/logs
     *
     * @param array $params URL parameters (id, visible_only)
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

        // Filter by visibility if requested (for customer-facing views)
        if (!empty($params['visible_only'])) {
            $c->where(['visible' => true]);
        }

        $c->sortby('timestamp', 'DESC');

        $logs = [];
        $collection = $this->modx->getIterator(\MiniShop3\Model\msOrderLog::class, $c);
        foreach ($collection as $log) {
            $data = $log->toArray();

            // Process entry - decode JSON if needed
            if (isset($data['entry'])) {
                if (is_string($data['entry'])) {
                    $decoded = json_decode($data['entry'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $data['entry_data'] = $decoded;
                    } else {
                        // Legacy string format - wrap in array
                        $data['entry_data'] = ['value' => $data['entry']];
                    }
                } elseif (is_array($data['entry'])) {
                    $data['entry_data'] = $data['entry'];
                }
            }

            // Add user name
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

        $this->getOrderLog()->addEntry(
            $order->get('id'),
            msOrderLog::ACTION_STATUS,
            [
                'old_status_id' => $oldStatusId,
                'new_status_id' => $newStatusId,
                'old_status_name' => $oldStatus ? $oldStatus->get('name') : (string)$oldStatusId,
                'new_status_name' => $newStatus ? $newStatus->get('name') : (string)$newStatusId,
            ]
        );
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
     * Get orders statistics based on current filters
     *
     * Statistics are calculated only for orders with statuses
     * specified in ms3_status_for_stat setting (e.g. "2,3" for paid/completed).
     *
     * @param array $params Filter parameters (same as getList)
     * @return array Statistics data
     */
    protected function getOrdersStats(array $params = []): array
    {
        $c = $this->modx->newQuery(msOrder::class);

        // Filter by statuses for statistics (ms3_status_for_stat)
        // Only count orders with these statuses (e.g. paid, completed)
        $statusForStat = $this->modx->getOption('ms3_status_for_stat', null, '2,3');
        if (!empty($statusForStat)) {
            $statuses = array_map('intval', array_filter(explode(',', $statusForStat)));
            if (!empty($statuses)) {
                $c->where(['status_id:IN' => $statuses]);
            }
        }

        // Apply filter_ prefixed params
        foreach ($params as $key => $value) {
            if (strpos($key, 'filter_') === 0 && !empty($value)) {
                $fieldName = substr($key, 7);
                $this->applyFilter($c, $fieldName, $value);
            }
        }

        // Direct filter params
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
            $c->where([
                'msOrder.createdon:>=' => date('Y-m-d 00:00:00', strtotime($dateFrom)),
            ]);
        }
        if ($dateTo = ($params['createdon_to'] ?? $params['date_end'] ?? null)) {
            $c->where([
                'msOrder.createdon:<=' => date('Y-m-d 23:59:59', strtotime($dateTo)),
            ]);
        }

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

    /**
     * Format order data for API response
     *
     * @param array $data Order data
     * @return array Formatted data
     */
    protected function formatOrder(array $data): array
    {
        $ms3 = $this->modx->services->get('ms3');

        if (!empty($data['status_name']) && str_starts_with($data['status_name'], 'ms3_')) {
            $translated = $this->modx->lexicon($data['status_name']);
            if ($translated !== $data['status_name']) {
                $data['status_name'] = $translated;
            }
        }

        if (!empty($data['delivery_name']) && str_starts_with($data['delivery_name'], 'ms3_')) {
            $translated = $this->modx->lexicon($data['delivery_name']);
            if ($translated !== $data['delivery_name']) {
                $data['delivery_name'] = $translated;
            }
        }

        if (!empty($data['payment_name']) && str_starts_with($data['payment_name'], 'ms3_')) {
            $translated = $this->modx->lexicon($data['payment_name']);
            if ($translated !== $data['payment_name']) {
                $data['payment_name'] = $translated;
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

    /**
     * Get extra field keys for a specific model class
     *
     * @param string $modelClass Model class name (msOrder, msOrderAddress, etc.)
     * @return array Array of extra field keys
     */
    protected function getExtraFieldKeys(string $modelClass): array
    {
        $keys = [];

        $query = $this->modx->newQuery(msExtraField::class);
        $query->where([
            'class' => $modelClass,
            'active' => true,
        ]);

        foreach ($this->modx->getIterator(msExtraField::class, $query) as $field) {
            $keys[] = $field->get('key');
        }

        return $keys;
    }

    /**
     * Get field names from msModelField configuration
     *
     * @param string $model Model name (msOrder, msOrderAddress)
     * @return array Array of field names
     */
    protected function getModelFieldNames(string $model): array
    {
        $names = [];

        $query = $this->modx->newQuery(msModelField::class);
        $query->where(['model' => $model]);

        foreach ($this->modx->getIterator(msModelField::class, $query) as $field) {
            $names[] = $field->get('name');
        }

        return $names;
    }
}
