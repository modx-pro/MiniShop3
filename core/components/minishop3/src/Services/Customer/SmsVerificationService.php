<?php

namespace MiniShop3\Services\Customer;

use MiniShop3\Model\msCustomer;
use MODX\Revolution\modX;

/**
 * SmsVerificationService - сервис подтверждения телефонных номеров через SMS
 *
 * ЗАГЛУШКА для будущей интеграции с SMS провайдерами (Twilio, SMS.ru и т.д.)
 *
 * В Phase 1 (MVP) этот сервис не реализован полностью.
 * Методы возвращают заглушки для совместимости с архитектурой.
 *
 * Пример будущей интеграции:
 * ```php
 * class SmsRuProvider implements SmsProviderInterface {
 *     public function sendSms(string $phone, string $message): bool {
 *         // Интеграция с SMS.ru API
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
     * Отправить код подтверждения по SMS (заглушка)
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

        // TODO: Интеграция с SMS провайдером
        // $code = $this->generateCode();
        // $sent = $this->provider->sendSms($phone, "Your verification code: {$code}");

        return [
            'success' => false,
            'message' => 'SMS verification is not configured',
            'code' => null,
        ];
    }

    /**
     * Проверить код подтверждения (заглушка)
     *
     * @param msCustomer $customer
     * @param string $code
     * @return bool
     */
    public function verifyCode(msCustomer $customer, string $code): bool
    {
        // TODO: Проверка кода из хранилища (кеш/БД)
        $this->modx->log(
            modX::LOG_LEVEL_WARN,
            "[SmsVerificationService] SMS verification not implemented"
        );

        return false;
    }

    /**
     * Генерация 6-значного кода подтверждения
     *
     * @return string
     */
    protected function generateCode(): string
    {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
