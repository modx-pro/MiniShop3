<?php

namespace MiniShop3\Processors\Api\Customer;

use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Customer\EmailVerificationService;
use MODX\Revolution\Processors\Processor;

/**
 * ResendVerification - processor for resending verification email
 *
 * Sends new email with email confirmation token.
 * Protected from spam via cooldown in EmailVerificationService.
 *
 * @package MiniShop3\Processors\Api\Customer
 */
class ResendVerification extends Processor
{
    /**
     * @return array|string
     */
    public function process()
    {
        $email = trim($this->getProperty('email', ''));

        if (empty($email)) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_email_required'));
        }

        /** @var msCustomer $customer */
        $customer = $this->modx->getObject(msCustomer::class, ['email' => $email]);

        if (!$customer) {
            return $this->success($this->modx->lexicon('ms3_email_verification_sent'));
        }

        /** @var EmailVerificationService $emailService */
        $emailService = $this->modx->services->get('ms3_email_verification_service');

        $result = $emailService->resendVerificationEmail($customer);

        if (!$result['success']) {
            return $this->failure($result['message']);
        }

        return $this->success($result['message']);
    }
}
