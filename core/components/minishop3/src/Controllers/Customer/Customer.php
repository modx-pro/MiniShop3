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
        $msCustomer = null;

        // Событие перед получением клиента (позволяет плагинам переопределить логику)
        $response = $this->ms3->utils->invokeEvent('msOnBeforeGetOrderCustomer', [
            'controller' => $this->ms3->order,
            'msCustomer' => $msCustomer,
        ]);
        if (!$response['success']) {
            return 0;
        }

        // 1. Поиск клиента по токену
        $msCustomer = $this->getObject();

        // 2. Если не найден по токену - пытаемся найти или создать
        if (empty($msCustomer)) {
            // Получаем данные заказа
            if ($orderData === null) {
                $orderResponse = $this->ms3->order->get();
                $orderData = $orderResponse['data']['order'] ?? [];
            }

            $email = $orderData['address_email'] ?? '';

            // 3. Поиск клиента по email
            if (!empty($email)) {
                $msCustomer = $this->findByEmail($email);

                if ($msCustomer) {
                    // Обновляем токен существующего клиента
                    $msCustomer->set('token', $this->token);
                    $msCustomer->save();
                }
            }

            // 4. Создание нового клиента
            if (empty($msCustomer)) {
                $msCustomer = $this->createFromOrderData($orderData);
            }
        }

        // Событие после получения клиента
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

        if (empty($email)) {
            return null;
        }

        $msCustomer = null;
        $autoRegister = (bool)$this->modx->getOption('ms3_customer_auto_register_on_order', null, true);
        $autoLogin = (bool)$this->modx->getOption('ms3_customer_auto_login_on_order', null, true);

        // Попытка создания через RegisterService (с паролем)
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

                    // Автоматическая авторизация (если включена настройка)
                    if ($autoLogin) {
                        $_SESSION['ms3']['customer_id'] = $msCustomer->id;
                        $_SESSION['ms3']['customer_token'] = $msCustomer->get('token');
                    }
                } else {
                    // Если регистрация не удалась (например, email уже занят), пытаемся найти клиента
                    $msCustomer = $this->findByEmail($email);

                    if ($msCustomer) {
                        $msCustomer->set('token', $this->token);
                        $msCustomer->save();

                        // Автоматическая авторизация для существующего клиента
                        if ($autoLogin) {
                            $_SESSION['ms3']['customer_id'] = $msCustomer->id;
                            $_SESSION['ms3']['customer_token'] = $msCustomer->get('token');
                        }
                    }
                }
            }
        }

        // Fallback: старый метод создания (без пароля, для обратной совместимости)
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
