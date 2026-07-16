<?php

namespace MiniShop3\Controllers\Customer;

$autoload = dirname(__FILE__, 4) . '/vendor/autoload.php';

require_once($autoload);

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Customer\CustomerAddressManager;
use MiniShop3\Services\Customer\CustomerFieldManager;
use MiniShop3\Services\Customer\CustomerOrderResolver;
use MODX\Revolution\modX;

/**
 * Domain facade for the customer profile/session (not an HTTP controller).
 *
 * Lives under Controllers\ for MS2-style compatibility, but does not handle
 * FastRoute requests. HTTP entry points live under Controllers\Api\Web\*
 * (auth, profile, etc.). Registered as DI key `ms3_customer`; typically
 * reached via `$ms3->customer`.
 *
 * @see \MiniShop3\Controllers\Api\Web\CustomerProfileController
 * @see \MiniShop3\ServiceRegistry
 */
class Customer
{
    /** @var modX $modx */
    public $modx;
    /** @var MiniShop3 $ms3 */
    public $ms3;
    /** @var array $config */
    public $config = [];
    protected $token = '';
    protected $validationRules = [];
    protected $validationMessages = [];

    protected CustomerFieldManager $fieldManager;
    protected CustomerOrderResolver $orderResolver;

    /**
     * @param MiniShop3 $ms3
     * @param array $config
     */
    public function __construct(MiniShop3 $ms3, array $config = [])
    {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;

        $this->config = array_merge([

        ], $config);
        $this->modx->lexicon->load('minishop3:customer');

        $this->initializeServices();
    }

    /**
     * Initialize services from DI container
     */
    protected function initializeServices(): void
    {
        $this->fieldManager = $this->getServiceFromDI(
            'ms3_customer_field_manager',
            fn() => new CustomerFieldManager($this->modx, $this->ms3)
        );

        $this->orderResolver = $this->getServiceFromDI(
            'ms3_customer_order_resolver',
            fn() => new CustomerOrderResolver($this->modx, $this->ms3, $this->fieldManager)
        );
    }

    /**
     * Get service from DI container or use fallback factory
     */
    protected function getServiceFromDI(string $serviceKey, callable $fallbackFactory): mixed
    {
        if ($this->modx->services->has($serviceKey)) {
            return $this->modx->services->get($serviceKey);
        }

        return $fallbackFactory();
    }

    public function initialize(string $token = ''): bool
    {
        if (empty($token)) {
            return false;
        }
        $this->token = $token;

        if (!empty($_SESSION['ms3']['validation']['rules'])) {
            $this->validationRules = $_SESSION['ms3']['validation']['rules'];
        }
        if (!empty($_SESSION['ms3']['validation']['messages'])) {
            $this->validationMessages = $_SESSION['ms3']['validation']['messages'];
        }

        $this->fieldManager->setValidationRules($this->validationRules);
        $this->fieldManager->setValidationMessages($this->validationMessages);

        return true;
    }

    public function generateToken(): array
    {
        /** @var \MiniShop3\Services\TokenService $tokenService */
        $tokenService = $this->modx->services->get('ms3_token_service');

        $result = $tokenService->generateCustomerToken();
        if ($result['token'] === '') {
            return $this->error('ms3_err_token');
        }

        return $this->success('', [
            'token' => $result['token'],
            'lifetime' => $result['lifetime'],
        ]);
    }

    public function updateToken(string $token = ''): array
    {
        if (empty($token)) {
            return $this->generateToken();
        }

        /** @var \MiniShop3\Services\TokenService $tokenService */
        $tokenService = $this->modx->services->get('ms3_token_service');

        $result = $tokenService->updateCustomerToken($token);
        if ($result['token'] === '') {
            return $this->error('ms3_err_token');
        }

        return $this->success('', [
            'token' => $result['token'],
            'lifetime' => $result['lifetime'],
        ]);
    }

    public function registerValidation(array $rules = [], array $messages = []): void
    {
        $this->validationRules = [
            'first_name' => 'required|min:2',
            'last_name' => 'required|min:3',
            'email' => 'required|email',
            'phone' => 'required|min:10'
        ];

        $this->validationMessages = [
            'required' => 'Required',
            'email' => 'Invalid email',
            'min' => 'Minimum :min characters',
        ];

        if (!empty($rules)) {
            $this->validationRules = $rules;
        }

        if (!empty($messages)) {
            $this->validationMessages = $messages;
        }

        $_SESSION['ms3']['validation']['rules'] = $this->validationRules;
        $_SESSION['ms3']['validation']['messages'] = $this->validationMessages;

        $this->fieldManager->setValidationRules($this->validationRules);
        $this->fieldManager->setValidationMessages($this->validationMessages);
    }

    public function getFields(): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        $msCustomer = $this->getObject();

        return $msCustomer
            ? $this->success('', $msCustomer->toArray())
            : $this->success('', $this->modx->getFields(msCustomer::class));
    }

    public function getObject(): object|null
    {
        return $this->getByToken($this->token);
    }

    public function getByToken(string $token): ?msCustomer
    {
        if (empty($token)) {
            return null;
        }

        return $this->modx->getObject(msCustomer::class, ['token' => $token]) ?: null;
    }

    public function set(array $data = []): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        foreach ($data as $key => $value) {
            $this->add($key, $value);
        }

        return $this->getFields();
    }

    public function add(string $key, mixed $value): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }

        return $this->fieldManager->add($this, $this->token, $key, $value);
    }

    public function validate(string $key, mixed $value): mixed
    {
        return $this->fieldManager->validate($this, $key, $value);
    }

    public function create(array $customerData): msCustomer|null
    {
        return $this->fieldManager->create($this, $customerData);
    }

    /**
     * Add address for customer
     *
     * Delegates to CustomerAddressManager service.
     *
     * @param array $customerAddressData Address data with required fields: customer_id, city, street
     * @return bool True on success, false on failure or duplicate
     */
    public function addAddress(array $customerAddressData): bool
    {
        return $this->getAddressManager()->add($customerAddressData);
    }

    /**
     * Get all addresses for customer
     *
     * Delegates to CustomerAddressManager service.
     *
     * @param int $customer_id Customer ID
     * @return array Array of address records
     */
    public function getAddresses(int $customer_id = 0): array
    {
        return $this->getAddressManager()->getByCustomerId($customer_id);
    }

    /**
     * Get CustomerAddressManager service from DI
     *
     * @return CustomerAddressManager
     */
    protected function getAddressManager(): CustomerAddressManager
    {
        $service = $this->modx->services->get('ms3_customer_address_manager');

        if (!$service) {
            // Fallback: create directly if DI not available
            $service = new CustomerAddressManager($this->modx, $this->ms3);
        }

        return $service;
    }

    /**
     * Get or create customer for order
     *
     * Main method to get customer_id during checkout.
     * Search/creation sequence:
     * 1. Search by token
     * 2. Search by email from order data
     * 3. Create via RegisterService (if auto-registration enabled)
     * 4. Create without password (fallback for backward compatibility)
     *
     * @param array|null $orderData Order data (if null, will be fetched from order->get())
     * @return int Customer ID or 0 if not found/created
     */
    public function getOrCreate(?array $orderData = null): int
    {
        return $this->orderResolver->getOrCreate($this, $this->token, $orderData);
    }

    /**
     * Shorthand for ms3 error method
     *
     * @param string $message
     * @param array $data
     * @param array $placeholders
     *
     * @return array|string
     */
    protected function error(string $message = '', array $data = [], array $placeholders = []): array|string
    {
        return $this->ms3->utils->error($message, $data, $placeholders);
    }

    /**
     * Shorthand for ms3 success method
     *
     * @param string $message
     * @param array $data
     * @param array $placeholders
     *
     * @return array|string
     */
    protected function success(string $message = '', array $data = [], array $placeholders = []): array|string
    {
        return $this->ms3->utils->success($message, $data, $placeholders);
    }
}
