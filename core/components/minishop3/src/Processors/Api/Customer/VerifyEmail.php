<?php

namespace MiniShop3\Processors\Api\Customer;

use MiniShop3\Services\Customer\EmailVerificationService;
use MODX\Revolution\Processors\Processor;

/**
 * VerifyEmail - email address verification processor
 *
 * Validates token from email and activates customer's email.
 *
 * @package MiniShop3\Processors\Api\Customer
 */
class VerifyEmail extends Processor
{
    /**
     * @return array|string
     */
    public function process()
    {
        $token = trim($this->getProperty('token', ''));

        if (empty($token)) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_token_required'));
        }

        /** @var EmailVerificationService $emailService */
        $emailService = $this->modx->services->get('ms3_email_verification_service');

        $customer = $emailService->verifyToken($token);

        if (!$customer) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_email_verification_invalid'));
        }

        return $this->success($this->modx->lexicon('ms3_customer_email_verified'), [
            'customer' => [
                'id' => $customer->id,
                'email' => $customer->get('email'),
                'email_verified' => true,
            ],
        ]);
    }
}
