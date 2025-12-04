<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Customer\EmailVerificationService;
use MODX\Revolution\modX;

/**
 * CustomerEmailController - Email verification API controller
 *
 * Handles sending and verification of email confirmation.
 *
 * Endpoints:
 * - POST /api/v1/customer/email/resend-verification - resend verification email
 * - GET /api/v1/customer/email/verify - verify token from email
 *
 * @package MiniShop3\Controllers\Api\Web
 */
class CustomerEmailController
{
    /** @var modX */
    protected modX $modx;

    /** @var MiniShop3 */
    protected MiniShop3 $ms3;

    /** @var EmailVerificationService */
    protected EmailVerificationService $emailVerification;

    /**
     * @param modX $modx
     * @param MiniShop3 $ms3
     */
    public function __construct(modX $modx, MiniShop3 $ms3)
    {
        $this->modx = $modx;
        $this->ms3 = $ms3;
        $this->modx->lexicon->load('minishop3:customer');

        $this->emailVerification = $this->modx->services->get('ms3_email_verification_service');
    }

    /**
     * Resend verification email
     *
     * POST /api/v1/customer/email/resend-verification
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function resendVerification(): array
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

        $result = $this->emailVerification->resendVerificationEmail($customer);

        if ($result['success']) {
            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                "[CustomerEmailController] Verification email resent to customer #{$customerId}"
            );
        }

        return $result;
    }

    /**
     * Verify confirmation token from email
     *
     * GET /api/v1/customer/email/verify?token={token}
     *
     * @param array $params Request parameters
     * @return array ['success' => bool, 'message' => string]
     */
    public function verify(array $params): array
    {
        $token = $params['token'] ?? '';

        if (empty($token)) {
            return $this->error($this->modx->lexicon('ms3_customer_err_token_required'));
        }

        $customer = $this->emailVerification->verifyToken($token);

        if (!$customer) {
            return $this->error($this->modx->lexicon('ms3_customer_err_email_verification_invalid'));
        }

        $_SESSION['ms3']['customer_id'] = $customer->id;
        $_SESSION['ms3']['customer_token'] = $customer->get('token');

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[CustomerEmailController] Email verified and customer #{$customer->id} auto-logged in"
        );

        return $this->success(
            $this->modx->lexicon('ms3_customer_email_verified'),
            ['customer_id' => $customer->id]
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
