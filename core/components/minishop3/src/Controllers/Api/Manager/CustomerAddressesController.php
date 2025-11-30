<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerAddress;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * API контроллер для управления адресами клиентов (Manager API)
 *
 * @package MiniShop3\Controllers\Api\Manager
 */
class CustomerAddressesController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Получить список адресов клиента
     * GET /api/mgr/customers/{id}/addresses
     *
     * @param array $params URL параметры (id - customer_id)
     * @return array Response
     */
    public function getList(array $params = []): array
    {
        $customerId = (int)($params['id'] ?? 0);

        if (!$customerId) {
            return Response::error('Customer ID is required', 400)->getData();
        }

        // Проверяем существование клиента
        $customer = $this->modx->getObject(msCustomer::class, $customerId);
        if (!$customer) {
            return Response::error('Customer not found', 404)->getData();
        }

        $addresses = $this->modx->getIterator(msCustomerAddress::class, [
            'customer_id' => $customerId
        ], [
            'sortby' => 'id',
            'sortdir' => 'DESC'
        ]);

        $results = [];
        foreach ($addresses as $address) {
            $results[] = $this->formatAddress($address);
        }

        return Response::success([
            'results' => $results,
            'total' => count($results),
            'customer_id' => $customerId
        ])->getData();
    }

    /**
     * Создать новый адрес
     * POST /api/mgr/customers/{id}/addresses
     *
     * @param array $data Данные адреса
     * @return array Response
     */
    public function create(array $data = []): array
    {
        $customerId = (int)($data['customer_id'] ?? 0);

        if (!$customerId) {
            return Response::error('Customer ID is required', 400)->getData();
        }

        // Проверяем существование клиента
        $customer = $this->modx->getObject(msCustomer::class, $customerId);
        if (!$customer) {
            return Response::error('Customer not found', 404)->getData();
        }

        /** @var msCustomerAddress $address */
        $address = $this->modx->newObject(msCustomerAddress::class);

        $address->set('customer_id', $customerId);

        // Заполняем поля
        $allowedFields = [
            'name', 'country', 'index', 'region', 'city', 'metro',
            'street', 'building', 'entrance', 'floor', 'room', 'comment', 'active'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $address->set($field, $data[$field]);
            }
        }

        // Генерируем hash для адреса
        $address->set('hash', $this->generateAddressHash($data));
        $address->set('createdon', date('Y-m-d H:i:s'));

        if (!$address->save()) {
            return Response::error('Failed to create address', 500)->getData();
        }

        return Response::success(
            $this->formatAddress($address),
            'Address created successfully'
        )->getData();
    }

    /**
     * Обновить адрес
     * PUT /api/mgr/customers/{id}/addresses/{address_id}
     *
     * @param array $data Данные для обновления
     * @return array Response
     */
    public function update(array $data = []): array
    {
        $customerId = (int)($data['customer_id'] ?? 0);
        $addressId = (int)($data['id'] ?? 0);

        if (!$customerId || !$addressId) {
            return Response::error('Customer ID and Address ID are required', 400)->getData();
        }

        $address = $this->modx->getObject(msCustomerAddress::class, [
            'id' => $addressId,
            'customer_id' => $customerId
        ]);

        if (!$address) {
            return Response::error('Address not found', 404)->getData();
        }

        // Обновляем поля
        $allowedFields = [
            'name', 'country', 'index', 'region', 'city', 'metro',
            'street', 'building', 'entrance', 'floor', 'room', 'comment', 'active'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $address->set($field, $data[$field]);
            }
        }

        // Обновляем hash
        $address->set('hash', $this->generateAddressHash($data));
        $address->set('updatedon', date('Y-m-d H:i:s'));

        if (!$address->save()) {
            return Response::error('Failed to update address', 500)->getData();
        }

        return Response::success(
            $this->formatAddress($address),
            'Address updated successfully'
        )->getData();
    }

    /**
     * Удалить адрес
     * DELETE /api/mgr/customers/{id}/addresses/{address_id}
     *
     * @param array $params URL параметры
     * @return array Response
     */
    public function delete(array $params = []): array
    {
        $customerId = (int)($params['id'] ?? 0);
        $addressId = (int)($params['address_id'] ?? 0);

        if (!$customerId || !$addressId) {
            return Response::error('Customer ID and Address ID are required', 400)->getData();
        }

        $address = $this->modx->getObject(msCustomerAddress::class, [
            'id' => $addressId,
            'customer_id' => $customerId
        ]);

        if (!$address) {
            return Response::error('Address not found', 404)->getData();
        }

        if (!$address->remove()) {
            return Response::error('Failed to delete address', 500)->getData();
        }

        return Response::success([], 'Address deleted successfully')->getData();
    }

    /**
     * Форматировать объект адреса для API ответа
     *
     * @param msCustomerAddress $address
     * @return array
     */
    protected function formatAddress(msCustomerAddress $address): array
    {
        return [
            'id' => $address->get('id'),
            'customer_id' => $address->get('customer_id'),
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
            'active' => (bool)$address->get('active'),
            'createdon' => $address->get('createdon'),
            'updatedon' => $address->get('updatedon'),
            // Форматированный адрес для отображения
            'formatted' => $this->formatAddressString($address),
        ];
    }

    /**
     * Генерировать hash адреса для дедупликации
     *
     * @param array $data
     * @return string
     */
    protected function generateAddressHash(array $data): string
    {
        $parts = [
            $data['country'] ?? '',
            $data['index'] ?? '',
            $data['region'] ?? '',
            $data['city'] ?? '',
            $data['street'] ?? '',
            $data['building'] ?? '',
            $data['room'] ?? '',
        ];

        return md5(mb_strtolower(implode('|', $parts)));
    }

    /**
     * Форматировать адрес в строку
     *
     * @param msCustomerAddress $address
     * @return string
     */
    protected function formatAddressString(msCustomerAddress $address): string
    {
        $parts = array_filter([
            $address->get('index'),
            $address->get('country'),
            $address->get('region'),
            $address->get('city'),
            $address->get('street'),
            $address->get('building') ? 'д. ' . $address->get('building') : null,
            $address->get('room') ? 'кв. ' . $address->get('room') : null,
        ]);

        return implode(', ', $parts);
    }
}
