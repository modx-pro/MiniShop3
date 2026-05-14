<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerAddress;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * API controller for managing customer addresses (Manager API)
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
     * Get customer address list
     * GET /api/mgr/customers/{id}/addresses
     *
     * @param array $params URL parameters (id - customer_id)
     * @return array Response
     */
    public function getList(array $params = []): array
    {
        $customerId = (int)($params['id'] ?? 0);

        if (!$customerId) {
            return Response::error('Customer ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $customer = $this->modx->getObject(msCustomer::class, $customerId);
        if (!$customer) {
            return Response::error('Customer not found', HttpStatus::NOT_FOUND)->getData();
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
     * Create new address
     * POST /api/mgr/customers/{id}/addresses
     *
     * @param array $data Address data
     * @return array Response
     */
    public function create(array $data = []): array
    {
        $customerId = (int)($data['customer_id'] ?? 0);

        if (!$customerId) {
            return Response::error('Customer ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $customer = $this->modx->getObject(msCustomer::class, $customerId);
        if (!$customer) {
            return Response::error('Customer not found', HttpStatus::NOT_FOUND)->getData();
        }

        /** @var msCustomerAddress $address */
        $address = $this->modx->newObject(msCustomerAddress::class);

        $address->set('customer_id', $customerId);

        $allowedFields = [
            'name', 'country', 'index', 'region', 'city', 'metro',
            'street', 'building', 'entrance', 'floor', 'room', 'comment', 'active'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $address->set($field, $data[$field]);
            }
        }

        $address->set('hash', $this->generateAddressHash($data));
        $address->set('createdon', date('Y-m-d H:i:s'));

        if (!$address->save()) {
            return Response::error('Failed to create address', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success(
            $this->formatAddress($address),
            'Address created successfully'
        )->getData();
    }

    /**
     * Update address
     * PUT /api/mgr/customers/{id}/addresses/{address_id}
     *
     * @param array $data Data to update
     * @return array Response
     */
    public function update(array $data = []): array
    {
        $customerId = (int)($data['customer_id'] ?? 0);
        $addressId = (int)($data['id'] ?? 0);

        if (!$customerId || !$addressId) {
            return Response::error('Customer ID and Address ID are required', HttpStatus::BAD_REQUEST)->getData();
        }

        $address = $this->modx->getObject(msCustomerAddress::class, [
            'id' => $addressId,
            'customer_id' => $customerId
        ]);

        if (!$address) {
            return Response::error('Address not found', HttpStatus::NOT_FOUND)->getData();
        }

        $allowedFields = [
            'name', 'country', 'index', 'region', 'city', 'metro',
            'street', 'building', 'entrance', 'floor', 'room', 'comment', 'active'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $address->set($field, $data[$field]);
            }
        }

        $address->set('hash', $this->generateAddressHash($data));
        $address->set('updatedon', date('Y-m-d H:i:s'));

        if (!$address->save()) {
            return Response::error('Failed to update address', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success(
            $this->formatAddress($address),
            'Address updated successfully'
        )->getData();
    }

    /**
     * Delete address
     * DELETE /api/mgr/customers/{id}/addresses/{address_id}
     *
     * @param array $params URL parameters
     * @return array Response
     */
    public function delete(array $params = []): array
    {
        $customerId = (int)($params['id'] ?? 0);
        $addressId = (int)($params['address_id'] ?? 0);

        if (!$customerId || !$addressId) {
            return Response::error('Customer ID and Address ID are required', HttpStatus::BAD_REQUEST)->getData();
        }

        $address = $this->modx->getObject(msCustomerAddress::class, [
            'id' => $addressId,
            'customer_id' => $customerId
        ]);

        if (!$address) {
            return Response::error('Address not found', HttpStatus::NOT_FOUND)->getData();
        }

        if (!$address->remove()) {
            return Response::error('Failed to delete address', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success([], 'Address deleted successfully')->getData();
    }

    /**
     * Format address object for API response
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
            'formatted' => $this->formatAddressString($address),
        ];
    }

    /**
     * Generate address hash for deduplication
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
     * Format address as string
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
            $address->get('building') ? 'bldg. ' . $address->get('building') : null,
            $address->get('room') ? 'apt. ' . $address->get('room') : null,
        ]);

        return implode(', ', $parts);
    }
}
