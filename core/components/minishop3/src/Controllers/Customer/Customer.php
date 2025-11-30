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
            'required' => 'Обязательно для заполнения',
            'email' => 'Не является email',
            'min' => 'Минимум :min символов',
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

        //TODO Реализовать событие ПередДобавлениемПоля

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

            // TODO как правильно определить текущего системного пользователя, если тот авторизован?
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

        //TODO Реализовать событие ПослеДобавлениемПоля

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

        // TODO валидировать наличие $key в модели msCustomer + разрешение на запись
        //TODO реализовать событие ДоВалидации

        // $eventParams = [
        //            'key' => $key,
        //            'value' => $value,
        //            'customer' => $this,
        //        ];
        //        $response = $this->invokeEvent('msOnBeforeValidateCustomerValue', $eventParams);
        //        $value = $response['data']['value'];

        // TODO валидировать $value

        // TODO реализовать событие ПослеВалидации

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
     * Returns id for current customer. If customer is not exists, registers him and returns id.
     *
     * @return integer $id
     */
    /**
     * Получить ID клиента для заказа (legacy метод)
     *
     * @deprecated Используйте getOrCreate() вместо этого метода
     * @return int ID клиента или 0 если не удалось найти/создать
     */
    public function getId(): int
    {
        // Делегируем вызов новому методу getOrCreate()
        return $this->getOrCreate();
    }

    public function create(array $customerData): msCustomer|null
    {
        //TODO  event msOnBeforeCreateCustomer
        $msCustomer = $this->modx->newObject(msCustomer::class, $customerData);
        $save = $msCustomer->save();
        if (!$save) {
            return null;
        }
        //TODO  event msOnCreateCustomer
        return $msCustomer;
    }

    public function addAddress(array $customerAddressData): bool
    {
        // Валидация обязательных полей
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

        // Генерация имени адреса (если не передано)
        if (empty($customerAddressData['name'])) {
            $nameParts = array_filter([
                $customerAddressData['city'] ?? '',
                $customerAddressData['street'] ?? '',
                $customerAddressData['building'] ?? '',
            ]);
            $customerAddressData['name'] = implode(', ', $nameParts);
        }

        // Генерация хеша для определения дубликатов
        $addressHash = $this->generateAddressHash($customerAddressData);
        $customerAddressData['hash'] = $addressHash;

        // Проверка дубликата по хешу и customer_id
        $isExists = $this->modx->getCount(msCustomerAddress::class, [
            'customer_id' => $customerAddressData['customer_id'],
            'hash' => $addressHash,
        ]);

        if (!empty($isExists)) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_INFO, '[Customer::addAddress] Address already exists for customer #' . $customerAddressData['customer_id']);
            return false;
        }

        // Добавление timestamp
        if (empty($customerAddressData['createdon'])) {
            $customerAddressData['createdon'] = date('Y-m-d H:i:s');
        }

        // Создание адреса
        $msCustomerAddress = $this->modx->newObject(msCustomerAddress::class, $customerAddressData);

        if (!$msCustomerAddress->save()) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[Customer::addAddress] Failed to save address');
            return false;
        }

        $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_INFO, '[Customer::addAddress] Created address #' . $msCustomerAddress->get('id') . ' for customer #' . $customerAddressData['customer_id']);

        return true;
    }

    /**
     * Генерация хеша адреса для определения дубликатов
     *
     * @param array $data Данные адреса
     * @return string MD5 хеш адреса
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
     * Получить или создать клиента для заказа
     *
     * Основной метод для получения customer_id при оформлении заказа.
     * Последовательность поиска/создания:
     * 1. Поиск по токену
     * 2. Поиск по email из данных заказа
     * 3. Создание через RegisterService (если включена автоматическая регистрация)
     * 4. Создание без пароля (fallback для обратной совместимости)
     *
     * @param array|null $orderData Данные заказа (если null, будут получены из order->get())
     * @return int ID клиента или 0 если не удалось найти/создать
     */
    public function getOrCreate(?array $orderData = null): int
    {
        $this->modx->log(
            modX::LOG_LEVEL_ERROR,
            '[Customer::getOrCreate] ⏯️ Starting customer retrieval/creation process. Token: ' . substr($this->token ?? 'NONE', 0, 16) . '...'
        );

        $msCustomer = null;

        // Событие перед получением клиента (позволяет плагинам переопределить логику)
        $response = $this->ms3->utils->invokeEvent('msOnBeforeGetOrderCustomer', [
            'controller' => $this->ms3->order,
            'msCustomer' => $msCustomer,
        ]);
        if (!$response['success']) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[Customer::getOrCreate] ❌ Event msOnBeforeGetOrderCustomer failed: ' . $response['message']);
            return 0;
        }

        // 1. Поиск клиента по токену
        $this->modx->log(
            modX::LOG_LEVEL_ERROR,
            '[Customer::getOrCreate] Step 1: Searching customer by token...'
        );

        $msCustomer = $this->getObject();

        if ($msCustomer) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[Customer::getOrCreate] ✅ Found existing customer #{$msCustomer->id} by token. Email: {$msCustomer->get('email')}"
            );
        }

        // 2. Если не найден по токену - пытаемся найти или создать
        if (empty($msCustomer)) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[Customer::getOrCreate] Step 2: Customer not found by token, proceeding to search/create...'
            );

            // Получаем данные заказа
            if ($orderData === null) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[Customer::getOrCreate] Order data not provided, fetching from order->get()...'
                );
                $orderResponse = $this->ms3->order->get();
                $orderData = $orderResponse['data']['order'] ?? [];
            }

            $email = $orderData['address_email'] ?? '';

            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[Customer::getOrCreate] Order email: ' . ($email ?: 'EMPTY')
            );

            // 3. Поиск клиента по email
            if (!empty($email)) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[Customer::getOrCreate] Step 3: Searching customer by email: {$email}..."
                );

                $msCustomer = $this->findByEmail($email);

                if ($msCustomer) {
                    // Обновляем токен существующего клиента
                    $oldToken = $msCustomer->get('token');
                    $msCustomer->set('token', $this->token);
                    $msCustomer->save();

                    $this->modx->log(
                        modX::LOG_LEVEL_ERROR,
                        "[Customer::getOrCreate] ✅ Found existing customer #{$msCustomer->id} by email ({$email}), updated token: {$oldToken} → {$this->token}"
                    );

                    $this->modx->log(
                        modX::LOG_LEVEL_ERROR,
                        "[Customer::getOrCreate] ⚠️ SECURITY: Existing customer found by email - auto-login SKIPPED (requires password authentication)"
                    );
                } else {
                    $this->modx->log(
                        modX::LOG_LEVEL_ERROR,
                        "[Customer::getOrCreate] Customer not found by email {$email}"
                    );
                }
            }

            // 4. Создание нового клиента
            if (empty($msCustomer)) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[Customer::getOrCreate] Step 4: Creating new customer via createFromOrderData()...'
                );

                $msCustomer = $this->createFromOrderData($orderData);
            }
        }

        // Событие после получения клиента
        $response = $this->ms3->utils->invokeEvent('msOnGetOrderCustomer', [
            'controller' => $this->ms3->order,
            'msCustomer' => $msCustomer,
        ]);
        if (!$response['success']) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[Customer::getOrCreate] ❌ Event msOnGetOrderCustomer failed: ' . $response['message']);
            return 0;
        }

        // Финальный лог результата
        if (!empty($msCustomer)) {
            $customerId = (int)$msCustomer->get('id');
            $isAuthorized = !empty($_SESSION['ms3']['customer_id']);

            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[Customer::getOrCreate] ✅ Process completed. Customer ID: {$customerId}, Email: {$msCustomer->get('email')}, Authorized: " . ($isAuthorized ? 'YES' : 'NO')
            );

            return $customerId;
        }

        $this->modx->log(
            modX::LOG_LEVEL_ERROR,
            '[Customer::getOrCreate] ❌ Process failed. No customer found or created.'
        );

        return 0;
    }

    /**
     * Поиск клиента по email
     *
     * @param string $email Email клиента
     * @return msCustomer|null Объект клиента или null
     */
    protected function findByEmail(string $email): ?msCustomer
    {
        if (empty($email)) {
            return null;
        }

        return $this->modx->getObject(msCustomer::class, ['email' => $email]);
    }

    /**
     * Создание клиента из данных заказа
     *
     * Логика:
     * 1. Если включена автоматическая регистрация (ms3_customer_auto_register_on_order = true)
     *    → создаёт через RegisterService (с паролем, верификацией email)
     * 2. Fallback: создаёт без пароля (для обратной совместимости)
     *
     * @param array $orderData Данные заказа
     * @return msCustomer|null Созданный клиент или null
     */
    protected function createFromOrderData(array $orderData): ?msCustomer
    {
        $email = $orderData['address_email'] ?? '';

        // Логируем начало процесса
        $this->modx->log(
            modX::LOG_LEVEL_ERROR,
            '[Customer::createFromOrderData] Starting customer creation process. Email: ' . ($email ?: 'EMPTY')
        );

        if (empty($email)) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[Customer::createFromOrderData] ❌ Cannot create customer without email. Order data: ' . json_encode($orderData)
            );
            return null;
        }

        $msCustomer = null;
        $autoRegister = (bool)$this->modx->getOption('ms3_customer_auto_register_on_order', null, true);
        $autoLogin = (bool)$this->modx->getOption('ms3_customer_auto_login_on_order', null, true);

        $this->modx->log(
            modX::LOG_LEVEL_ERROR,
            "[Customer::createFromOrderData] Settings: auto_register={$autoRegister}, auto_login={$autoLogin}, email={$email}"
        );

        // Попытка создания через RegisterService (с паролем)
        if ($autoRegister) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[Customer::createFromOrderData] 🔄 Attempting registration via RegisterService for {$email}"
            );

            /** @var \MiniShop3\Services\Customer\RegisterService $registerService */
            $registerService = $this->modx->services->get('ms3_register_service');

            if (!$registerService) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[Customer::createFromOrderData] ❌ RegisterService not available in service container'
                );
            } else {
                $registerData = [
                    'first_name' => $orderData['address_first_name'] ?? '',
                    'last_name' => $orderData['address_last_name'] ?? '',
                    'phone' => $orderData['address_phone'] ?? '',
                    'email' => $email,
                    'token' => $this->token,
                    // GDPR consent (предполагаем согласие при оформлении заказа)
                    'privacy_accepted' => true,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ];

                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[Customer::createFromOrderData] Register data: ' . json_encode([
                        'email' => $registerData['email'],
                        'first_name' => $registerData['first_name'],
                        'last_name' => $registerData['last_name'],
                        'phone' => $registerData['phone'],
                        'has_token' => !empty($registerData['token']),
                    ])
                );

                $registerResult = $registerService->register($registerData);

                if ($registerResult['success']) {
                    $msCustomer = $registerResult['customer'];

                    $this->modx->log(
                        modX::LOG_LEVEL_ERROR,
                        "[Customer::createFromOrderData] ✅ Successfully auto-registered customer #{$msCustomer->id} ({$email}) via RegisterService"
                    );

                    // Автоматическая авторизация (если включена настройка)
                    if ($autoLogin) {
                        $_SESSION['ms3']['customer_id'] = $msCustomer->id;
                        $_SESSION['ms3']['customer_token'] = $msCustomer->get('token');

                        $this->modx->log(
                            modX::LOG_LEVEL_ERROR,
                            "[Customer::createFromOrderData] 🔐 Auto-logged in customer #{$msCustomer->id}. Session: customer_id={$msCustomer->id}, token=" . substr($msCustomer->get('token'), 0, 16) . '...'
                        );
                    } else {
                        $this->modx->log(
                            modX::LOG_LEVEL_ERROR,
                            "[Customer::createFromOrderData] ⏭️ Auto-login disabled (ms3_customer_auto_login_on_order=false)"
                        );
                    }
                } else {
                    // Если регистрация не удалась (например, email уже занят), пытаемся найти клиента
                    $this->modx->log(
                        modX::LOG_LEVEL_ERROR,
                        "[Customer::createFromOrderData] ⚠️ RegisterService failed: {$registerResult['message']}. Trying to find existing customer by email..."
                    );

                    $msCustomer = $this->findByEmail($email);

                    if ($msCustomer) {
                        $this->modx->log(
                            modX::LOG_LEVEL_ERROR,
                            "[Customer::createFromOrderData] 🔍 Found existing customer #{$msCustomer->id} by email {$email}"
                        );

                        $oldToken = $msCustomer->get('token');
                        $msCustomer->set('token', $this->token);
                        $msCustomer->save();

                        $this->modx->log(
                            modX::LOG_LEVEL_ERROR,
                            "[Customer::createFromOrderData] 🔄 Updated customer #{$msCustomer->id} token: {$oldToken} → {$this->token}"
                        );

                        // Автоматическая авторизация для существующего клиента (если включена настройка)
                        if ($autoLogin) {
                            $_SESSION['ms3']['customer_id'] = $msCustomer->id;
                            $_SESSION['ms3']['customer_token'] = $msCustomer->get('token');

                            $this->modx->log(
                                modX::LOG_LEVEL_ERROR,
                                "[Customer::createFromOrderData] 🔐 Auto-logged in existing customer #{$msCustomer->id}"
                            );
                        } else {
                            $this->modx->log(
                                modX::LOG_LEVEL_ERROR,
                                "[Customer::createFromOrderData] ⏭️ Auto-login disabled for existing customer"
                            );
                        }
                    } else {
                        $this->modx->log(
                            modX::LOG_LEVEL_ERROR,
                            "[Customer::createFromOrderData] ❌ Customer not found by email {$email} after RegisterService failure"
                        );
                    }
                }
            }
        } else {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[Customer::createFromOrderData] ⏭️ Auto-register disabled (ms3_customer_auto_register_on_order=false)'
            );
        }

        // Fallback: старый метод создания (без пароля, для обратной совместимости)
        if (empty($msCustomer)) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[Customer::createFromOrderData] 🔄 Using fallback method (create without password) for {$email}"
            );

            $customerData = [
                'first_name' => $orderData['address_first_name'] ?? '',
                'last_name' => $orderData['address_last_name'] ?? '',
                'phone' => $orderData['address_phone'] ?? '',
                'email' => $email,
                'token' => $this->token,
            ];

            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[Customer::createFromOrderData] Fallback customer data: ' . json_encode([
                    'email' => $customerData['email'],
                    'first_name' => $customerData['first_name'],
                    'last_name' => $customerData['last_name'],
                    'phone' => $customerData['phone'],
                    'has_token' => !empty($customerData['token']),
                ])
            );

            $msCustomer = $this->create($customerData);

            if ($msCustomer) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[Customer::createFromOrderData] ✅ Created customer #{$msCustomer->id} ({$email}) via fallback method (without password)"
                );

                // Автоматическая авторизация (если включена настройка)
                if ($autoLogin) {
                    $_SESSION['ms3']['customer_id'] = $msCustomer->id;
                    $_SESSION['ms3']['customer_token'] = $msCustomer->get('token');

                    $this->modx->log(
                        modX::LOG_LEVEL_ERROR,
                        "[Customer::createFromOrderData] 🔐 Auto-logged in customer #{$msCustomer->id} (fallback method). Session: customer_id={$msCustomer->id}"
                    );
                } else {
                    $this->modx->log(
                        modX::LOG_LEVEL_ERROR,
                        "[Customer::createFromOrderData] ⏭️ Auto-login disabled for fallback customer"
                    );
                }
            } else {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[Customer::createFromOrderData] ❌ Failed to create customer with email: {$email} via fallback method"
                );
            }
        }

        // Финальный лог результата
        if ($msCustomer) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[Customer::createFromOrderData] ✅ Process completed successfully. Customer ID: {$msCustomer->id}, Email: {$email}, Logged in: " . ($autoLogin ? 'YES' : 'NO')
            );
        } else {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[Customer::createFromOrderData] ❌ Process failed. No customer created for email: {$email}"
            );
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
