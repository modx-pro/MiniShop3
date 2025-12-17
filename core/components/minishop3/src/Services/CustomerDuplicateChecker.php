<?php

namespace MiniShop3\Services;

use MiniShop3\Model\msCustomer;
use MODX\Revolution\modX;

/**
 * Service for checking customer duplicates
 *
 * Can be replaced by developers via MODX Service Container
 * to implement custom duplicate checking logic.
 *
 * @package MiniShop3\Services
 */
class CustomerDuplicateChecker
{
    protected modX $modx;

    /**
     * Fields to check for duplicates
     * @var array
     */
    protected array $checkFields = ['email', 'phone'];

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->loadFieldsFromSettings();
    }

    /**
     * Load check fields from system settings
     */
    protected function loadFieldsFromSettings(): void
    {
        $fieldsJson = $this->modx->getOption('ms3_customer_duplicate_fields', null, '["email", "phone"]');
        $fields = json_decode($fieldsJson, true);

        if (is_array($fields) && !empty($fields)) {
            $this->checkFields = $fields;
        }
    }

    /**
     * Find duplicate customer by provided data
     *
     * Uses OR logic: returns first customer matching ANY of the check fields
     *
     * @param array $data Customer data to check (email, phone, etc.)
     * @return msCustomer|null Found duplicate or null
     */
    public function findDuplicate(array $data): ?msCustomer
    {
        foreach ($this->checkFields as $field) {
            $value = $data[$field] ?? null;

            if (empty($value)) {
                continue;
            }

            // Normalize the value for comparison
            $normalizedValue = $this->normalizeFieldValue($field, $value);

            if (empty($normalizedValue)) {
                continue;
            }

            /** @var msCustomer|null $customer */
            $customer = $this->modx->getObject(msCustomer::class, [
                $field => $normalizedValue
            ]);

            if ($customer) {
                return $customer;
            }
        }

        return null;
    }

    /**
     * Normalize field value for comparison
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @return string|null Normalized value
     */
    protected function normalizeFieldValue(string $field, $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (empty($value)) {
            return null;
        }

        switch ($field) {
            case 'email':
                // Lowercase email for comparison
                return strtolower($value);

            case 'phone':
                // Remove all non-digit characters for phone comparison
                $digits = preg_replace('/\D/', '', $value);
                // Return original if no digits or too short
                return strlen($digits) >= 7 ? $digits : $value;

            default:
                return $value;
        }
    }

    /**
     * Get fields used for duplicate checking
     *
     * @return array
     */
    public function getCheckFields(): array
    {
        return $this->checkFields;
    }

    /**
     * Set fields for duplicate checking
     *
     * Allows programmatic override of check fields
     *
     * @param array $fields
     * @return self
     */
    public function setCheckFields(array $fields): self
    {
        $this->checkFields = $fields;
        return $this;
    }

    /**
     * Check if data has any checkable fields with values
     *
     * @param array $data
     * @return bool
     */
    public function hasCheckableData(array $data): bool
    {
        foreach ($this->checkFields as $field) {
            $value = $data[$field] ?? null;
            if (!empty($value) && is_string($value) && trim($value) !== '') {
                return true;
            }
        }
        return false;
    }
}
