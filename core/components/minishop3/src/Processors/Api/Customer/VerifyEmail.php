<?php

namespace MiniShop3\Processors\Api\Customer;

use MiniShop3\MiniShop3;
use MiniShop3\Services\Customer\CustomerPublicDto;
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
    /** @var array<string> */
    public $languageTopics = ['minishop3:customer'];

    /**
     * @return array|string
     */
    public function process()
    {
        $this->modx->lexicon->load('minishop3:customer');

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

        /** @var MiniShop3 $ms3 */
        $ms3 = $this->modx->services->get('ms3');

        return $this->success($this->modx->lexicon('ms3_customer_email_verify_success'), [
            'customer' => CustomerPublicDto::fromCustomer($customer, $this->modx, $ms3),
        ]);
    }
}
