<?php

namespace MiniShop3\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msOrderLog;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Services\CustomerDuplicateChecker;
use MiniShop3\Services\CustomerFactory;
use MiniShop3\Services\Delivery\DeliveryService;
use MODX\Revolution\modX;
use Ramsey\Uuid\Uuid;

class ManagerOrderMutationService
{
    protected modX $modx;
    protected ManagerOrderPresenter $presenter;
    protected ?OrderLogService $orderLog;

    public function __construct(modX $modx, ?ManagerOrderPresenter $presenter = null, ?OrderLogService $orderLog = null)
    {
        $this->modx = $modx;
        $this->presenter = $presenter ?? new ManagerOrderPresenter($modx);
        $this->orderLog = $orderLog;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{success: bool, message: string, data: array<string, mixed>, status: int}
     */
    public function create(array $params = []): array
    {
        $createCustomer = !empty($params['create_customer']);
        $forceCreateCustomer = !empty($params['force_create_customer']);
        $customerId = (int)($params['customer_id'] ?? 0);

        // Handle customer creation
        if ($createCustomer && $customerId === 0) {
            // Validate customer data - need at least email or phone
            $email = trim((string)($params['email'] ?? ''));
            $phone = trim((string)($params['phone'] ?? ''));

            if (empty($email) && empty($phone)) {
                return $this->error(
                    (string)$this->modx->lexicon('ms3_order_err_customer_contact'),
                    HttpStatus::BAD_REQUEST,
                    ['fields' => ['email', 'phone']]
                );
            }

            /** @var CustomerDuplicateChecker $duplicateChecker */
            $duplicateChecker = $this->modx->services->get('ms3_customer_duplicate_checker');

            // Check for duplicates (unless forcing creation)
            if (!$forceCreateCustomer && $duplicateChecker->hasCheckableData($params)) {
                $existingCustomer = $duplicateChecker->findDuplicate($params);

                if ($existingCustomer) {
                    // Return duplicate info for user decision
                    return $this->success([
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
                    ], 'Customer with matching data already exists');
                }
            }

            // Create new customer
            try {
                /** @var CustomerFactory $customerFactory */
                $customerFactory = $this->modx->services->get('ms3_customer_factory');
                $customer = $customerFactory->createFromOrderData($params);
                $customerId = (int)$customer->get('id');

                $this->modx->log(
                    modX::LOG_LEVEL_INFO,
                    "[ManagerOrderMutationService] Created new customer #{$customerId} from order data"
                );
            } catch (\Exception $e) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[ManagerOrderMutationService] Failed to create customer: ' . $e->getMessage()
                );

                return $this->error(
                    'Failed to create customer: ' . $e->getMessage(),
                    HttpStatus::INTERNAL_SERVER_ERROR
                );
            }
        }

        $order = $this->modx->newObject(msOrder::class);
        $order->set('uuid', (string)Uuid::uuid4());
        $order->set('token', md5(uniqid('ms3_mgr_', true)));

        // Set status to "Draft" - order will be finalized later
        $statusDraft = (int)$this->modx->getOption('ms3_status_draft', null, 1) ?: 1;
        $order->set('status_id', $statusDraft);

        $order->set('context', $params['context'] ?? 'web');
        $order->set('createdon', time());
        $order->set('updatedon', time());
        $order->set('user_id', $this->modx->user->get('id'));
        $order->set('customer_id', $customerId);
        $order->set('delivery_id', (int)($params['delivery_id'] ?? 0));
        $order->set('payment_id', (int)($params['payment_id'] ?? 0));
        $order->set('order_comment', $params['order_comment'] ?? '');

        // Order number will be generated on finalization (NULL so UNIQUE allows many drafts)
        $order->set('num', null);

        // Initial costs (will be recalculated after adding products)
        $order->set('cart_cost', 0);
        /** @var OrderService $orderService */
        $orderService = $this->modx->services->get('ms3_order_service');
        $deliveryCost = (float)($params['delivery_cost'] ?? 0);
        $order->set('delivery_cost', $deliveryCost);
        $order->set('cost', $orderService->clampComputedTotal(null, 0.0, $deliveryCost, 0.0));
        $order->set('weight', 0);

        $pairError = $this->validateDeliveryPaymentPair(
            (int) $order->get('delivery_id'),
            (int) $order->get('payment_id')
        );
        if ($pairError !== null) {
            return $pairError;
        }

        if (!$order->save()) {
            return $this->error('Failed to create order', HttpStatus::INTERNAL_SERVER_ERROR);
        }

        $address = $this->modx->newObject(msOrderAddress::class);
        $address->set('order_id', $order->get('id'));
        $address->set('createdon', time());

        // Address fields from params
        // Safe to use array_key_exists: new entity, no previous value to silently overwrite;
        // all address columns are nullable VARCHAR/TEXT.
        $addressFields = [
            'first_name', 'last_name', 'phone', 'email',
            'country', 'index', 'region', 'city', 'metro',
            'street', 'building', 'entrance', 'floor', 'room',
            'comment', 'text_address',
        ];
        foreach ($addressFields as $field) {
            if (array_key_exists($field, $params)) {
                $address->set($field, $params[$field]);
            }
        }
        $address->save();

        // Log draft creation
        $this->getOrderLog()->addEntry(
            (int)$order->get('id'),
            msOrderLog::ACTION_STATUS,
            [
                'old_status_id' => 0,
                'new_status_id' => $statusDraft,
                'old_status_name' => '',
                'new_status_name' => $this->presenter->getStatusName($statusDraft),
            ]
        );

        // Return created order with address data
        $orderData = $order->toArray();
        $orderData = $this->presenter->mergeAddressIntoOrderData($orderData, $address);
        $orderData['customer_created'] = $createCustomer && $customerId > 0;

        return $this->success(
            $this->presenter->formatOrder($orderData),
            'Order draft created'
        );
    }

    /**
     * @param array<string, mixed> $params
     * @return array{success: bool, message: string, data: array<string, mixed>, status: int}
     */
    public function update(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);
        if (!$id) {
            return $this->error('Order ID is required', HttpStatus::BAD_REQUEST);
        }

        /** @var msOrder|null $order */
        $order = $this->modx->getObject(msOrder::class, $id);
        if (!$order) {
            return $this->error('Order not found', HttpStatus::NOT_FOUND);
        }

        // Store old values for logging
        $oldStatusId = (int)$order->get('status_id');

        // Get editable order fields from msModelField configuration
        $orderFields = $this->presenter->getModelFieldNames('msOrder');
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
        $orderExtraFields = $this->presenter->getExtraFieldKeys('MiniShop3\\Model\\msOrder');
        foreach ($orderExtraFields as $extraFieldKey) {
            if (!array_key_exists($extraFieldKey, $params)) {
                continue;
            }

            $normalized = $this->presenter->normalizeExtraFieldValue(
                'MiniShop3\\Model\\msOrder',
                $extraFieldKey,
                $params[$extraFieldKey]
            );
            if (!$normalized['ok']) {
                return $this->error(
                    (string)$normalized['message'],
                    HttpStatus::UNPROCESSABLE_ENTITY
                );
            }

            $newValue = $normalized['value'];
            $oldValue = $order->get($extraFieldKey);
            if ($oldValue != $newValue) {
                $changedOrderFields[$extraFieldKey] = ['old' => $oldValue, 'new' => $newValue];
            }
            $order->set($extraFieldKey, $newValue);
        }

        $pairError = $this->validateDeliveryPaymentPair(
            (int) $order->get('delivery_id'),
            (int) $order->get('payment_id')
        );
        if ($pairError !== null) {
            return $pairError;
        }

        $order->set('updatedon', date('Y-m-d H:i:s'));
        if (!$order->save()) {
            return $this->error('Failed to update order', HttpStatus::INTERNAL_SERVER_ERROR);
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
            $changedAddressFields = [];

            // Get editable address fields from msModelField configuration
            $addressFields = $this->presenter->getModelFieldNames('msOrderAddress');
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
            $addressExtraFields = $this->presenter->getExtraFieldKeys('MiniShop3\\Model\\msOrderAddress');
            foreach ($addressExtraFields as $extraFieldKey) {
                if (!array_key_exists($extraFieldKey, $params)) {
                    continue;
                }

                $normalized = $this->presenter->normalizeExtraFieldValue(
                    'MiniShop3\\Model\\msOrderAddress',
                    $extraFieldKey,
                    $params[$extraFieldKey]
                );
                if (!$normalized['ok']) {
                    return $this->error(
                        (string)$normalized['message'],
                        HttpStatus::UNPROCESSABLE_ENTITY
                    );
                }

                $newValue = $normalized['value'];
                $oldValue = $address->get($extraFieldKey);
                if ($oldValue != $newValue) {
                    $changedAddressFields[$extraFieldKey] = ['old' => $oldValue, 'new' => $newValue];
                }
                $address->set($extraFieldKey, $newValue);
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

        // Handle status change via OrderStatusService (sends notifications)
        $newStatusId = (int)$order->get('status_id');
        if ($oldStatusId !== $newStatusId) {
            // Revert status to old value - OrderStatusService will change it properly
            $order->set('status_id', $oldStatusId);
            $order->save();

            /** @var OrderStatusService $orderStatusService */
            $orderStatusService = $this->modx->services->get('ms3_order_status');
            $result = $orderStatusService->change((int)$order->get('id'), $newStatusId);

            if ($result !== true) {
                return $this->error(
                    is_string($result) ? $result : (string)$this->modx->lexicon('ms3_err_status_change'),
                    HttpStatus::BAD_REQUEST
                );
            }

            // Reload order to get updated data
            $order = $this->modx->getObject(msOrder::class, $id);
            if (!$order instanceof msOrder) {
                return $this->error('Order not found after update', HttpStatus::INTERNAL_SERVER_ERROR);
            }
        }

        // Return the same shape as GET (address-merge, formatted cost, no secret fields)
        return $this->success(
            $this->presenter->buildOrderPayloadFromModel($order),
            'Order updated successfully'
        );
    }

    /**
     * @param array<string, mixed> $params
     * @return array{success: bool, message: string, data: array<string, mixed>, status: int}
     */
    public function delete(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);
        if (!$id) {
            return $this->error('Order ID is required', HttpStatus::BAD_REQUEST);
        }

        $order = $this->modx->getObject(msOrder::class, $id);
        if (!$order) {
            return $this->error('Order not found', HttpStatus::NOT_FOUND);
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
            return $this->error('Failed to delete order', HttpStatus::INTERNAL_SERVER_ERROR);
        }

        return $this->success([], 'Order deleted successfully');
    }

    /**
     * @param array<string, mixed> $params
     * @return array{success: bool, message: string, data: array<string, mixed>, status: int}
     */
    public function bulkDelete(array $params = []): array
    {
        $ids = $params['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            return $this->error('Order IDs array is required', HttpStatus::BAD_REQUEST);
        }

        // Sanitize IDs
        $ids = array_filter(array_map('intval', $ids), static fn($id) => $id > 0);
        if (empty($ids)) {
            return $this->error('No valid order IDs provided', HttpStatus::BAD_REQUEST);
        }

        $deleted = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $order = $this->modx->getObject(msOrder::class, $id);
            if (!$order) {
                $failed++;
                continue;
            }

            $addresses = $this->modx->getIterator(msOrderAddress::class, ['order_id' => $id]);
            foreach ($addresses as $address) {
                $address->remove();
            }

            $products = $this->modx->getIterator(\MiniShop3\Model\msOrderProduct::class, ['order_id' => $id]);
            foreach ($products as $product) {
                $product->remove();
            }

            $logs = $this->modx->getIterator(msOrderLog::class, ['order_id' => $id]);
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
            return $this->error('Failed to delete orders', HttpStatus::INTERNAL_SERVER_ERROR);
        }

        return $this->success(
            ['deleted' => $deleted, 'failed' => $failed],
            "Deleted {$deleted} orders"
        );
    }

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
     * @param array<string, mixed> $data
     * @return array{success: true, message: string, data: array<string, mixed>, status: int}
     */
    /**
     * Reject incompatible delivery/payment pairs (msDeliveryMember link required).
     *
     * @return array{success: bool, message: string, data: array<string, mixed>, status: int}|null
     */
    protected function validateDeliveryPaymentPair(int $deliveryId, int $paymentId): ?array
    {
        if ($deliveryId <= 0 || $paymentId <= 0) {
            return null;
        }

        /** @var DeliveryService $deliveryService */
        $deliveryService = $this->modx->services->get('ms3_delivery_service');
        $pairError = $deliveryService->getDeliveryPaymentPairError($deliveryId, $paymentId);
        if ($pairError === null) {
            return null;
        }

        return $this->error($pairError, HttpStatus::UNPROCESSABLE_ENTITY);
    }

    protected function success(array $data = [], string $message = ''): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'status' => HttpStatus::OK,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: false, message: string, data: array<string, mixed>, status: int}
     */
    protected function error(string $message, int $status, array $data = []): array
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => $data,
            'status' => $status,
        ];
    }
}
