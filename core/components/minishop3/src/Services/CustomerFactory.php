<?php

namespace MiniShop3\Services;

use MiniShop3\Model\msCustomer;
use MODX\Revolution\modUser;
use MODX\Revolution\modUserProfile;
use MODX\Revolution\modX;

/**
 * Factory service for creating customers
 *
 * Creates msCustomer records and optionally modUser based on system settings.
 * Can be replaced by developers via MODX Service Container.
 *
 * @package MiniShop3\Services
 */
class CustomerFactory
{
    protected modX $modx;

    /**
     * Customer fields that can be set from order data
     */
    protected array $customerFields = [
        'first_name',
        'last_name',
        'email',
        'phone',
    ];

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Create customer from order data
     *
     * @param array $data Order/customer data
     * @return msCustomer Created customer
     * @throws \Exception On creation failure
     */
    public function createFromOrderData(array $data): msCustomer
    {
        /** @var msCustomer $customer */
        $customer = $this->modx->newObject(msCustomer::class);

        // Set customer fields
        foreach ($this->customerFields as $field) {
            if (isset($data[$field])) {
                $value = trim($data[$field]);
                // Normalize email to lowercase
                if ($field === 'email' && !empty($value)) {
                    $value = strtolower($value);
                }
                $customer->set($field, $value);
            }
        }

        // Set defaults
        $customer->set('is_active', true);
        $customer->set('is_blocked', false);
        $customer->set('failed_login_attempts', 0);
        $customer->set('created_at', date('Y-m-d H:i:s'));
        $customer->set('orders_count', 0);
        $customer->set('total_spent', 0);

        // Generate secure password (for potential future login)
        $password = $this->generateSecurePassword();
        $customer->set('password', $this->hashPassword($password));

        // Check if we should create modUser
        $createModUser = $this->modx->getOption('ms3_customer_sync_create_moduser', null, false);

        if ($createModUser) {
            $user = $this->createModUser($data, $password);
            if ($user) {
                $customer->set('user_id', $user->get('id'));
            }
        }

        if (!$customer->save()) {
            throw new \Exception('Failed to save customer');
        }

        return $customer;
    }

    /**
     * Create MODX user from customer data
     *
     * @param array $data Customer data
     * @param string $password Plain password
     * @return modUser|null Created user or null on failure
     */
    protected function createModUser(array $data, string $password): ?modUser
    {
        // Email is required for modUser
        $email = $data['email'] ?? '';
        if (empty($email)) {
            $this->modx->log(modX::LOG_LEVEL_WARN, '[CustomerFactory] Cannot create modUser without email');
            return null;
        }

        // Check if user with this email already exists
        $existingUser = $this->modx->getObject(modUser::class, ['username' => $email]);
        if ($existingUser) {
            $this->modx->log(modX::LOG_LEVEL_INFO, "[CustomerFactory] modUser with email {$email} already exists");
            return $existingUser;
        }

        /** @var modUser $user */
        $user = $this->modx->newObject(modUser::class);
        $user->set('username', strtolower($email));
        $user->set('password', $password); // modUser will hash it
        $user->set('active', true);
        $user->set('blocked', false);

        if (!$user->save()) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[CustomerFactory] Failed to create modUser');
            return null;
        }

        // Create user profile
        /** @var modUserProfile $profile */
        $profile = $this->modx->newObject(modUserProfile::class);
        $profile->set('internalKey', $user->get('id'));
        $profile->set('email', strtolower($email));
        $profile->set('fullname', trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')));
        $profile->set('phone', $data['phone'] ?? '');
        $profile->save();

        // Add to user group if configured
        $groupId = (int) $this->modx->getOption('ms3_customer_sync_user_group', null, 0);
        if ($groupId > 0) {
            $user->joinGroup($groupId);
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, "[CustomerFactory] Created modUser #{$user->get('id')} for {$email}");

        return $user;
    }

    /**
     * Generate secure random password
     *
     * @param int $length Password length
     * @return string Generated password
     */
    protected function generateSecurePassword(int $length = 16): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }

    /**
     * Hash password for msCustomer
     *
     * @param string $password Plain password
     * @return string Hashed password
     */
    protected function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Get customer fields that can be set
     *
     * @return array
     */
    public function getCustomerFields(): array
    {
        return $this->customerFields;
    }

    /**
     * Set customer fields that can be set
     *
     * @param array $fields
     * @return self
     */
    public function setCustomerFields(array $fields): self
    {
        $this->customerFields = $fields;
        return $this;
    }
}
