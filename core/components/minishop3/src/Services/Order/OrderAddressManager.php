<?php

namespace MiniShop3\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerAddress;
use MiniShop3\Model\msOrder;
use MiniShop3\Services\Customer\CustomerPublicDto;
use MODX\Revolution\modX;

/**
 * Order Address Manager
 *
 * Manages customer addresses in orders.
 * Handles applying saved addresses and cleaning address fields.
 */
class OrderAddressManager
{
    protected modX $modx;
    protected MiniShop3 $ms3;
    protected OrderDraftManager $draftManager;
    protected OrderFieldManager $fieldManager;

    public function __construct(
        modX $modx,
        MiniShop3 $ms3,
        OrderDraftManager $draftManager,
        OrderFieldManager $fieldManager
    ) {
        $this->modx = $modx;
        $this->ms3 = $ms3;
        $this->draftManager = $draftManager;
        $this->fieldManager = $fieldManager;
    }

    /**
     * Set customer address from saved addresses
     *
     * @param msOrder $draft Draft order
     * @param array $orderData Current order data
     * @param string|null $addressHash Hash of saved customer address
     * @return array Response with address fields
     */
    public function setCustomerAddress(msOrder $draft, array $orderData, ?string $addressHash = null): array
    {
        if (empty($addressHash)) {
            return $this->cleanCustomerAddress($draft, $orderData);
        }

        if (empty($orderData['customer_id'])) {
            return $this->error('ms3_err_customer_required');
        }

        $msCustomerAddress = $this->modx->getObject(msCustomerAddress::class, [
            'customer_id' => $orderData['customer_id'],
            'hash' => $addressHash,
        ]);

        if (!$msCustomerAddress) {
            return $this->error('ms3_err_address_not_found');
        }

        // Get address fields (excluding system fields)
        $excludeFields = ['id', 'customer_id', 'hash', 'name', 'comment', 'createdon', 'updatedon', 'active'];
        $addressFields = $this->getCustomerAddressFields($excludeFields);

        $returnData = [];

        foreach ($addressFields as $key => $value) {
            if (array_key_exists('address_' . $key, $orderData)) {
                $fieldValue = $msCustomerAddress->get($key);
                $this->fieldManager->add($draft, $orderData, $key, $fieldValue);
                $returnData[$key] = $fieldValue;
            }
        }

        // Store address hash in properties
        $properties = $orderData['properties'] ?? [];
        unset($properties['save_address']);
        $properties['address_hash'] = $addressHash;
        $this->draftManager->updateField($draft, 'properties', $properties);

        return $this->success('', $returnData);
    }

    /**
     * Clean customer address fields
     *
     * @param msOrder $draft Draft order
     * @param array $orderData Current order data
     * @return array Response
     */
    public function cleanCustomerAddress(msOrder $draft, array $orderData): array
    {
        // Get address fields (excluding system fields)
        $excludeFields = ['id', 'customer_id', 'hash', 'name', 'comment', 'createdon', 'updatedon', 'active'];
        $addressFields = $this->getCustomerAddressFields($excludeFields);

        foreach ($addressFields as $key => $value) {
            if (array_key_exists('address_' . $key, $orderData) && !empty($orderData['address_' . $key])) {
                $this->fieldManager->add($draft, $orderData, $key, null);
            }
        }

        // Remove address hash from properties
        $properties = $orderData['properties'] ?? [];
        unset($properties['address_hash']);
        $this->draftManager->updateField($draft, 'properties', $properties);

        return $this->success('');
    }

    /**
     * Save current order address to customer's saved addresses
     *
     * @param int $customerId Customer ID
     * @param array $orderData Order data with address fields
     * @return bool True on success, false on failure or duplicate
     */
    public function saveToCustomerAddresses(int $customerId, array $orderData): bool
    {
        if (empty($customerId)) {
            return false;
        }

        $addressData = [
            'customer_id' => $customerId,
            'country' => $orderData['address_country'] ?? '',
            'index' => $orderData['address_index'] ?? '',
            'region' => $orderData['address_region'] ?? '',
            'city' => $orderData['address_city'] ?? '',
            'metro' => $orderData['address_metro'] ?? '',
            'street' => $orderData['address_street'] ?? '',
            'building' => $orderData['address_building'] ?? '',
            'entrance' => $orderData['address_entrance'] ?? '',
            'floor' => $orderData['address_floor'] ?? '',
            'room' => $orderData['address_room'] ?? '',
            'comment' => $orderData['address_comment'] ?? '',
        ];

        return $this->ms3->customer->addAddress($addressData);
    }

    /**
     * Get customer address fields (exclude specific fields)
     *
     * @param array $exclude Fields to exclude
     * @return array Field definitions
     */
    public function getCustomerAddressFields(array $exclude = []): array
    {
        $fields = $this->modx->getFields(msCustomerAddress::class);

        if (!empty($exclude)) {
            foreach ($exclude as $key) {
                unset($fields[$key]);
            }
        }

        return $fields;
    }

    /**
     * Fill order address from customer data
     *
     * @param msOrder $draft Draft order
     * @param array $orderData Current order data
     * @param array $customerData Customer data
     * @return void
     */
    public function fillFromCustomer(msOrder $draft, array &$orderData, array $customerData): void
    {
        $fieldMappings = [
            'address_first_name' => 'first_name',
            'address_last_name' => 'last_name',
            'address_email' => 'email',
            'address_phone' => 'phone',
        ];

        foreach ($fieldMappings as $orderField => $customerField) {
            if (empty($orderData[$orderField]) && !empty($customerData[$customerField])) {
                $fieldKey = str_replace('address_', '', $orderField);
                $this->fieldManager->add($draft, $orderData, $fieldKey, $customerData[$customerField]);
                $orderData[$orderField] = $customerData[$customerField];
            }
        }
    }

    /**
     * Prefill empty checkout profile fields on the customer's draft from their public profile.
     *
     * Only fills fields that are still empty in the draft (does not overwrite checkout edits).
     */
    public function prefillProfileFieldsFromCustomer(msOrder $draft, msCustomer $customer): void
    {
        $draftCustomerId = (int) $draft->get('customer_id');
        $customerId = (int) $customer->get('id');
        if ($draftCustomerId <= 0 || $draftCustomerId !== $customerId) {
            return;
        }

        $customerData = [];
        foreach (CustomerPublicDto::CORE_PROFILE_EDITABLE_FIELDS as $field) {
            $value = $customer->get($field);
            if ($value !== null && $value !== '') {
                $customerData[$field] = $value;
            }
        }
        if ($customerData === []) {
            return;
        }

        $orderData = $this->draftManager->toArray($draft);
        $this->fillFromCustomer($draft, $orderData, $customerData);
    }

    /**
     * Shorthand for success response
     */
    protected function success(string $message = '', array $data = []): array
    {
        return $this->ms3->utils->success($message, $data);
    }

    /**
     * Shorthand for error response
     */
    protected function error(string $message = '', array $data = []): array
    {
        return $this->ms3->utils->error($message ?: 'ms3_err_unknown', $data);
    }
}
