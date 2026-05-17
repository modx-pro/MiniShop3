<?php

namespace MiniShop3\Services\Order;

use MiniShop3\MiniShop3;
use MODX\Revolution\modUser;
use MODX\Revolution\modUserProfile;
use MODX\Revolution\modUserSetting;
use MODX\Revolution\modX;

/**
 * Order User Resolver
 *
 * Resolves or creates MODX users for orders.
 * Handles user lookup by email/phone and automatic registration.
 */
class OrderUserResolver
{
    protected modX $modx;
    protected MiniShop3 $ms3;

    public function __construct(modX $modx, MiniShop3 $ms3)
    {
        $this->modx = $modx;
        $this->ms3 = $ms3;
    }

    /**
     * Get or create user ID for order
     *
     * @param array $orderData Order data with address fields
     * @return int User ID or 0 on failure
     */
    public function getUserId(array $orderData): int
    {
        $modUser = null;

        $response = $this->ms3->utils->invokeEvent('msOnBeforeGetOrderUser', [
            'resolver' => $this,
            'user' => $modUser,
            'orderData' => $orderData,
        ]);

        if (!$response['success']) {
            return 0;
        }

        if (isset($response['data']['orderData']) && is_array($response['data']['orderData'])) {
            $orderData = $response['data']['orderData'];
        }

        if (!empty($response['data']['user']) && $response['data']['user'] instanceof modUser) {
            $modUser = $response['data']['user'];
        }

        if (!$modUser) {
            $email = $orderData['address_email'] ?? '';
            $firstName = $orderData['address_first_name'] ?? '';
            $lastName = $orderData['address_last_name'] ?? '';
            $fullName = trim(implode(' ', [$firstName, $lastName]));
            $phone = $orderData['address_phone'] ?? '';

            // Generate fallback name if empty
            if (empty($fullName)) {
                $fullName = $this->generateFallbackName($email, $phone);
            }

            // Generate username from full name
            $modResource = $this->modx->newObject(\modResource::class);
            $userName = $modResource->cleanAlias($fullName);

            // Generate email if empty
            if (empty($email)) {
                $email = $userName . '@' . $this->modx->getOption('http_host');
            }

            // Check if current user is authenticated
            if ($this->modx->user->isAuthenticated()) {
                $this->updateCurrentUserProfile($email, $phone);
                $modUser = $this->modx->user;
            } else {
                // Try to find existing user or create new one
                $userData = [
                    'email' => $email,
                    'full_name' => $fullName,
                    'user_name' => $userName,
                    'phone' => $phone,
                ];

                $modUser = $this->checkUserExists($userData);

                if (!$modUser) {
                    $modUser = $this->createUser($userData);
                }
            }
        }

        $response = $this->ms3->utils->invokeEvent('msOnGetOrderUser', [
            'resolver' => $this,
            'user' => $modUser,
        ]);

        if (!$response['success']) {
            return 0;
        }

        return $modUser instanceof modUser ? $modUser->get('id') : 0;
    }

    /**
     * Check if user already exists by email or phone
     *
     * @param array $data User data (email, phone)
     * @return modUser|null Existing user or null
     */
    public function checkUserExists(array $data): ?modUser
    {
        $c = $this->modx->newQuery(modUser::class);
        $c->leftJoin(modUserProfile::class, 'Profile');

        $filter = [
            'username' => $data['email'],
            'OR:Profile.email:=' => $data['email']
        ];

        if (!empty($data['phone'])) {
            $filter['OR:Profile.mobilephone:='] = $data['phone'];
        }

        $c->where($filter);
        $c->select('modUser.id');

        return $this->modx->getObject(modUser::class, $c);
    }

    /**
     * Create new MODX user from order data
     *
     * @param array $data User data
     * @return modUser|null Created user or null on failure
     */
    public function createUser(array $data): ?modUser
    {
        $modUser = $this->modx->newObject(modUser::class);
        $modUser->set('username', $data['user_name']);

        // Generate secure random password using MODX built-in method
        $password = $modUser->generatePassword();
        $modUser->set('password', $password);

        // Create profile
        $profile = $this->modx->newObject(modUserProfile::class, [
            'email' => $data['email'],
            'fullname' => $data['full_name'],
            'mobilephone' => $data['phone'],
        ]);
        $modUser->addOne($profile);

        // Set culture key setting
        /** @var modUserSetting $setting */
        $setting = $this->modx->newObject(modUserSetting::class);
        $setting->fromArray([
            'key' => 'cultureKey',
            'area' => 'language',
            'value' => $this->modx->getOption('cultureKey', null, 'en', true),
        ], '', true);
        $modUser->addMany($setting);

        if (!$modUser->save()) {
            return null;
        }

        // Join user groups if configured
        $this->joinUserGroups($modUser);

        return $modUser;
    }

    /**
     * Update current authenticated user's profile
     *
     * @param string $email Email to set if empty
     * @param string $phone Phone to set if empty
     */
    protected function updateCurrentUserProfile(string $email, string $phone): void
    {
        $profile = $this->modx->user->Profile;

        if (!$profile->get('email')) {
            $profile->set('email', $email);
        }

        if (!$profile->get('mobilephone')) {
            $profile->set('mobilephone', $phone);
        }

        $profile->save();
    }

    /**
     * Join user to configured groups
     *
     * @param modUser $modUser User to add to groups
     */
    protected function joinUserGroups(modUser $modUser): void
    {
        $groups = $this->modx->getOption('ms3_order_user_groups', null, false);

        if (!$groups) {
            return;
        }

        $groupRoles = array_map('trim', explode(',', $groups));

        foreach ($groupRoles as $groupRole) {
            $parts = explode(':', $groupRole);

            $roleId = null;
            if (count($parts) > 1 && !empty($parts[1])) {
                $roleId = is_numeric($parts[1]) ? (int)$parts[1] : $parts[1];
            }

            $modUser->joinGroup($parts[0], $roleId);
        }
    }

    /**
     * Generate fallback name from email or phone
     *
     * @param string $email User email
     * @param string $phone User phone
     * @return string Generated name
     */
    protected function generateFallbackName(string $email, string $phone): string
    {
        if (!empty($email)) {
            return substr($email, 0, strpos($email, '@'));
        }

        if (!empty($phone)) {
            return preg_replace('#\D#', '', $phone);
        }

        return uniqid('user_', false);
    }
}
