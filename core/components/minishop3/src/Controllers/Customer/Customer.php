<?php

namespace MiniShop3\Controllers\Customer;

$autoload = dirname(__FILE__, 4) . '/vendor/autoload.php';

require_once($autoload);

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Customer\AuthManager;
use MiniShop3\Services\Customer\CustomerAddressManager;
use MODX\Revolution\modX;

use Rakit\Validation\Validator;

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

    /**
     * Cart constructor.
     *
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
    }

    public function getFields(): array
    {
        if (empty($this->token)) {
            return $this->error('ms3_err_token');
        }
        $msCustomer = $this->modx->getObject(msCustomer::class, [
            'token' => $this->token,
        ]);
        if (!$msCustomer) {
            return $this->success('', $this->modx->getFields(msCustomer::class));
        }
        return $this->success('', $msCustomer->toArray());
    }

    public function getObject(): object|null
    {
        if (empty($this->token)) {
            return null;
        }
        $msCustomer = $this->modx->getObject(msCustomer::class, [
            'token' => $this->token,
        ]);
        if (!$msCustomer) {
            return null;
        }
        return $msCustomer;
    }

    public function getByToken(string $token): ?msCustomer
    {
        if (empty($token)) {
            return null;
        }
        $msCustomer = $this->modx->getObject(msCustomer::class, [
            'token' => $token,
        ]);
        if (!$msCustomer) {
            return null;
        }
        return $msCustomer;
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

        if (empty($key)) {
            return $this->error('ms3_customer_key_empty');
        }

        $response = $this->ms3->utils->invokeEvent('msOnBeforeAddToCustomer', [
            'key' => $key,
            'value' => $value,
            'customer' => $this,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $value = $response['data']['value'];

        $response = $this->validate($key, $value);
        if (is_array($response)) {
            return $this->error($response[$key]);
        }

        $validated = $response;

        $isNew = false;
        $msCustomer = $this->modx->getObject(msCustomer::class, [
            'token' => $this->token
        ]);
        if ($msCustomer) {
            $msCustomer->set($key, $validated);
        } else {
            $isNew = true;
            $userId = 0;

            // TODO how to correctly determine current system user if authenticated?
            if ($this->modx->user->hasSessionContext($this->ms3->config['ctx'])) {
                $userId = $this->modx->user->get('id');
            }
            $msCustomer = $this->modx->newObject(msCustomer::class, [
                'token' => $this->token,
                $key => $validated,
                'user_id' => $userId
            ]);
        }
        $msCustomer->save();

        $response = $this->ms3->utils->invokeEvent('msOnAddToCustomer', [
            'key' => $key,
            'value' => $validated,
            'customer' => $this,
            'msCustomer' => $msCustomer,
            'isNew' => $isNew,
        ]);
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        return ($validated === false)
            ? $this->error('', [$key => $value])
            : $this->success('', [$key => $validated]);
    }

    public function validate(string $key, mixed $value): mixed
    {
        // Allow plugins to modify value before validation
        $response = $this->ms3->utils->invokeEvent('msOnBeforeValidateCustomerValue', [
            'key' => $key,
            'value' => $value,
            'customer' => $this,
        ]);
        if (!$response['success']) {
            return [$key => $response['message']];
        }
        $value = $response['data']['value'];

        // Standard validation
        if (!empty($this->validationRules[$key])) {
            $validator = new Validator();

            $validation = $validator->validate(
                [$key => $value],
                [$key => $this->validationRules[$key]],
                $this->validationMessages
            );

            $validation->validate();

            if ($validation->fails()) {
                $errors = $validation->errors();

                // Allow plugins to handle validation errors.
                // Contract (Utils::invokeEvent merges returnedValues into data):
                // - success=false → caller gets [$key => message] from the wrapper.
                // - success=true and data.errors is set (array, may be empty) → return that shape;
                //   omitted key keeps standard $errors->firstOfAll().
                $response = $this->ms3->utils->invokeEvent('msOnErrorValidateCustomerValue', [
                    'key' => $key,
                    'value' => $value,
                    'errors' => $errors->firstOfAll(),
                    'customer' => $this,
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

        // Allow plugins to modify validated value
        $response = $this->ms3->utils->invokeEvent('msOnValidateCustomerValue', [
            'key' => $key,
            'value' => $value,
            'customer' => $this,
        ]);
        if (!$response['success']) {
            return [$key => $response['message']];
        }

        return $response['data']['value'];
    }

    public function create(array $customerData): msCustomer|null
    {
        // Allow plugins to modify data before creation
        $response = $this->ms3->utils->invokeEvent('msOnBeforeCreateCustomer', [
            'customerData' => $customerData,
            'customer' => $this,
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

        // Allow plugins to act after customer creation
        $response = $this->ms3->utils->invokeEvent('msOnCreateCustomer', [
            'customerData' => $customerData,
            'msCustomer' => $msCustomer,
            'customer' => $this,
        ]);
        if (!$response['success']) {
            // Customer already created, but plugins can log/handle errors
            $this->modx->log(modX::LOG_LEVEL_WARN, '[Customer::create] msOnCreateCustomer event failed: ' . $response['message']);
        }

        return $msCustomer;
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
        $msCustomer = null;

        $response = $this->ms3->utils->invokeEvent('msOnBeforeGetOrderCustomer', [
            'controller' => $this->ms3->order,
            'msCustomer' => $msCustomer,
        ]);
        if (!$response['success']) {
            return 0;
        }

        if (!empty($response['data']['msCustomer']) && $response['data']['msCustomer'] instanceof msCustomer) {
            $msCustomer = $response['data']['msCustomer'];
        } else {
            $msCustomer = $this->getObject();
        }

        if (empty($msCustomer)) {
            if ($orderData === null) {
                $orderResponse = $this->ms3->order->get();
                $orderData = $orderResponse['data']['order'] ?? [];
            }

            $email = $orderData['address_email'] ?? '';

            if (!empty($email)) {
                // Link order to existing account by email only.
                // Never overwrite msCustomer.token — that hands the guest session the victim's account.
                $msCustomer = $this->findByEmail($email);
            }

            if (empty($msCustomer)) {
                $msCustomer = $this->createFromOrderData($orderData);
            }
        }

        $response = $this->ms3->utils->invokeEvent('msOnGetOrderCustomer', [
            'controller' => $this->ms3->order,
            'msCustomer' => $msCustomer,
        ]);
        if (!$response['success']) {
            return 0;
        }

        if (!empty($msCustomer)) {
            return (int)$msCustomer->get('id');
        }

        return 0;
    }

    /**
     * Find customer by email
     *
     * @param string $email Customer email
     * @return msCustomer|null Customer object or null
     */
    protected function findByEmail(string $email): ?msCustomer
    {
        $normalized = AuthManager::normalizeEmail($email);
        if ($normalized === '') {
            return null;
        }

        /** @var msCustomer|null $customer */
        $customer = $this->modx->getObject(msCustomer::class, ['email' => $normalized]);
        if ($customer) {
            return $customer;
        }

        $raw = trim($email);
        if ($raw !== '' && $raw !== $normalized) {
            return $this->modx->getObject(msCustomer::class, ['email' => $raw]) ?: null;
        }

        return null;
    }

    /**
     * Create customer from order data
     *
     * Logic:
     * 1. If auto-registration enabled (ms3_customer_auto_register_on_order = true)
     *    → creates via RegisterService (with password, email verification)
     * 2. Fallback: creates without password (for backward compatibility)
     *
     * @param array $orderData Order data
     * @return msCustomer|null Created customer or null
     */
    protected function createFromOrderData(array $orderData): ?msCustomer
    {
        $email = $orderData['address_email'] ?? '';

        if (empty($email)) {
            return null;
        }

        $msCustomer = null;
        $autoRegister = (bool)$this->modx->getOption('ms3_customer_auto_register_on_order', null, true);
        $autoLogin = (bool)$this->modx->getOption('ms3_customer_auto_login_on_order', null, true);

        if ($autoRegister) {
            /** @var \MiniShop3\Services\Customer\RegisterService $registerService */
            $registerService = $this->modx->services->get('ms3_register_service');

            if ($registerService) {
                $registerData = [
                    'first_name' => $orderData['address_first_name'] ?? '',
                    'last_name' => $orderData['address_last_name'] ?? '',
                    'phone' => $orderData['address_phone'] ?? '',
                    'email' => $email,
                    'token' => $this->token,
                    'privacy_accepted' => true,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ];

                $registerResult = $registerService->register($registerData);

                if ($registerResult['success']) {
                    $msCustomer = $registerResult['customer'];
                } else {
                    // Email already registered: attach order only — no token/session takeover
                    $msCustomer = $this->findByEmail($email);
                }
            }
        }

        if (empty($msCustomer)) {
            $customerData = [
                'first_name' => $orderData['address_first_name'] ?? '',
                'last_name' => $orderData['address_last_name'] ?? '',
                'phone' => $orderData['address_phone'] ?? '',
                'email' => $email,
                'token' => $this->token,
            ];

            $msCustomer = $this->create($customerData);
        }

        if ($msCustomer && $autoLogin) {
            /** @var AuthManager $authManager */
            $authManager = $this->modx->services->get('ms3_auth_manager');
            if (!$authManager->establishCustomerSession($msCustomer)) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[Customer] establishCustomerSession failed for customer #{$msCustomer->id}"
                );
            }
        }

        return $msCustomer;
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
