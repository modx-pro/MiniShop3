<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerAddress;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * API controller for customer addresses (Web API)
 *
 * Manages delivery addresses for authorized customers.
 * Supports CRUD operations and address selection during checkout.
 *
 * @package MiniShop3\Controllers\Api\Web
 */
class CustomerAddressController
{
    use AuthorizedCustomerTrait;

    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->modx->lexicon->load('minishop3:customer');
    }

    /**
     * Get customer address list
     * GET /api/v1/customer/addresses
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function getList(array $params = []): array
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error($this->modx->lexicon('ms3_customer_err_not_authorized'), 401)->getData();
        }

        $addresses = $this->modx->getIterator(msCustomerAddress::class, [
            'customer_id' => $customer->get('id'),
            'active' => 1
        ]);

        $data = [];
        foreach ($addresses as $address) {
            $data[] = $this->formatAddress($address);
        }

        return Response::success($data)->getData();
    }

    /**
     * Get specific address
     * GET /api/v1/customer/addresses/{id}
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function get(array $params = []): array
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error($this->modx->lexicon('ms3_customer_err_not_authorized'), 401)->getData();
        }

        $addressId = (int)($params['id'] ?? 0);

        if (!$addressId) {
            return Response::error($this->modx->lexicon('ms3_customer_err_address_id_not_specified'), Response::HTTP_BAD_REQUEST)->getData();
        }

        $address = $this->modx->getObject(msCustomerAddress::class, [
            'id' => $addressId,
            'customer_id' => $customer->get('id')
        ]);

        if (!$address) {
            return Response::error($this->modx->lexicon('ms3_customer_err_address_not_found'), Response::HTTP_NOT_FOUND)->getData();
        }

        return Response::success($this->formatAddress($address))->getData();
    }

    /**
     * Create new address
     * POST /api/v1/customer/addresses
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function create(array $params = []): array
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error($this->modx->lexicon('ms3_customer_err_not_authorized'), 401)->getData();
        }

        $input = $this->getRequestData();

        $required = ['name', 'city', 'street'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                return Response::error($this->modx->lexicon('ms3_customer_err_field_required'), Response::HTTP_BAD_REQUEST)->getData();
            }
        }

        $addressHash = $this->generateAddressHash($input);
        $exists = $this->modx->getObject(msCustomerAddress::class, [
            'customer_id' => $customer->get('id'),
            'hash' => $addressHash,
        ]);

        if ($exists) {
            return Response::error($this->modx->lexicon('ms3_customer_address_already_exists'), 409, [
                'existing_id' => $exists->get('id')
            ])->getData();
        }

        $address = $this->modx->newObject(msCustomerAddress::class);
        $address->set('customer_id', $customer->get('id'));
        $address->set('hash', $addressHash);
        $address->set('createdon', date('Y-m-d H:i:s'));

        $fields = ['name', 'country', 'index', 'region', 'city', 'metro', 'street', 'building', 'entrance', 'floor', 'room', 'comment'];
        foreach ($fields as $field) {
            if (isset($input[$field])) {
                $address->set($field, $input[$field]);
            }
        }

        if (!$address->save()) {
            return Response::error($this->modx->lexicon('ms3_customer_address_creation_error'), Response::HTTP_INTERNAL_SERVER_ERROR)->getData();
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, '[MS3] Created customer address: ' . $addressHash . ' for customer #' . $customer->get('id'));

        return Response::success($this->formatAddress($address), $this->modx->lexicon('ms3_customer_address_added'), 201)->getData();
    }

    /**
     * Update address
     * PUT /api/v1/customer/addresses/{id}
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function update(array $params = []): array
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error($this->modx->lexicon('ms3_customer_err_not_authorized'), 401)->getData();
        }

        $addressId = (int)($params['id'] ?? 0);

        if (!$addressId) {
            return Response::error($this->modx->lexicon('ms3_customer_err_address_id_not_specified'), Response::HTTP_BAD_REQUEST)->getData();
        }

        $address = $this->modx->getObject(msCustomerAddress::class, [
            'id' => $addressId,
            'customer_id' => $customer->get('id')
        ]);

        if (!$address) {
            return Response::error($this->modx->lexicon('ms3_customer_err_address_not_found'), Response::HTTP_NOT_FOUND)->getData();
        }

        $input = $this->getRequestData();

        $fields = ['name', 'country', 'index', 'region', 'city', 'metro', 'street', 'building', 'entrance', 'floor', 'room', 'comment'];
        $hasChanges = false;

        foreach ($fields as $field) {
            if (isset($input[$field]) && $input[$field] !== $address->get($field)) {
                $address->set($field, $input[$field]);
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            $addressHash = $this->generateAddressHash($input);
            $address->set('hash', $addressHash);
            $address->set('updatedon', date('Y-m-d H:i:s'));

            if (!$address->save()) {
                return Response::error($this->modx->lexicon('ms3_customer_address_update_error'), Response::HTTP_INTERNAL_SERVER_ERROR)->getData();
            }

            $this->modx->log(modX::LOG_LEVEL_INFO, '[MS3] Updated customer address #' . $addressId);
        }

        return Response::success($this->formatAddress($address), $this->modx->lexicon('ms3_customer_address_updated'))->getData();
    }

    /**
     * Set default address
     * PUT /api/v1/customer/addresses/{id}/set-default
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function setDefault(array $params = []): array
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error($this->modx->lexicon('ms3_customer_err_not_authorized'), 401)->getData();
        }

        $addressId = (int)($params['id'] ?? 0);

        if (!$addressId) {
            return Response::error($this->modx->lexicon('ms3_customer_err_address_id_not_specified'), Response::HTTP_BAD_REQUEST)->getData();
        }

        $address = $this->modx->getObject(msCustomerAddress::class, [
            'id' => $addressId,
            'customer_id' => $customer->get('id'),
            'active' => 1
        ]);

        if (!$address) {
            return Response::error($this->modx->lexicon('ms3_customer_err_address_not_found'), Response::HTTP_NOT_FOUND)->getData();
        }

        $table = $this->modx->getTableName(msCustomerAddress::class);
        $sql = "UPDATE {$table} SET is_default = 0 WHERE customer_id = :customer_id";
        $stmt = $this->modx->prepare($sql);
        $stmt->execute(['customer_id' => $customer->get('id')]);

        $address->set('is_default', 1);
        $address->set('updatedon', date('Y-m-d H:i:s'));

        if (!$address->save()) {
            return Response::error($this->modx->lexicon('ms3_customer_address_default_error'), Response::HTTP_INTERNAL_SERVER_ERROR)->getData();
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, '[MS3] Set default address #' . $addressId . ' for customer #' . $customer->get('id'));

        return Response::success($this->formatAddress($address), $this->modx->lexicon('ms3_customer_address_default_set'))->getData();
    }

    /**
     * Delete address (soft delete via active = 0)
     * DELETE /api/v1/customer/addresses/{id}
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function delete(array $params = []): array
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error($this->modx->lexicon('ms3_customer_err_not_authorized'), 401)->getData();
        }

        $addressId = (int)($params['id'] ?? 0);

        if (!$addressId) {
            return Response::error($this->modx->lexicon('ms3_customer_err_address_id_not_specified'), Response::HTTP_BAD_REQUEST)->getData();
        }

        $address = $this->modx->getObject(msCustomerAddress::class, [
            'id' => $addressId,
            'customer_id' => $customer->get('id')
        ]);

        if (!$address) {
            return Response::error($this->modx->lexicon('ms3_customer_err_address_not_found'), Response::HTTP_NOT_FOUND)->getData();
        }

        $address->set('active', 0);
        $address->set('updatedon', date('Y-m-d H:i:s'));

        if (!$address->save()) {
            return Response::error($this->modx->lexicon('ms3_customer_address_delete_error'), Response::HTTP_INTERNAL_SERVER_ERROR)->getData();
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, '[MS3] Deleted customer address #' . $addressId);

        return Response::success(null, $this->modx->lexicon('ms3_customer_address_deleted'))->getData();
    }

    /**
     * Format address for API response
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
            'is_default' => (int)$address->get('is_default'),
        ];
    }

    /**
     * Generate address hash for duplicate detection
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
     * Get request body data (POST/PUT)
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
     * Transform response from old format to new
     *
     * @param array $result
     * @return array
     */
    protected function transformResponse(array $result): array
    {
        if (isset($result['success'])) {
            return $result;
        }

        return Response::success($result)->getData();
    }
}
