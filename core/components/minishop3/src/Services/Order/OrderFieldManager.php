<?php

namespace MiniShop3\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderLog;
use MiniShop3\Services\Order\OrderLogService;
use MiniShop3\Services\Validation\ValidationService;
use MODX\Revolution\modX;

/**
 * Order Field Manager
 *
 * Manages order field CRUD operations and validation.
 * Handles both order fields and address fields.
 */
class OrderFieldManager
{
    protected modX $modx;
    protected MiniShop3 $ms3;
    protected OrderDraftManager $draftManager;

    protected array $validationRules = [];
    protected array $validationMessages = [];
    protected ?array $deliveryValidationRules = null;
    protected ?OrderLogService $log = null;

    public function __construct(modX $modx, MiniShop3 $ms3, OrderDraftManager $draftManager)
    {
        $this->modx = $modx;
        $this->ms3 = $ms3;
        $this->draftManager = $draftManager;
    }

    /**
     * Set order log instance for change tracking
     */
    public function setLog(OrderLogService $log): void
    {
        $this->log = $log;
    }

    /**
     * Add or update order field
     *
     * @param msOrder|null $draft Draft order (null = field not saved)
     * @param array $orderData Current order data
     * @param string $key Field key
     * @param mixed $value Field value
     * @return array Response
     */
    public function add(?msOrder $draft, array $orderData, string $key, mixed $value = null): array
    {
        $response = $this->ms3->utils->invokeEvent('msOnBeforeAddToOrder', [
            'key' => $key,
            'value' => $value,
            'draft' => $draft,
        ]);

        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $value = $response['data']['value'];

        // Empty value = remove field
        if ($value === null || $value === '') {
            if ($draft) {
                $this->remove($draft, $orderData, $key);
            }
            return $this->success('', [$key => null]);
        }

        // Validate field
        $validateResponse = $this->validate($orderData, $key, $value);

        if ($validateResponse['success']) {
            $validated = $validateResponse['data']['value'];

            $response = $this->ms3->utils->invokeEvent('msOnAddToOrder', [
                'key' => $key,
                'value' => $validated,
                'draft' => $draft,
            ]);

            if (!$response['success']) {
                return $this->error($response['message']);
            }

            $validated = $response['data']['value'];

            // Only save to draft if it exists
            if ($draft) {
                // Get old value for logging
                $orderKey = $key;
                $addressFields = $draft->Address ? array_keys($draft->Address->toArray()) : [];
                if (in_array($key, $addressFields)) {
                    $orderKey = 'address_' . $key;
                }
                $oldValue = $orderData[$orderKey] ?? null;

                // Update draft
                $this->draftManager->updateField($draft, $key, $validated);

                // Log field change if value actually changed
                if ($oldValue != $validated && $this->log) {
                    $isAddressField = in_array($key, $addressFields);
                    $action = $isAddressField ? msOrderLog::ACTION_ADDRESS : msOrderLog::ACTION_FIELD;

                    $this->log->addEntry(
                        $draft->get('id'),
                        $action,
                        [
                            'fields' => [
                                $key => ['old' => $oldValue, 'new' => $validated],
                            ],
                        ]
                    );
                }
            }

            return $this->success('', [$key => $validated]);
        }

        // Validation failed
        if ($draft) {
            $this->draftManager->updateField($draft, $key);
        }

        return $this->error($validateResponse['data']['error'][$key], [$key => null]);
    }

    /**
     * Remove order field
     *
     * @param msOrder $draft Draft order
     * @param array $orderData Current order data
     * @param string $key Field key
     * @return bool True if field existed and was removed
     */
    public function remove(msOrder $draft, array $orderData, string $key): bool
    {
        $properties = $draft->get('properties') ?? [];
        $existsInValidated = isset($properties['_validated'][$key]);

        $exists = array_key_exists($key, $orderData)
            || array_key_exists('address_' . $key, $orderData)
            || $existsInValidated;

        if ($exists) {
            $response = $this->ms3->utils->invokeEvent('msOnBeforeRemoveFromOrder', [
                'key' => $key,
                'draft' => $draft,
            ]);

            if (!$response['success']) {
                return false;
            }

            $this->draftManager->updateField($draft, $key);

            $response = $this->ms3->utils->invokeEvent('msOnRemoveFromOrder', [
                'key' => $key,
                'draft' => $draft,
            ]);

            if (!$response['success']) {
                return false;
            }
        }

        return $exists;
    }

    /**
     * Validate order field value
     *
     * @param array $orderData Current order data
     * @param string $key Field key
     * @param mixed $value Field value
     * @return array Response with validated value or error
     */
    public function validate(array $orderData, string $key, mixed $value): array
    {
        // Default validation rules
        $this->validationRules = [
            'delivery_id' => 'required|numeric',
            'payment_id' => 'required|numeric',
        ];

        $this->validationMessages = [
            'required' => 'Required',
            'numeric' => 'Must be a number',
            'min' => 'Minimum :min characters',
            'email' => 'Invalid email'
        ];

        // Load delivery-specific validation rules
        if (!empty($orderData['delivery_id']) && empty($this->deliveryValidationRules)) {
            $response = $this->getDeliveryValidationRules($orderData['delivery_id']);
            if (!empty($response['success'])) {
                $this->deliveryValidationRules = $response['data']['validation_rules'];
                $this->validationRules = array_unique(
                    array_merge($this->validationRules, $this->deliveryValidationRules)
                );
            }
        }

        // Event: before validation
        $eventParams = [
            'key' => $key,
            'value' => $value,
            'orderData' => $orderData,
        ];
        $response = $this->ms3->utils->invokeEvent('msOnBeforeValidateOrderValue', $eventParams);
        $value = $response['data']['value'];

        // No validation rule for this field = pass through
        if (!isset($this->validationRules[$key])) {
            return $this->success('', ['value' => $response['data']['value']]);
        }

        // Run validation
        $validation = $this->getValidationService()->validate(
            [$key => $value],
            [$key => $this->validationRules[$key]],
            $this->validationMessages
        );

        if ($validation->fails()) {
            $errors = $validation->errors();
            $eventParams = [
                'key' => $key,
                'value' => $value,
                'error' => $errors->firstOfAll(),
            ];
            $response = $this->ms3->utils->invokeEvent('msOnErrorValidateOrderValue', $eventParams);

            if (!empty($response['data']['error'])) {
                return $this->error('', ['error' => $response['data']['error']]);
            }
        } else {
            $eventParams = [
                'key' => $key,
                'value' => $value,
            ];
            $response = $this->ms3->utils->invokeEvent('msOnValidateOrderValue', $eventParams);
        }

        if (in_array($key, ['payment_id', 'delivery_id'], true)) {
            $deliveryId = (int) ($key === 'delivery_id' ? $response['data']['value'] : ($orderData['delivery_id'] ?? 0));
            $paymentId = (int) ($key === 'payment_id' ? $response['data']['value'] : ($orderData['payment_id'] ?? 0));
            /** @var \MiniShop3\Services\Delivery\DeliveryService $deliveryService */
            $deliveryService = $this->modx->services->get('ms3_delivery_service');
            $pairError = $deliveryService->getDeliveryPaymentPairError($deliveryId, $paymentId);
            if ($pairError !== null) {
                return $this->error('', [
                    'error' => [
                        $key => $pairError,
                    ],
                ]);
            }
        }

        return $this->success('', ['value' => $response['data']['value']]);
    }

    /**
     * Get validation rules for delivery method
     */
    public function getDeliveryValidationRules(int $deliveryId): array
    {
        if (empty($deliveryId)) {
            return $this->error('ms3_order_delivery_id_nf');
        }

        $q = $this->modx->newQuery(msDelivery::class);
        $q->where([
            'id' => $deliveryId,
            'active' => 1
        ]);
        $q->select('validation_rules');
        $q->prepare();
        $q->stmt->execute();
        $rules = $q->stmt->fetch(\PDO::FETCH_COLUMN);

        if (empty($rules)) {
            return $this->success('', ['validation_rules' => []]);
        }

        $rules = json_decode($rules, true);

        if (!is_array($rules)) {
            return $this->success('', ['validation_rules' => []]);
        }

        return $this->success('', ['validation_rules' => $rules]);
    }

    /**
     * Get required fields for delivery method
     */
    public function getDeliveryRequiredFields(int $deliveryId): array
    {
        $response = $this->getDeliveryValidationRules($deliveryId);

        if (!$response['success']) {
            return $this->error($response['message'] ?? 'ms3_order_err_delivery', ['delivery']);
        }

        $requires = array_filter($response['data']['validation_rules'], function ($rules) {
            return in_array('required', array_map('trim', explode("|", $rules)));
        }, ARRAY_FILTER_USE_BOTH);

        return $this->success('', ['requires' => $requires]);
    }

    /**
     * Set custom validation rules (from session or external source)
     */
    public function setValidationRules(array $rules): void
    {
        $this->validationRules = array_merge($this->validationRules, $rules);
    }

    /**
     * Set custom validation messages
     */
    public function setValidationMessages(array $messages): void
    {
        $this->validationMessages = array_merge($this->validationMessages, $messages);
    }

    /**
     * Resolve the canonical validation service from MODX DI.
     */
    protected function getValidationService(): ValidationService
    {
        $service = $this->modx->services->get('ms3_validation_service');

        return $service instanceof ValidationService ? $service : new ValidationService();
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
    protected function error(string $message = '', array $data = []): array
    {
        return $this->ms3->utils->error($message ?: 'ms3_err_unknown', $data);
    }
}
