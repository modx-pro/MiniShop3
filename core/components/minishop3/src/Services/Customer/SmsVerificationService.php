<?php

namespace MiniShop3\Services\Customer;

use MiniShop3\Model\msCustomer;
use MODX\Revolution\modX;

/**
 * SmsVerificationService - phone number verification service via SMS
 *
 * STUB for future integration with SMS providers (Twilio, SMS.ru, etc.)
 *
 * In Phase 1 (MVP) this service is not fully implemented.
 * Methods return stubs for architecture compatibility.
 *
 * Example of future integration:
 * ```php
 * class SmsRuProvider implements SmsProviderInterface {
 *     public function sendSms(string $phone, string $message): bool {
 *         // Integration with SMS.ru API
 *         $client = new SmsRuClient($this->apiKey);
 *         return $client->send($phone, $message);
 *     }
 * }
 *
 * $smsService = new SmsVerificationService($modx);
 * $smsService->setProvider(new SmsRuProvider($apiKey));
 * $smsService->sendVerificationCode($customer);
 * ```
 *
 * @package MiniShop3\Services
 */
class SmsVerificationService
{
    /** @var modX */
    protected modX $modx;

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Send verification code via SMS (stub)
     *
     * @param msCustomer $customer
     * @return array ['success' => bool, 'message' => string, 'code' => string|null]
     */
    public function sendVerificationCode(msCustomer $customer): array
    {
        $phone = $customer->get('phone');

        if (empty($phone)) {
            return [
                'success' => false,
                'message' => 'Phone number is required',
                'code' => null,
            ];
        }

        $this->modx->log(
            modX::LOG_LEVEL_WARN,
            "[SmsVerificationService] SMS sending not implemented (phone: {$phone})"
        );

        return [
            'success' => false,
            'message' => 'SMS verification is not configured',
            'code' => null,
        ];
    }

    /**
     * Verify code (stub)
     *
     * @param msCustomer $customer
     * @param string $code
     * @return bool
     */
    public function verifyCode(msCustomer $customer, string $code): bool
    {
        $this->modx->log(
            modX::LOG_LEVEL_WARN,
            "[SmsVerificationService] SMS verification not implemented"
        );

        return false;
    }

    /**
     * Generate 6-digit verification code
     *
     * @return string
     */
    protected function generateCode(): string
    {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
