<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerAddress;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * API контроллер для работы с адресами клиентов (Web API)
 *
 * Управление адресами доставки для авторизованных клиентов.
 * Поддерживает CRUD операции и выбор адреса при оформлении заказа.
 *
 * @package MiniShop3\Controllers\Api\Web
 */
class CustomerAddressController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Получение списка адресов клиента
     * GET /api/v1/customer/addresses
     *
     * @param array $params URL параметры
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function getList(array $params = []): array
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error('Customer not authorized', 401)->getData();
        }

        // Получаем все активные адреса клиента
        $addresses = $this->modx->getIterator(msCustomerAddress::class, [
            'customer_id' => $customer->get('id'),
            'active' => 1
        ]);

        $data = [];
        foreach ($addresses as $address) {
            $data[] = $this->formatAddress($address);
        }

        return Response::success($data, 'Addresses retrieved successfully')->getData();
    }

    /**
     * Получение конкретного адреса
     * GET /api/v1/customer/addresses/{id}
     *
     * @param array $params URL параметры
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function get(array $params = []): array
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error('Customer not authorized', 401)->getData();
        }

        $addressId = (int)($params['id'] ?? 0);

        if (!$addressId) {
            return Response::error('Address ID is required', 400)->getData();
        }

        $address = $this->modx->getObject(msCustomerAddress::class, [
            'id' => $addressId,
            'customer_id' => $customer->get('id')
        ]);

        if (!$address) {
            return Response::error('Address not found', 404)->getData();
        }

        return Response::success($this->formatAddress($address), 'Address retrieved successfully')->getData();
    }

    /**
     * Создание нового адреса
     * POST /api/v1/customer/addresses
     *
     * @param array $params URL параметры
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function create(array $params = []): array
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error('Customer not authorized', 401)->getData();
        }

        $input = $this->getRequestData();

        // Валидация обязательных полей
        $required = ['name', 'city', 'street'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                return Response::error("Field '{$field}' is required", 400)->getData();
            }
        }

        // Проверка дубликата по хешу адреса
        $addressHash = $this->generateAddressHash($input);
        $exists = $this->modx->getObject(msCustomerAddress::class, [
            'customer_id' => $customer->get('id'),
            'hash' => $addressHash,
        ]);

        if ($exists) {
            return Response::error('Address already exists', 409, [
                'existing_id' => $exists->get('id')
            ])->getData();
        }

        // Создание нового адреса
        $address = $this->modx->newObject(msCustomerAddress::class);
        $address->set('customer_id', $customer->get('id'));
        $address->set('hash', $addressHash);
        $address->set('createdon', date('Y-m-d H:i:s'));

        // Установка полей адреса
        $fields = ['name', 'country', 'index', 'region', 'city', 'metro', 'street', 'building', 'entrance', 'floor', 'room', 'comment'];
        foreach ($fields as $field) {
            if (isset($input[$field])) {
                $address->set($field, $input[$field]);
            }
        }

        if (!$address->save()) {
            return Response::error('Failed to save address', 500)->getData();
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, '[MS3] Created customer address: ' . $addressHash . ' for customer #' . $customer->get('id'));

        return Response::success($this->formatAddress($address), 'Address created successfully', 201)->getData();
    }

    /**
     * Обновление адреса
     * PUT /api/v1/customer/addresses/{id}
     *
     * @param array $params URL параметры
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function update(array $params = []): array
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error('Customer not authorized', 401)->getData();
        }

        $addressId = (int)($params['id'] ?? 0);

        if (!$addressId) {
            return Response::error('Address ID is required', 400)->getData();
        }

        $address = $this->modx->getObject(msCustomerAddress::class, [
            'id' => $addressId,
            'customer_id' => $customer->get('id')
        ]);

        if (!$address) {
            return Response::error('Address not found', 404)->getData();
        }

        $input = $this->getRequestData();

        // Обновление полей адреса
        $fields = ['name', 'country', 'index', 'region', 'city', 'metro', 'street', 'building', 'entrance', 'floor', 'room', 'comment'];
        $hasChanges = false;

        foreach ($fields as $field) {
            if (isset($input[$field]) && $input[$field] !== $address->get($field)) {
                $address->set($field, $input[$field]);
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            // Обновление хеша при изменении ключевых полей
            $addressHash = $this->generateAddressHash($input);
            $address->set('hash', $addressHash);
            $address->set('updatedon', date('Y-m-d H:i:s'));

            if (!$address->save()) {
                return Response::error('Failed to update address', 500)->getData();
            }

            $this->modx->log(modX::LOG_LEVEL_INFO, '[MS3] Updated customer address #' . $addressId);
        }

        return Response::success($this->formatAddress($address), 'Address updated successfully')->getData();
    }

    /**
     * Удаление адреса (мягкое удаление через active = 0)
     * DELETE /api/v1/customer/addresses/{id}
     *
     * @param array $params URL параметры
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function delete(array $params = []): array
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error('Customer not authorized', 401)->getData();
        }

        $addressId = (int)($params['id'] ?? 0);

        if (!$addressId) {
            return Response::error('Address ID is required', 400)->getData();
        }

        $address = $this->modx->getObject(msCustomerAddress::class, [
            'id' => $addressId,
            'customer_id' => $customer->get('id')
        ]);

        if (!$address) {
            return Response::error('Address not found', 404)->getData();
        }

        // Мягкое удаление
        $address->set('active', 0);
        $address->set('updatedon', date('Y-m-d H:i:s'));

        if (!$address->save()) {
            return Response::error('Failed to delete address', 500)->getData();
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, '[MS3] Deleted customer address #' . $addressId);

        return Response::success(null, 'Address deleted successfully')->getData();
    }

    /**
     * Получение авторизованного клиента из сессии
     *
     * @return msCustomer|null
     */
    protected function getAuthorizedCustomer(): ?msCustomer
    {
        $ms3 = $this->modx->services->get('ms3');
        if (!$ms3) {
            return null;
        }

        $ms3->initialize();

        // Получаем токен клиента
        $token = $_REQUEST['ms3_token'] ?? $_SESSION['ms3']['customer_token'] ?? '';

        if (empty($token)) {
            return null;
        }

        // Получаем клиента по токену
        $customer = $this->modx->getObject(msCustomer::class, ['token' => $token]);

        return $customer ?: null;
    }

    /**
     * Форматирование адреса для API ответа
     *
     * @param msCustomerAddress $address
     * @return array
     */
    protected function formatAddress(msCustomerAddress $address): array
    {
        return [
            'id' => (int)$address->get('id'),
            'name' => $address->get('name'),
            'country' => $address->get('country'),
            'index' => $address->get('index'),
            'region' => $address->get('region'),
            'city' => $address->get('city'),
            'metro' => $address->get('metro'),
            'street' => $address->get('street'),
            'building' => $address->get('building'),
            'entrance' => $address->get('entrance'),
            'floor' => $address->get('floor'),
            'room' => $address->get('room'),
            'comment' => $address->get('comment'),
            'hash' => $address->get('hash'),
            'createdon' => $address->get('createdon'),
            'updatedon' => $address->get('updatedon'),
            'active' => (int)$address->get('active'),
        ];
    }

    /**
     * Генерация хеша адреса для определения дубликатов
     *
     * @param array $data
     * @return string
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

    /**
     * Получение данных из тела запроса (POST/PUT)
     *
     * @return array
     */
    protected function getRequestData(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Преобразование ответа из старого формата в новый
     *
     * @param array $result
     * @return array
     */
    protected function transformResponse(array $result): array
    {
        // Если ответ уже в правильном формате, возвращаем как есть
        if (isset($result['success'])) {
            return $result;
        }

        // Преобразование старого формата
        return Response::success($result)->getData();
    }
}
