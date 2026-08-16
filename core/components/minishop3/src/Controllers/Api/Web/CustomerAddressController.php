<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerAddress;
use MiniShop3\Router\ApiErrorCode;
use MiniShop3\Router\HttpStatus;
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
     * @return Response
     */
    public function getList(array $params = []): Response
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error($this->modx->lexicon('ms3_customer_err_not_authorized'), HttpStatus::UNAUTHORIZED);
        }

        $addresses = $this->modx->getIterator(msCustomerAddress::class, [
            'customer_id' => $customer->get('id'),
            'active' => 1
        ]);

        $data = [];
        foreach ($addresses as $address) {
            $data[] = $this->formatAddress($address);
        }

        return Response::success([
            'items' => $data,
            'total' => count($data),
        ]);
    }

    /**
     * Get specific address
     * GET /api/v1/customer/addresses/{id}
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function get(array $params = []): Response
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error($this->modx->lexicon('ms3_customer_err_not_authorized'), HttpStatus::UNAUTHORIZED);
        }

        $addressId = (int)($params['id'] ?? 0);

        if (!$addressId) {
            return Response::error($this->modx->lexicon('ms3_customer_err_address_id_not_specified'), HttpStatus::BAD_REQUEST);
        }

        $address = $this->modx->getObject(msCustomerAddress::class, [
            'id' => $addressId,
            'customer_id' => $customer->get('id')
        ]);

        if (!$address) {
            return Response::error($this->modx->lexicon('ms3_customer_err_address_not_found'), HttpStatus::NOT_FOUND);
        }

        return Response::success($this->formatAddress($address));
    }

    /**
     * Create new address
     * POST /api/v1/customer/addresses
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function create(array $params = []): Response
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error($this->modx->lexicon('ms3_customer_err_not_authorized'), HttpStatus::UNAUTHORIZED);
        }

        $input = $this->getRequestData();

        $required = ['name', 'city', 'street'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                $missing[$field] = $this->modx->lexicon('ms3_customer_err_field_required');
            }
        }
        if ($missing !== []) {
            return Response::errorWithCode(
                ApiErrorCode::VALIDATION_FAILED,
                $this->modx->lexicon('ms3_customer_err_field_required'),
                HttpStatus::UNPROCESSABLE_ENTITY,
                $missing,
            );
        }

        $addressHash = $this->generateAddressHash($input);
        $exists = $this->modx->getObject(msCustomerAddress::class, [
            'customer_id' => $customer->get('id'),
            'hash' => $addressHash,
        ]);

        if ($exists) {
            return Response::errorWithCode(
                ApiErrorCode::CONFLICT,
                $this->modx->lexicon('ms3_customer_address_already_exists'),
                HttpStatus::CONFLICT,
                data: ['existing_id' => $exists->get('id')],
            );
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
            return Response::error($this->modx->lexicon('ms3_customer_address_creation_error'), HttpStatus::INTERNAL_SERVER_ERROR);
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, '[MS3] Created customer address: ' . $addressHash . ' for customer #' . $customer->get('id'));

        return Response::success($this->formatAddress($address), $this->modx->lexicon('ms3_customer_address_added'), HttpStatus::CREATED);
    }

    /**
     * Update address
     * PUT /api/v1/customer/addresses/{id}
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function update(array $params = []): Response
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error($this->modx->lexicon('ms3_customer_err_not_authorized'), HttpStatus::UNAUTHORIZED);
        }

        $addressId = (int)($params['id'] ?? 0);

        if (!$addressId) {
            return Response::error($this->modx->lexicon('ms3_customer_err_address_id_not_specified'), HttpStatus::BAD_REQUEST);
        }

        $address = $this->modx->getObject(msCustomerAddress::class, [
            'id' => $addressId,
            'customer_id' => $customer->get('id')
        ]);

        if (!$address) {
            return Response::error($this->modx->lexicon('ms3_customer_err_address_not_found'), HttpStatus::NOT_FOUND);
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
                return Response::error($this->modx->lexicon('ms3_customer_address_update_error'), HttpStatus::INTERNAL_SERVER_ERROR);
            }

            $this->modx->log(modX::LOG_LEVEL_INFO, '[MS3] Updated customer address #' . $addressId);
        }

        return Response::success($this->formatAddress($address), $this->modx->lexicon('ms3_customer_address_updated'));
    }

    /**
     * Set default address
     * PUT /api/v1/customer/addresses/{id}/set-default
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function setDefault(array $params = []): Response
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error($this->modx->lexicon('ms3_customer_err_not_authorized'), HttpStatus::UNAUTHORIZED);
        }

        $addressId = (int)($params['id'] ?? 0);

        if (!$addressId) {
            return Response::error($this->modx->lexicon('ms3_customer_err_address_id_not_specified'), HttpStatus::BAD_REQUEST);
        }

        $address = $this->modx->getObject(msCustomerAddress::class, [
            'id' => $addressId,
            'customer_id' => $customer->get('id'),
            'active' => 1
        ]);

        if (!$address) {
            return Response::error($this->modx->lexicon('ms3_customer_err_address_not_found'), HttpStatus::NOT_FOUND);
        }

        $table = $this->modx->getTableName(msCustomerAddress::class);
        $sql = "UPDATE {$table} SET is_default = 0 WHERE customer_id = :customer_id";
        $stmt = $this->modx->prepare($sql);
        $stmt->execute(['customer_id' => $customer->get('id')]);

        $address->set('is_default', 1);
        $address->set('updatedon', date('Y-m-d H:i:s'));

        if (!$address->save()) {
            return Response::error($this->modx->lexicon('ms3_customer_address_default_error'), HttpStatus::INTERNAL_SERVER_ERROR);
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, '[MS3] Set default address #' . $addressId . ' for customer #' . $customer->get('id'));

        return Response::success($this->formatAddress($address), $this->modx->lexicon('ms3_customer_address_default_set'));
    }

    /**
     * Delete address (soft delete via active = 0)
     * DELETE /api/v1/customer/addresses/{id}
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function delete(array $params = []): Response
    {
        $customer = $this->getAuthorizedCustomer();

        if (!$customer) {
            return Response::error($this->modx->lexicon('ms3_customer_err_not_authorized'), HttpStatus::UNAUTHORIZED);
        }

        $addressId = (int)($params['id'] ?? 0);

        if (!$addressId) {
            return Response::error($this->modx->lexicon('ms3_customer_err_address_id_not_specified'), HttpStatus::BAD_REQUEST);
        }

        $address = $this->modx->getObject(msCustomerAddress::class, [
            'id' => $addressId,
            'customer_id' => $customer->get('id')
        ]);

        if (!$address) {
            return Response::error($this->modx->lexicon('ms3_customer_err_address_not_found'), HttpStatus::NOT_FOUND);
        }

        $address->set('active', 0);
        $address->set('updatedon', date('Y-m-d H:i:s'));

        if (!$address->save()) {
            return Response::error($this->modx->lexicon('ms3_customer_address_delete_error'), HttpStatus::INTERNAL_SERVER_ERROR);
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, '[MS3] Deleted customer address #' . $addressId);

        return Response::success(null, $this->modx->lexicon('ms3_customer_address_deleted'));
    }

    /**
     * Format address for API response
     *
     * @return array<string, mixed>
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
}
