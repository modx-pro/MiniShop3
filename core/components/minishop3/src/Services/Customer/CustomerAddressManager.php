<?php

namespace MiniShop3\Services\Customer;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomerAddress;
use MODX\Revolution\modX;

/**
 * Service for managing customer addresses
 *
 * Handles CRUD operations for customer addresses including:
 * - Adding new addresses with duplicate detection
 * - Retrieving customer addresses
 * - Address hash generation for deduplication
 */
class CustomerAddressManager
{
    /** @var modX */
    protected modX $modx;

    /** @var MiniShop3 */
    protected MiniShop3 $ms3;

    /**
     * @param modX $modx
     * @param MiniShop3 $ms3
     */
    public function __construct(modX $modx, MiniShop3 $ms3)
    {
        $this->modx = $modx;
        $this->ms3 = $ms3;
    }

    /**
     * Add address for customer
     *
     * Creates a new address record with duplicate detection via hash.
     * Fires events: msOnBeforeAddCustomerAddress, msOnAddCustomerAddress
     *
     * @param array $addressData Address data with required fields: customer_id, city, street
     * @return bool True on success, false on failure or duplicate
     */
    public function add(array $addressData): bool
    {
        if (empty($addressData['customer_id'])) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[CustomerAddressManager::add] customer_id is required');
            return false;
        }

        if (empty($addressData['city'])) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[CustomerAddressManager::add] city is required');
            return false;
        }

        if (empty($addressData['street'])) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[CustomerAddressManager::add] street is required');
            return false;
        }

        // Allow plugins to modify address data before saving
        $response = $this->ms3->utils->invokeEvent('msOnBeforeAddCustomerAddress', [
            'addressData' => $addressData,
        ]);
        if (!$response['success']) {
            return false;
        }
        $addressData = $response['data']['addressData'];

        // Generate name if not provided
        if (empty($addressData['name'])) {
            $nameParts = array_filter([
                $addressData['city'] ?? '',
                $addressData['street'] ?? '',
                $addressData['building'] ?? '',
            ]);
            $addressData['name'] = implode(', ', $nameParts);
        }

        // Generate hash for duplicate detection
        $addressHash = $this->generateHash($addressData);
        $addressData['hash'] = $addressHash;

        // Check for duplicates
        $isExists = $this->modx->getCount(msCustomerAddress::class, [
            'customer_id' => $addressData['customer_id'],
            'hash' => $addressHash,
        ]);

        if (!empty($isExists)) {
            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                '[CustomerAddressManager::add] Address already exists for customer #' . $addressData['customer_id']
            );
            return false;
        }

        if (empty($addressData['createdon'])) {
            $addressData['createdon'] = date('Y-m-d H:i:s');
        }

        $msCustomerAddress = $this->modx->newObject(msCustomerAddress::class, $addressData);

        if (!$msCustomerAddress->save()) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[CustomerAddressManager::add] Failed to save address');
            return false;
        }

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            '[CustomerAddressManager::add] Created address #' . $msCustomerAddress->get('id') .
            ' for customer #' . $addressData['customer_id']
        );

        // Allow plugins to act after address is added
        $response = $this->ms3->utils->invokeEvent('msOnAddCustomerAddress', [
            'addressData' => $addressData,
            'msCustomerAddress' => $msCustomerAddress,
        ]);
        if (!$response['success']) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                '[CustomerAddressManager::add] msOnAddCustomerAddress event failed: ' . $response['message']
            );
        }

        return true;
    }

    /**
     * Get all addresses for customer
     *
     * @param int $customerId Customer ID
     * @return array Array of address records
     */
    public function getByCustomerId(int $customerId): array
    {
        if ($customerId <= 0) {
            return [];
        }

        $q = $this->modx->newQuery(msCustomerAddress::class);
        $q->where(['customer_id' => $customerId]);
        $q->select($this->modx->getSelectColumns(msCustomerAddress::class, 'msCustomerAddress'));
        $q->prepare();
        $q->stmt->execute();

        return $q->stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Generate address hash for duplicate detection
     *
     * @param array $data Address data
     * @return string MD5 hash of address
     */
    public function generateHash(array $data): string
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
     * Get single address by ID
     *
     * @param int $addressId Address ID
     * @return msCustomerAddress|null
     */
    public function getById(int $addressId): ?msCustomerAddress
    {
        if ($addressId <= 0) {
            return null;
        }

        return $this->modx->getObject(msCustomerAddress::class, $addressId);
    }

    /**
     * Delete address by ID
     *
     * @param int $addressId Address ID
     * @param int $customerId Customer ID (for verification)
     * @return bool
     */
    public function delete(int $addressId, int $customerId): bool
    {
        $address = $this->modx->getObject(msCustomerAddress::class, [
            'id' => $addressId,
            'customer_id' => $customerId,
        ]);

        if (!$address) {
            return false;
        }

        return $address->remove();
    }

    /**
     * Update address
     *
     * @param int $addressId Address ID
     * @param int $customerId Customer ID (for verification)
     * @param array $data Updated data
     * @return bool
     */
    public function update(int $addressId, int $customerId, array $data): bool
    {
        $address = $this->modx->getObject(msCustomerAddress::class, [
            'id' => $addressId,
            'customer_id' => $customerId,
        ]);

        if (!$address) {
            return false;
        }

        // Update allowed fields
        $allowedFields = ['name', 'city', 'street', 'building', 'room', 'index', 'properties'];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $address->set($field, $data[$field]);
            }
        }

        // Regenerate hash if address changed.
        // array_key_exists (not isset) — null in payload is a legitimate clear of an
        // address component and must trigger hash recalc, otherwise the cleared address
        // keeps its old hash and breaks dedup.
        if (
            array_key_exists('city', $data)
            || array_key_exists('street', $data)
            || array_key_exists('building', $data)
            || array_key_exists('room', $data)
        ) {
            $address->set('hash', $this->generateHash(array_merge($address->toArray(), $data)));
        }

        return $address->save();
    }
}
