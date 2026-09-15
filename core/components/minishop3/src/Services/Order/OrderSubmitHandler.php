<?php

namespace MiniShop3\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msPayment;
use MiniShop3\Services\Inventory\InventoryException;
use MiniShop3\Services\Inventory\OrderInventoryCoordinator;
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
    protected OrderNumberGenerator $numberGenerator;
    protected ?OrderInventoryCoordinator $inventoryCoordinator = null;

    public function __construct(
        modX $modx,
        MiniShop3 $ms3,
        OrderDraftManager $draftManager,
        OrderCostCalculator $costCalculator,
        OrderFieldManager $fieldManager,
        OrderAddressManager $addressManager,
        OrderUserResolver $userResolver,
        ?OrderNumberGenerator $numberGenerator = null,
        ?OrderInventoryCoordinator $inventoryCoordinator = null
    ) {
        $this->modx = $modx;
        $this->ms3 = $ms3;
        $this->draftManager = $draftManager;
        $this->costCalculator = $costCalculator;
        $this->fieldManager = $fieldManager;
        $this->addressManager = $addressManager;
        $this->userResolver = $userResolver;
        $this->numberGenerator = $numberGenerator ?? new OrderNumberGenerator($modx);
        $this->inventoryCoordinator = $inventoryCoordinator;
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

        /** @var \MiniShop3\Services\Delivery\DeliveryService $deliveryService */
        $deliveryService = $this->modx->services->get('ms3_delivery_service');
        $pairError = $deliveryService->getDeliveryPaymentPairError(
            (int) $orderData['delivery_id'],
            (int) $orderData['payment_id']
        );
        if ($pairError !== null) {
            return $this->error($pairError, ['payment_id', 'delivery_id']);
        }

        // Check required fields for delivery
        $requiredResponse = $this->fieldManager->getDeliveryRequiredFields($orderData['delivery_id']);
        if (!$requiredResponse['success']) {
            return $this->error($requiredResponse['message'] ?? 'ms3_order_err_delivery');
        }

        $requires = $requiredResponse['data']['requires'];
        $validatedFields = $orderData['properties']['_validated'] ?? [];
        $errors = [];
        foreach ($requires as $field => $rules) {
            if (empty($orderData[$field]) && empty($orderData['address_' . $field]) && !isset($validatedFields[$field])) {
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

        $costData = $costResponse['data'];
        $deliveryCost = $costData['delivery_cost'];
        $cartCost = $costData['cart_cost'];
        $totalCost = (float) $costData['cost'];

        // Check available stock before allocating a number (no holding order on reject)
        try {
            $this->inventory()?->assertOrderAvailable($draft);
        } catch (InventoryException $exception) {
            return $this->error($exception->getLexiconKey(), [], $exception->getPlaceholders());
        }

        // Allocate order number and persist costs under GET_LOCK (#380)
        try {
            $this->numberGenerator->runWithNextNumber(function (string $num) use (
                $draft,
                $customerId,
                $userId,
                $cartCost,
                $deliveryCost,
                $totalCost
            ): void {
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
                if (!$draft->save()) {
                    $this->modx->log(
                        modX::LOG_LEVEL_ERROR,
                        '[OrderSubmitHandler] Order save failed while assigning num=' . $num
                    );
                    throw new \RuntimeException('ms3_err_order_num_save');
                }
            });
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage());
        } catch (\Throwable $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[OrderSubmitHandler] ' . $e->getMessage());
            return $this->error('ms3_err_order_num_save');
        }

        $failAfterNumber = function (?string $message, array $data = [], array $placeholders = []) use ($draft): array {
            return $this->failAfterNumberAllocated($draft, $message, $data, $placeholders);
        };

        // Save address to customer's saved addresses if requested
        $properties = $draft->get('properties') ?? [];
        if (!empty($properties['save_address']) && !empty($customerId)) {
            $this->addressManager->saveToCustomerAddresses($customerId, $orderData);
        }

        // Extract custom validated fields before events
        $customFields = $properties['_validated'] ?? [];

        // Event: before create order
        $response = $this->ms3->utils->invokeEvent('msOnBeforeCreateOrder', [
            'handler' => $this,
            'msOrder' => $draft,
            'customFields' => $customFields,
        ]);

        if (!$response['success']) {
            return $failAfterNumber($response['message']);
        }

        // Event: on create order
        $response = $this->ms3->utils->invokeEvent('msOnCreateOrder', [
            'handler' => $this,
            'msOrder' => $draft,
            'customFields' => $customFields,
        ]);

        if (!$response['success']) {
            return $failAfterNumber($response['message']);
        }

        // Clean up _validated from properties
        if (!empty($customFields)) {
            $properties = $draft->get('properties') ?? [];
            unset($properties['_validated']);
            $draft->set('properties', $properties);
            $draft->save();
        }

        // Store order in session
        if (empty($_SESSION['ms3']['orders'])) {
            $_SESSION['ms3']['orders'] = [];
        }
        $_SESSION['ms3']['orders'][] = $draft->get('id');

        try {
            $this->inventory()?->assertOrderAvailable($draft);
        } catch (InventoryException $exception) {
            return $failAfterNumber(
                $exception->getLexiconKey(),
                [],
                $exception->getPlaceholders()
            );
        }

        $statusNew = (int) $this->modx->getOption('ms3_status_new', null, 2) ?: 2;
        /** @var OrderStatusService $orderStatus */
        $orderStatus = $this->modx->services->get('ms3_order_status');
        $statusResponse = $orderStatus->change($draft->get('id'), $statusNew);

        if ($statusResponse !== true) {
            return $failAfterNumber(
                is_string($statusResponse) ? $statusResponse : 'ms3_err_unknown',
                ['msorder' => $draft->get('uuid')]
            );
        }

        $msOrder = $this->modx->getObject(msOrder::class, ['id' => $draft->get('id')]);
        if (!$msOrder instanceof msOrder) {
            $this->abortInventoryHold($draft);

            return $this->error('ms3_err_order_load');
        }

        $paymentResponse = $msPayment->send($msOrder);
        if (!is_array($paymentResponse) || empty($paymentResponse['success'])) {
            $this->abortInventoryHold($msOrder);

            return $this->error(
                is_array($paymentResponse) ? ($paymentResponse['message'] ?? null) : null
            );
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
     * Peek next order number (legacy / plugin API).
     *
     * @deprecated Prefer OrderNumberGenerator::runWithNextNumber() so allocate and save share one lock.
     */
    public function getNewOrderNum(): string
    {
        $this->modx->log(
            modX::LOG_LEVEL_WARN,
            '[OrderSubmitHandler] getNewOrderNum() is deprecated; use runWithNextNumber() for atomic allocate+save'
        );

        return $this->numberGenerator->generate();
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
    protected function error(?string $message = '', array $data = [], array $placeholders = []): array
    {
        if ($message === null || $message === '') {
            $message = 'ms3_err_unknown';
        }
        return $this->ms3->utils->error($message, $data, $placeholders);
    }

    /**
     * After a number is assigned, any failure before New must drop the number
     * so the draft is not a holding order without a reserve.
     */
    protected function failAfterNumberAllocated(
        msOrder $draft,
        ?string $message,
        array $data = [],
        array $placeholders = []
    ): array {
        $this->revertAllocatedNumber($draft);

        return $this->error($message, $data, $placeholders);
    }

    protected function revertAllocatedNumber(msOrder $draft): void
    {
        if (!OrderInventoryCoordinator::isInventoryEnabled($this->modx)) {
            return;
        }
        $draft->set('num', null);
        $draft->save();
    }

    /**
     * Drop an uncommitted hold after payment send() failure.
     * When enforcement is on, cancel the order so it is not left as New without a reserve.
     *
     * @return bool false when stock may still be held
     */
    protected function abortInventoryHold(msOrder $msOrder): bool
    {
        if (!OrderInventoryCoordinator::isInventoryEnabled($this->modx)) {
            return true;
        }
        try {
            $canceledId = (int) $this->modx->getOption('ms3_status_canceled', null, 5) ?: 5;
            /** @var OrderStatusService $orderStatus */
            $orderStatus = $this->modx->services->get('ms3_order_status');
            $result = $orderStatus->change((int) $msOrder->get('id'), $canceledId);
            if ($result === true) {
                return true;
            }
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[OrderSubmitHandler] cancel after payment failure: ' . $result
            );
        } catch (\Throwable $exception) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[OrderSubmitHandler] cancel after payment failure: ' . $exception->getMessage()
            );
        }

        $released = false;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $this->inventory()?->releaseOrder($msOrder);
                $released = true;
                break;
            } catch (\Throwable $exception) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[OrderSubmitHandler] inventory release after payment failure: ' . $exception->getMessage()
                );
            }
        }

        return $released;
    }

    protected function inventory(): ?OrderInventoryCoordinator
    {
        return $this->inventoryCoordinator;
    }
}
