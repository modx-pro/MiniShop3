<?php

namespace MiniShop3\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msPayment;
use MiniShop3\Services\CustomerFactory;
use MiniShop3\Services\CustomerDuplicateChecker;
use MODX\Revolution\modX;

/**
 * Order Finalize Service
 *
 * Handles order finalization from admin panel.
 * Converts DRAFT order to final order with proper validation,
 * cost calculation, events and notifications.
 */
class OrderFinalizeService
{
    protected modX $modx;
    protected MiniShop3 $ms3;

    public function __construct(modX $modx, MiniShop3 $ms3)
    {
        $this->modx = $modx;
        $this->ms3 = $ms3;
    }

    /**
     * Finalize order (convert draft to final order)
     *
     * @param int $orderId Order ID to finalize
     * @param array $options Options for finalization:
     *   - skip_validation: bool - Skip validation checks
     *   - skip_notifications: bool - Skip sending notifications
     *   - skip_payment: bool - Skip payment gateway call
     *   - create_customer: bool - Create customer from order address data
     *   - force_create_customer: bool - Create customer even if duplicate found
     * @return array Response with success/error
     */
    public function finalize(int $orderId, array $options = []): array
    {
        $skipValidation = $options['skip_validation'] ?? false;
        $skipNotifications = $options['skip_notifications'] ?? false;
        $createCustomer = $options['create_customer'] ?? false;
        $forceCreateCustomer = $options['force_create_customer'] ?? false;

        // Get order
        /** @var msOrder $order */
        $order = $this->modx->getObject(msOrder::class, $orderId);
        if (!$order) {
            return $this->error('ms3_order_err_nf');
        }

        // Check order is in DRAFT status
        $statusDraft = (int) $this->modx->getOption('ms3_status_draft', null, 1) ?: 1;
        if ((int) $order->get('status_id') !== $statusDraft) {
            return $this->error('ms3_order_err_already_finalized');
        }

        // Validation
        if (!$skipValidation) {
            $validationResult = $this->validate($order);
            if (!$validationResult['success']) {
                return $validationResult;
            }
        }

        // Event: before manager-side order creation (finalize = draft → real order).
        // Sibling of msOnSubmitOrder but fires in the manager finalize flow.
        $response = $this->ms3->utils->invokeEvent('msOnBeforeMgrCreateOrder', [
            'service' => $this,
            'msOrder' => $order,
            'from_manager' => true,
        ]);

        if (!$response['success']) {
            return $this->error($response['message']);
        }

        // Create customer if requested and no customer linked yet
        if ($createCustomer && empty($order->get('customer_id'))) {
            $customerResult = $this->createCustomerFromOrder($order, $forceCreateCustomer);
            if (!$customerResult['success']) {
                // If duplicate found, return it for user decision
                if (!empty($customerResult['data']['duplicate_found'])) {
                    return $customerResult;
                }
                return $this->error($customerResult['message'] ?? 'ms3_err_customer_create');
            }

            // Update order with new customer ID
            if (!empty($customerResult['data']['customer_id'])) {
                $order->set('customer_id', $customerResult['data']['customer_id']);
                $order->save();
            }
        }

        // Calculate costs
        $costResult = $this->calculateCosts($order);
        if (!$costResult['success']) {
            return $costResult;
        }

        // Generate order number if not set
        if (empty($order->get('num'))) {
            $order->set('num', $this->getNewOrderNum());
        }

        // Update order
        $order->set('updatedon', time());
        $order->set('cost', $costResult['data']['total_cost']);
        $order->set('cart_cost', $costResult['data']['cart_cost']);
        $order->set('delivery_cost', $costResult['data']['delivery_cost']);
        $order->save();

        // Event: before create order (same as frontend)
        $response = $this->ms3->utils->invokeEvent('msOnBeforeCreateOrder', [
            'service' => $this,
            'msOrder' => $order,
            'from_manager' => true,
        ]);

        if (!$response['success']) {
            return $this->error($response['message']);
        }

        // Event: on create order (same as frontend)
        $response = $this->ms3->utils->invokeEvent('msOnCreateOrder', [
            'service' => $this,
            'msOrder' => $order,
            'from_manager' => true,
        ]);

        if (!$response['success']) {
            return $this->error($response['message']);
        }

        // Change status to "new"
        $statusNew = (int) $this->modx->getOption('ms3_status_new', null, 2) ?: 2;

        /** @var OrderStatusService $orderStatus */
        $orderStatus = $this->modx->services->get('ms3_order_status');
        $statusResponse = $orderStatus->change(
            $order->get('id'),
            $statusNew,
            $skipNotifications
        );

        if ($statusResponse !== true) {
            return $this->error($statusResponse);
        }

        // Reload order after status change
        $order = $this->modx->getObject(msOrder::class, $orderId);

        // Event: manager-side order creation finished (draft finalized).
        $this->ms3->utils->invokeEvent('msOnMgrCreateOrder', [
            'service' => $this,
            'msOrder' => $order,
            'from_manager' => true,
        ]);

        return $this->success('ms3_order_finalized', [
            'order_id' => $order->get('id'),
            'order_num' => $order->get('num'),
            'status_id' => $order->get('status_id'),
        ]);
    }

    /**
     * Validate order before finalization
     *
     * @param msOrder $order
     * @return array
     */
    protected function validate(msOrder $order): array
    {
        $errors = [];

        // Check products exist
        $productCount = $this->modx->getCount(msOrderProduct::class, [
            'order_id' => $order->get('id'),
        ]);

        if ($productCount === 0) {
            $errors[] = 'products';
        }

        // Check delivery is selected
        $deliveryId = (int) $order->get('delivery_id');
        if ($deliveryId > 0) {
            $delivery = $this->modx->getObject(msDelivery::class, [
                'id' => $deliveryId,
                'active' => 1,
            ]);
            if (!$delivery) {
                $errors[] = 'delivery_id';
            }
        } else {
            $errors[] = 'delivery_id';
        }

        // Check payment is selected
        $paymentId = (int) $order->get('payment_id');
        if ($paymentId > 0) {
            $payment = $this->modx->getObject(msPayment::class, [
                'id' => $paymentId,
                'active' => 1,
            ]);
            if (!$payment) {
                $errors[] = 'payment_id';
            }
        } else {
            $errors[] = 'payment_id';
        }

        // Check customer is linked (optional - manager can create orders without customer)
        // $customerId = (int) $order->get('customer_id');
        // if ($customerId === 0) {
        //     $errors[] = 'customer_id';
        // }

        // Return early if basic errors found
        if (!empty($errors)) {
            return $this->error('ms3_order_err_validation', $errors);
        }

        // Check required fields for delivery
        $requiredFieldsErrors = $this->validateDeliveryRequiredFields($order);
        if (!empty($requiredFieldsErrors)) {
            return $this->error('ms3_order_err_validation', $requiredFieldsErrors);
        }

        return $this->success();
    }

    /**
     * Create customer from order address data
     *
     * @param msOrder $order Order to get address data from
     * @param bool $forceCreate Skip duplicate check and force creation
     * @return array Success with customer_id or error with duplicate_found
     */
    protected function createCustomerFromOrder(msOrder $order, bool $forceCreate = false): array
    {
        // Get order address data
        $address = $this->modx->getObject(msOrderAddress::class, ['order_id' => $order->get('id')]);
        if (!$address) {
            return $this->error('ms3_order_err_address_nf');
        }

        $addressData = $address->toArray();

        // Need at least email or phone to create customer
        $email = trim($addressData['email'] ?? '');
        $phone = trim($addressData['phone'] ?? '');

        if (empty($email) && empty($phone)) {
            return $this->error('ms3_order_err_customer_contact', ['email', 'phone']);
        }

        // Prepare customer data
        $customerData = [
            'first_name' => $addressData['first_name'] ?? '',
            'last_name' => $addressData['last_name'] ?? '',
            'email' => $email,
            'phone' => $phone,
        ];

        // Check for duplicates (unless forcing creation)
        if (!$forceCreate) {
            /** @var CustomerDuplicateChecker $duplicateChecker */
            $duplicateChecker = $this->modx->services->get('ms3_customer_duplicate_checker');

            if ($duplicateChecker->hasCheckableData($customerData)) {
                $existingCustomer = $duplicateChecker->findDuplicate($customerData);

                if ($existingCustomer) {
                    // Return duplicate info for user decision
                    return $this->success('', [
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
                    ]);
                }
            }
        }

        // Create new customer
        try {
            /** @var CustomerFactory $customerFactory */
            $customerFactory = $this->modx->services->get('ms3_customer_factory');
            $customer = $customerFactory->createFromOrderData($customerData);

            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                "[OrderFinalizeService] Created new customer #{$customer->get('id')} from order #{$order->get('id')}"
            );

            return $this->success('', ['customer_id' => $customer->get('id')]);
        } catch (\Exception $e) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[OrderFinalizeService] Failed to create customer: " . $e->getMessage()
            );
            return $this->error('ms3_err_customer_create');
        }
    }

    /**
     * Validate delivery required fields
     *
     * @param msOrder $order
     * @return array Array of missing field names
     */
    protected function validateDeliveryRequiredFields(msOrder $order): array
    {
        $errors = [];
        $deliveryId = (int) $order->get('delivery_id');

        if ($deliveryId <= 0) {
            return $errors; // No delivery = no required fields to check
        }

        // Get delivery validation rules
        $delivery = $this->modx->getObject(msDelivery::class, $deliveryId);
        if (!$delivery) {
            return $errors;
        }

        $validationRules = $delivery->get('validation_rules');
        if (empty($validationRules)) {
            return $errors;
        }

        $rules = json_decode($validationRules, true);
        if (!is_array($rules)) {
            return $errors;
        }

        // Get order address data
        $address = $this->modx->getObject(msOrderAddress::class, ['order_id' => $order->get('id')]);
        $addressData = $address ? $address->toArray() : [];

        // Check each required field
        foreach ($rules as $field => $rule) {
            // Check if field has 'required' rule
            $rulesParts = array_map('trim', explode('|', $rule));
            if (!in_array('required', $rulesParts)) {
                continue;
            }

            // Check if field has value in address data
            $value = $addressData[$field] ?? null;
            if (empty($value)) {
                $errors[] = $field;
            }
        }

        return $errors;
    }

    /**
     * Calculate order costs
     *
     * @param msOrder $order
     * @return array
     */
    protected function calculateCosts(msOrder $order): array
    {
        // Calculate cart cost from order products
        $cartCost = 0;
        $weight = 0;

        $products = $this->modx->getIterator(msOrderProduct::class, [
            'order_id' => $order->get('id'),
        ]);

        foreach ($products as $product) {
            $cartCost += (float) $product->get('cost');
            $weight += (float) $product->get('weight') * (int) $product->get('count');
        }

        // Calculate delivery cost
        $deliveryCost = 0;
        $deliveryId = (int) $order->get('delivery_id');

        if ($deliveryId > 0) {
            /** @var msDelivery $delivery */
            $delivery = $this->modx->getObject(msDelivery::class, $deliveryId);
            if ($delivery) {
                // Use delivery's getCost method if available, otherwise use fixed price
                $deliveryCost = (float) $delivery->get('price');

                // Check for weight-based pricing
                $weightPrice = (float) $delivery->get('weight_price');
                if ($weightPrice > 0 && $weight > 0) {
                    $deliveryCost += $weight * $weightPrice;
                }
            }
        }

        // Update order weight
        $order->set('weight', $weight);

        /** @var OrderService $orderService */
        $orderService = $this->modx->services->get('ms3_order_service');
        $totalCost = $orderService->clampComputedTotal($order, (float) $cartCost, (float) $deliveryCost, 0.0);

        return $this->success('', [
            'cart_cost' => $cartCost,
            'delivery_cost' => $deliveryCost,
            'total_cost' => $totalCost,
            'weight' => $weight,
        ]);
    }

    /**
     * Generate new order number
     *
     * @return string
     */
    protected function getNewOrderNum(): string
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

        $prefix = $format ? date($format) : date('ym');

        // Find last order with this prefix
        $c = $this->modx->newQuery(msOrder::class);
        $c->where(['num:LIKE' => "{$prefix}%"]);
        $c->select('num');
        $c->sortby('id', 'DESC');
        $c->limit(1);

        $count = 0;
        if ($c->prepare() && $c->stmt->execute()) {
            $num = $c->stmt->fetchColumn();
            if (!empty($num)) {
                $parts = explode($separator, $num);
                $count = (int)($parts[1] ?? 0);
            }
        }

        $count++;

        return sprintf('%s%s%d', $prefix, $separator, $count);
    }

    /**
     * Success response
     */
    protected function success(string $message = '', array $data = []): array
    {
        return $this->ms3->utils->success($message, $data);
    }

    /**
     * Error response
     */
    protected function error(string $message, array $data = []): array
    {
        return $this->ms3->utils->error($message, $data);
    }
}
