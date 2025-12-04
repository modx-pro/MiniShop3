<?php

namespace MiniShop3\Services\Customer;

/**
 * ProfilePageService - customer profile page service
 *
 * Displays customer personal data with editing capability.
 * Shows email verification status and resend button.
 *
 * Example usage in snippet:
 * ```php
 * [[!msCustomer?
 *   &service=`profile`
 *   &tpl=`ms3_customer_profile`
 * ]]
 * ```
 *
 * @package MiniShop3\Services\Customer
 */
class ProfilePageService extends CustomerPageService
{
    /**
     * Get raw profile data
     *
     * @return array Customer profile data
     */
    public function getData(): array
    {
        $customerData = $this->customer->toArray();

        $emailVerified = !empty($customerData['email_verified_at']);
        $emailVerifiedAt = $emailVerified
            ? date('d.m.Y H:i', strtotime($customerData['email_verified_at']))
            : null;

        $phoneVerified = !empty($customerData['phone_verified_at']);
        $phoneVerifiedAt = $phoneVerified
            ? date('d.m.Y H:i', strtotime($customerData['phone_verified_at']))
            : null;

        return [
            'customer' => $customerData,
            'email_verified' => $emailVerified,
            'email_verified_at' => $emailVerifiedAt,
            'phone_verified' => $phoneVerified,
            'phone_verified_at' => $phoneVerifiedAt,
            'errors' => $_SESSION['ms3']['customer_profile_errors'] ?? [],
            'success' => $_SESSION['ms3']['customer_profile_success'] ?? false,
        ];
    }

    /**
     * Render profile page
     *
     * @return string HTML content
     */
    public function render(): string
    {
        $tpl = $this->modx->getOption(
            'tpl',
            $this->scriptProperties,
            'tpl.msCustomer.profile'
        );

        $data = $this->getData();

        unset($_SESSION['ms3']['customer_profile_errors']);
        unset($_SESSION['ms3']['customer_profile_success']);

        $chunk = $this->pdoFetch->getChunk($tpl, $data);
        return is_string($chunk) ? $chunk : '';
    }
}
