<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MODX\Revolution\modX;
use Rakit\Validation\Validator;

/**
 * CustomerProfileController - Customer profile management API controller
 *
 * Handles customer personal data updates.
 *
 * Endpoints:
 * - PUT /api/v1/customer/profile - update profile
 *
 * @package MiniShop3\Controllers\Api\Web
 */
class CustomerProfileController
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
        $this->modx->lexicon->load('minishop3:customer');
    }

    /**
     * Update customer profile
     *
     * PUT /api/v1/customer/profile
     *
     * @param array $data Form data
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    public function update(array $data): array
    {
        if (empty($_SESSION['ms3']['customer_id'])) {
            return $this->error($this->modx->lexicon('ms3_customer_err_login_required'));
        }

        $customerId = (int)$_SESSION['ms3']['customer_id'];

        /** @var msCustomer $customer */
        $customer = $this->modx->getObject(msCustomer::class, $customerId);

        if (!$customer) {
            return $this->error($this->modx->lexicon('ms3_err_customer_nf'));
        }

        $validator = new Validator();
        $validation = $validator->make($data, [
            'first_name' => 'required|min:2|max:100',
            'last_name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'phone' => 'required|min:10|max:20',
        ]);

        $validation->validate();

        if ($validation->fails()) {
            $errors = $validation->errors()->firstOfAll();
            $_SESSION['ms3']['customer_profile_errors'] = $errors;

            return $this->error(
                $this->modx->lexicon('ms3_customer_err_validation'),
                ['errors' => $errors]
            );
        }

        $oldEmail = $customer->get('email');
        $newEmail = trim($data['email']);

        if ($oldEmail !== $newEmail) {
            $existingCustomer = $this->modx->getObject(msCustomer::class, [
                'email' => $newEmail,
                'id:!=' => $customerId,
            ]);

            if ($existingCustomer) {
                $_SESSION['ms3']['customer_profile_errors'] = [
                    'email' => $this->modx->lexicon('ms3_customer_err_email_exists')
                ];
                return $this->error($this->modx->lexicon('ms3_customer_err_email_exists'));
            }

            $customer->set('email_verified_at', null);

            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                "[CustomerProfileController] Email changed for customer #{$customerId}: {$oldEmail} → {$newEmail}. Verification reset."
            );
        }

        $customer->set('first_name', trim($data['first_name']));
        $customer->set('last_name', trim($data['last_name']));
        $customer->set('email', $newEmail);
        $customer->set('phone', trim($data['phone']));

        if (!$customer->save()) {
            return $this->error($this->modx->lexicon('ms3_customer_err_save'));
        }

        $_SESSION['ms3']['customer_profile_success'] = true;

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[CustomerProfileController] Profile updated for customer #{$customerId}"
        );

        return $this->success(
            $this->modx->lexicon('ms3_customer_profile_updated'),
            ['customer' => $customer->toArray()]
        );
    }

    /**
     * Success response
     *
     * @param string $message
     * @param array $data
     * @return array
     */
    protected function success(string $message = '', array $data = []): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Error response
     *
     * @param string $message
     * @param array $data
     * @return array
     */
    protected function error(string $message, array $data = []): array
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => $data,
        ];
    }
}
