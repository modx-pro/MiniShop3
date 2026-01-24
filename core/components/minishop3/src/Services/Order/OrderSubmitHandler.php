<?php

namespace MiniShop3\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msPayment;
use MODX\Revolution\modX;

/**
 * Order Submit Handler
 *
 * Handles order submission workflow: validation, cost calculation,
 * user resolution, status change, and payment initiation.
 */
class OrderSubmitHandler
{
    protected modX $modx;
    protected MiniShop3 $ms3;
    protected OrderDraftManager $draftManager;
    protected OrderCostCalculator $costCalculator;
    protected OrderFieldManager $fieldManager;
    protected OrderAddressManager $addressManager;
    protected OrderUserResolver $userResolver;

    public function __construct(
        modX $modx,
        MiniShop3 $ms3,
        OrderDraftManager $draftManager,
        OrderCostCalculator $costCalculator,
        OrderFieldManager $fieldManager,
        OrderAddressManager $addressManager,
        OrderUserResolver $userResolver
    ) {
        $this->modx = $modx;
        $this->ms3 = $ms3;
        $this->draftManager = $draftManager;
        $this->costCalculator = $costCalculator;
        $this->fieldManager = $fieldManager;
        $this->addressManager = $addressManager;
        $this->userResolver = $userResolver;
    }

    /**
     * Submit order (convert draft to final order)
     *
     * @param msOrder $draft Draft order to submit
     * @param array $orderData Current order data
     * @param string $token Session token
     * @param string $ctx Context
     * @param array $submitData Additional submit data from event
     * @return array Response with redirect URL on success
     */
    public function submit(
        msOrder $draft,
        array $orderData,
        string $token,
        string $ctx = 'web',
        array $submitData = []
    ): array {
        // Event: before submit
        $response = $this->ms3->utils->invokeEvent('msOnSubmitOrder', [
            'handler' => $this,
            'draft' => $draft,
            'orderData' => $orderData,
            'data' => $submitData,
        ]);

        if (!$response['success']) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[OrderSubmitHandler::submit] Event msOnSubmitOrder failed: ' . $response['message']
            );
            return $this->error($response['message']);
        }

        // Process any additional data from event
        if (!empty($response['data']['data'])) {
            foreach ($response['data']['data'] as $key => $value) {
                $this->fieldManager->add($draft, $orderData, $key, $value);
            }
            // Refresh order data
            $orderData = $this->draftManager->toArray($draft);
        }

        // Validate cart has items
        $this->ms3->cart->initialize($ctx, $token);
        $cartResponse = $this->ms3->cart->status();

        if (!$cartResponse['success']) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[OrderSubmitHandler::submit] Cart status failed: ' . $cartResponse['message']
            );
            return $this->error($cartResponse['message']);
        }

        $cartStatus = $cartResponse['data'];
        if (empty($cartStatus['total_count'])) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[OrderSubmitHandler::submit] Cart is empty');
            return $this->error('ms3_order_err_empty');
        }

        // Validate delivery is selected (before customer creation)
        if (empty($orderData['delivery_id'])) {
            return $this->error('ms3_order_err_delivery', ['delivery_id']);
        }

        // Validate payment is selected (before customer creation)
        if (empty($orderData['payment_id'])) {
            return $this->error('ms3_order_err_payment', ['payment_id']);
        }

        /** @var msPayment $msPayment */
        $msPayment = $this->modx->getObject(
            msPayment::class,
            ['id' => $orderData['payment_id'], 'active' => 1]
        );

        if (!$msPayment) {
            return $this->error('ms3_order_err_payment_not_found', ['payment_id' => $orderData['payment_id']]);
        }

        // Check required fields for delivery
        $requiredResponse = $this->fieldManager->getDeliveryRequiredFields($orderData['delivery_id']);
        if (!$requiredResponse['success']) {
            return $this->error($requiredResponse['message'] ?? 'ms3_order_err_delivery');
        }

        $requires = $requiredResponse['data']['requires'];
        $errors = [];
        foreach ($requires as $field => $rules) {
            if (empty($orderData[$field]) && empty($orderData['address_' . $field])) {
                $errors[] = $field;
            }
        }

        if (!empty($errors)) {
            return $this->error('ms3_order_err_requires', $errors);
        }

        // Link customer to order (optional - order can proceed without customer)
        $customerId = $draft->get('customer_id');
        if (empty($customerId)) {
            $this->ms3->customer->initialize($token);
            $customerId = $this->ms3->customer->getOrCreate();

            if (!empty($customerId)) {
                $draft->set('customer_id', $customerId);
                $draft->save();
            }
            // If no customer created (no email/phone), order proceeds with data in msOrderAddress only
        }

        // Register MODX user if configured
        $registerUser = $this->modx->getOption('ms3_order_register_user_on_submit', null, false);
        $userId = 0;
        if ($registerUser) {
            $userId = $this->userResolver->getUserId($orderData);
            if (empty($userId)) {
                return $this->error('ms3_err_user_nf');
            }
        }

        // Calculate costs
        $costResponse = $this->costCalculator->getTotalCost($draft, $orderData, $token, $ctx);
        if (!$costResponse['success']) {
            return $this->error($costResponse['message']);
        }

        $deliveryCost = $costResponse['data']['delivery_cost'];
        $cartCost = $costResponse['data']['cart_cost'];

        // Generate order number
        $num = $this->getNewOrderNum();

        // Update draft with final data
        // Total cost = cart cost + delivery cost
        $totalCost = $cartCost + $deliveryCost;

        $draft->fromArray([
            'customer_id' => $customerId,
            'user_id' => $userId,
            'updatedon' => time(),
            'num' => $num,
            'cart_cost' => $cartCost,
            'delivery_cost' => $deliveryCost,
            'cost' => $totalCost,
        ]);

        $draft->Address->set('updatedon', time());
        $draft->save();

        // Save address to customer's saved addresses if requested
        $properties = $draft->get('properties') ?? [];
        if (!empty($properties['save_address']) && !empty($customerId)) {
            $this->addressManager->saveToCustomerAddresses($customerId, $orderData);
        }

        // Event: before create order
        $response = $this->ms3->utils->invokeEvent('msOnBeforeCreateOrder', [
            'handler' => $this,
            'msOrder' => $draft,
        ]);

        if (!$response['success']) {
            return $this->error($response['message']);
        }

        // Event: on create order
        $response = $this->ms3->utils->invokeEvent('msOnCreateOrder', [
            'handler' => $this,
            'msOrder' => $draft,
        ]);

        if (!$response['success']) {
            return $this->error($response['message']);
        }

        // Store order in session
        if (empty($_SESSION['ms3']['orders'])) {
            $_SESSION['ms3']['orders'] = [];
        }
        $_SESSION['ms3']['orders'][] = $draft->get('id');

        // Change status to "new"
        $statusNew = $this->modx->getOption('ms3_status_new', null, 2);
        /** @var OrderStatusService $orderStatus */
        $orderStatus = $this->modx->services->get('ms3_order_status');
        $statusResponse = $orderStatus->change($draft->get('id'), $statusNew);

        if ($statusResponse !== true) {
            return $this->error($statusResponse, ['msorder' => $draft->get('uuid')]);
        }

        // Reload order after status change
        /** @var msOrder $msOrder */
        $msOrder = $this->modx->getObject(msOrder::class, ['id' => $draft->get('id')]);

        // Send to payment gateway (payment was validated earlier)
        $paymentResponse = $msPayment->send($msOrder);

        if (!$paymentResponse['success']) {
            return $this->error($paymentResponse['message'] ?? null);
        }

        // Return redirect from payment or default thanks page
        if (!empty($paymentResponse['data']['redirect'])) {
            return $paymentResponse;
        }

        $thanksId = $this->modx->getOption('ms3_order_redirect_thanks_id', null, 1);
        $redirect = $this->modx->makeUrl($thanksId, $ctx, ['msorder' => $msOrder->get('uuid')]);
        $paymentResponse['data']['redirect'] = $redirect;

        return $paymentResponse;
    }

    /**
     * Generate new order number
     *
     * Format: {date_format}{separator}{counter}
     * Example: 2501/1, 2501/2, etc.
     *
     * @return string Generated order number
     */
    public function getNewOrderNum(): string
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
     * Shorthand for success response
     */
    protected function success(string $message = '', array $data = []): array
    {
        return $this->ms3->utils->success($message, $data);
    }

    /**
     * Shorthand for error response
     */
    protected function error(?string $message = '', array $data = []): array
    {
        if ($message === null || $message === '') {
            $message = 'ms3_err_unknown';
        }
        return $this->ms3->utils->error($message, $data);
    }
}
