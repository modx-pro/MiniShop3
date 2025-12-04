<?php

namespace MiniShop3\Controllers\Customer;

$autoload = dirname(__FILE__, 4) . '/vendor/autoload.php';

require_once($autoload);

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerAddress;
use MODX\Revolution\modUser;
use MODX\Revolution\modUserProfile;
use MODX\Revolution\modUserSetting;
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

        // $response = $$this->ms3->utils->invokeEvent('msOnBeforeAddToOrder', [
        //            'key' => $key,
        //            'value' => $value,
        //            'order' => $this,
        //        ]);
        //        if (!$response['success']) {
        //            return $this->error($response['message']);
        //        }
        //        $value = $response['data']['value'];

        $response = $this->validate($key, $value);
        if (is_array($response)) {
            return $this->error($response[$key]);
        }

        $validated = $response;

        $msCustomer = $this->modx->getObject(msCustomer::class, [
            'token' => $this->token
        ]);
        if ($msCustomer) {
            $msCustomer->set($key, $validated);
        } else {
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

        // TODO Implement event after adding field

        //$response = $$this->ms3->utils->invokeEvent('msOnAddToCustomer', [
        //                    'key' => $key,
        //                    'value' => $validated,
        //                    'customer' => $this,
//                                'mode' => 'new'
        //                ]);
        //                if (!$response['success']) {
        //                    return $this->error($response['message']);
        //                }
        //                $validated = $response['data']['value'];

        return ($validated === false)
            ? $this->error('', [$key => $value])
            : $this->success('', [$key => $validated]);
    }

    public function validate(string $key, mixed $value): mixed
    {
        $validator = new Validator();

        $validation = $validator->validate(
            [$key => $value],
            [$key => $this->validationRules[$key]],
            $this->validationMessages
        );

        $validation->validate();

        if ($validation->fails()) {
            // handling errors
            $errors = $validation->errors();
            return $errors->firstOfAll();
        } else {
            return $value;
        }

        // $eventParams = [
        //            'key' => $key,
        //            'value' => $value,
        //            'customer' => $this,
        //        ];
        //        $response = $this->invokeEvent('msOnBeforeValidateCustomerValue', $eventParams);
        //        $value = $response['data']['value'];

        //$eventParams = [
        //            'key' => $key,
        //            'value' => $value,
        //            'customer' => $this,
        //        ];
        //        $response = $this->invokeEvent('msOnValidateCustomerValue', $eventParams);
        //        return $response['data']['value'];

        return $value;
    }

    /**
     * Get customer ID for order (legacy method)
     *
     * @deprecated Use getOrCreate() instead
     * @return int Customer ID or 0 if not found/created
     */
    public function getId(): int
    {
        return $this->getOrCreate();
    }

    public function create(array $customerData): msCustomer|null
    {
        $msCustomer = $this->modx->newObject(msCustomer::class, $customerData);
        $save = $msCustomer->save();
        if (!$save) {
            return null;
        }
        return $msCustomer;
    }

    public function addAddress(array $customerAddressData): bool
    {
        if (empty($customerAddressData['customer_id'])) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[Customer::addAddress] customer_id is required');
            return false;
        }

        if (empty($customerAddressData['city'])) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[Customer::addAddress] city is required');
            return false;
        }

        if (empty($customerAddressData['street'])) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[Customer::addAddress] street is required');
            return false;
        }

        if (empty($customerAddressData['name'])) {
            $nameParts = array_filter([
                $customerAddressData['city'] ?? '',
                $customerAddressData['street'] ?? '',
                $customerAddressData['building'] ?? '',
            ]);
            $customerAddressData['name'] = implode(', ', $nameParts);
        }

        $addressHash = $this->generateAddressHash($customerAddressData);
        $customerAddressData['hash'] = $addressHash;

        $isExists = $this->modx->getCount(msCustomerAddress::class, [
            'customer_id' => $customerAddressData['customer_id'],
            'hash' => $addressHash,
        ]);

        if (!empty($isExists)) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_INFO, '[Customer::addAddress] Address already exists for customer #' . $customerAddressData['customer_id']);
            return false;
        }

        if (empty($customerAddressData['createdon'])) {
            $customerAddressData['createdon'] = date('Y-m-d H:i:s');
        }

        $msCustomerAddress = $this->modx->newObject(msCustomerAddress::class, $customerAddressData);

        if (!$msCustomerAddress->save()) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[Customer::addAddress] Failed to save address');
            return false;
        }

        $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_INFO, '[Customer::addAddress] Created address #' . $msCustomerAddress->get('id') . ' for customer #' . $customerAddressData['customer_id']);

        return true;
    }

    /**
     * Generate address hash for duplicate detection
     *
     * @param array $data Address data
     * @return string MD5 hash of address
     */
    protected function generateAddressHash(array $data): string
    {
        $string = implode('|', [
            $data['city'] ?? '',
            $data['street'] ?? '',
            $data['building'] ?? '',
            $data['room'] ?? '',
        ]);

        return md5(mb_strtolower($string));
    }

    public function getAddresses(int $customer_id = 0): bool|array
    {
        $q = $this->modx->newQuery(msCustomerAddress::class);
        $q->where([
            'customer_id' => $customer_id,
        ]);
        $fields = $this->modx->getSelectColumns(msCustomerAddress::class, 'msCustomerAddress');
        $q->select($fields);
        $q->prepare();
        $q->stmt->execute();
        return $q->stmt->fetchAll(\PDO::FETCH_ASSOC);
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

        $msCustomer = $this->getObject();

        if (empty($msCustomer)) {
            if ($orderData === null) {
                $orderResponse = $this->ms3->order->get();
                $orderData = $orderResponse['data']['order'] ?? [];
            }

            $email = $orderData['address_email'] ?? '';

            if (!empty($email)) {
                $msCustomer = $this->findByEmail($email);

                if ($msCustomer) {
                    $msCustomer->set('token', $this->token);
                    $msCustomer->save();
                }
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
        if (empty($email)) {
            return null;
        }

        return $this->modx->getObject(msCustomer::class, ['email' => $email]);
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

                    if ($autoLogin) {
                        $_SESSION['ms3']['customer_id'] = $msCustomer->id;
                        $_SESSION['ms3']['customer_token'] = $msCustomer->get('token');
                    }
                } else {
                    $msCustomer = $this->findByEmail($email);

                    if ($msCustomer) {
                        $msCustomer->set('token', $this->token);
                        $msCustomer->save();

                        if ($autoLogin) {
                            $_SESSION['ms3']['customer_id'] = $msCustomer->id;
                            $_SESSION['ms3']['customer_token'] = $msCustomer->get('token');
                        }
                    }
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

            if ($msCustomer && $autoLogin) {
                $_SESSION['ms3']['customer_id'] = $msCustomer->id;
                $_SESSION['ms3']['customer_token'] = $msCustomer->get('token');
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
