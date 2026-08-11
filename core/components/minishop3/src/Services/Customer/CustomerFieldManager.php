<?php

namespace MiniShop3\Services\Customer;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Validation\ValidationService;
use MODX\Revolution\modX;

/**
 * Customer Field Manager
 *
 * Manages customer field validation, add/update, and creation.
 */
class CustomerFieldManager
{
    protected modX $modx;
    protected MiniShop3 $ms3;

    protected array $validationRules = [];
    protected array $validationMessages = [];

    public function __construct(modX $modx, MiniShop3 $ms3)
    {
        $this->modx = $modx;
        $this->ms3 = $ms3;
    }

    public function setValidationRules(array $rules): void
    {
        $this->validationRules = $rules;
    }

    public function setValidationMessages(array $messages): void
    {
        $this->validationMessages = $messages;
    }

    /**
     * Add or update customer field
     */
    /**
     * @param object $customer Customer facade (event payload BC)
     */
    public function add(object $customer, string $token, string $key, mixed $value): array
    {
        if (empty($key)) {
            return $this->ms3->utils->error('ms3_customer_key_empty');
        }

        $response = $this->ms3->utils->invokeEvent('msOnBeforeAddToCustomer', [
            'key' => $key,
            'value' => $value,
            'customer' => $customer,
        ]);
        if (!$response['success']) {
            return $this->ms3->utils->error($response['message']);
        }
        $value = $response['data']['value'];

        $response = $this->validate($customer, $key, $value);
        if (is_array($response)) {
            return $this->ms3->utils->error($response[$key]);
        }

        $validated = $response;

        $isNew = false;
        $msCustomer = $this->modx->getObject(msCustomer::class, [
            'token' => $token,
        ]);
        if ($msCustomer) {
            $msCustomer->set($key, $validated);
        } else {
            $isNew = true;
            $userId = 0;

            if ($this->modx->user->hasSessionContext($this->ms3->config['ctx'])) {
                $userId = $this->modx->user->get('id');
            }
            $msCustomer = $this->modx->newObject(msCustomer::class, [
                'token' => $token,
                $key => $validated,
                'user_id' => $userId,
            ]);
        }
        $msCustomer->save();

        $response = $this->ms3->utils->invokeEvent('msOnAddToCustomer', [
            'key' => $key,
            'value' => $validated,
            'customer' => $customer,
            'msCustomer' => $msCustomer,
            'isNew' => $isNew,
        ]);
        if (!$response['success']) {
            return $this->ms3->utils->error($response['message']);
        }

        return ($validated === false)
            ? $this->ms3->utils->error('', [$key => $value])
            : $this->ms3->utils->success('', [$key => $validated]);
    }

    /**
     * Validate customer field value
     *
     * @param object $customer Customer facade (event payload BC)
     * @return mixed Validated value or error array keyed by field
     */
    public function validate(object $customer, string $key, mixed $value): mixed
    {
        $response = $this->ms3->utils->invokeEvent('msOnBeforeValidateCustomerValue', [
            'key' => $key,
            'value' => $value,
            'customer' => $customer,
        ]);
        if (!$response['success']) {
            return [$key => $response['message']];
        }
        $value = $response['data']['value'];

        if (!empty($this->validationRules[$key])) {
            $validation = $this->getValidationService()->validate(
                [$key => $value],
                [$key => $this->validationRules[$key]],
                $this->validationMessages
            );

            if ($validation->fails()) {
                $errors = $validation->errors();

                $response = $this->ms3->utils->invokeEvent('msOnErrorValidateCustomerValue', [
                    'key' => $key,
                    'value' => $value,
                    'errors' => $errors->firstOfAll(),
                    'customer' => $customer,
                ]);

                if (!$response['success']) {
                    return [$key => $response['message']];
                }

                $data = $response['data'] ?? [];
                if (array_key_exists('errors', $data) && is_array($data['errors'])) {
                    return $data['errors'];
                }

                return $errors->firstOfAll();
            }
        }

        $response = $this->ms3->utils->invokeEvent('msOnValidateCustomerValue', [
            'key' => $key,
            'value' => $value,
            'customer' => $customer,
        ]);
        if (!$response['success']) {
            return [$key => $response['message']];
        }

        return $response['data']['value'];
    }

    /**
     * Create customer record
     */
    /**
     * @param object $customer Customer facade (event payload BC)
     */
    public function create(object $customer, array $customerData): ?msCustomer
    {
        $response = $this->ms3->utils->invokeEvent('msOnBeforeCreateCustomer', [
            'customerData' => $customerData,
            'customer' => $customer,
        ]);
        if (!$response['success']) {
            return null;
        }
        $customerData = $response['data']['customerData'];

        $msCustomer = $this->modx->newObject(msCustomer::class, $customerData);
        $save = $msCustomer->save();
        if (!$save) {
            return null;
        }

        $response = $this->ms3->utils->invokeEvent('msOnCreateCustomer', [
            'customerData' => $customerData,
            'msCustomer' => $msCustomer,
            'customer' => $customer,
        ]);
        if (!$response['success']) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                '[CustomerFieldManager::create] msOnCreateCustomer event failed: ' . $response['message']
            );
        }

        return $msCustomer;
    }

    /**
     * Resolve the canonical validation service from MODX DI.
     */
    protected function getValidationService(): ValidationService
    {
        $service = $this->modx->services->get('ms3_validation_service');

        return $service instanceof ValidationService ? $service : new ValidationService();
    }
}
