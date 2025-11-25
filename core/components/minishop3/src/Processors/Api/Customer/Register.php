<?php

namespace MiniShop3\Processors\Api\Customer;

use MiniShop3\Services\Customer\AuthManager;
use MiniShop3\Services\Customer\EmailVerificationService;
use MiniShop3\Services\Customer\RateLimiter;
use MiniShop3\Services\Customer\RegisterService;
use MODX\Revolution\Processors\Processor;

/**
 * Register - процессор регистрации нового клиента
 *
 * Создает нового клиента с валидацией и опциональной верификацией email.
 * Защищен от спама через RateLimiter.
 *
 * @package MiniShop3\Processors\Api\Customer
 */
class Register extends Processor
{
    /**
     * @return array|string
     */
    public function process()
    {
        // Загружаем лексикон
        $this->modx->lexicon->load('minishop3:customer');

        $email = trim($this->getProperty('email', ''));
        $password = $this->getProperty('password', '');
        $firstName = trim($this->getProperty('first_name', ''));
        $lastName = trim($this->getProperty('last_name', ''));
        $phone = trim($this->getProperty('phone', ''));
        $privacyAccepted = (bool)$this->getProperty('privacy_accepted', false);

        // Валидация обязательных полей
        if (empty($email)) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_email_required'));
        }

        if (empty($password)) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_password_required'));
        }

        // GDPR: проверка согласия
        $requirePrivacy = (bool)$this->modx->getOption('ms3_customer_require_privacy_consent', null, true);
        if ($requirePrivacy && !$privacyAccepted) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_privacy_required'));
        }

        /** @var RateLimiter $rateLimiter */
        $rateLimiter = $this->modx->services->get('ms3_rate_limiter');

        // Rate limiting по IP (не более 3 регистраций в час)
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!$rateLimiter->check('register', $ip, 3, 3600)) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_register_rate_limit'));
        }

        /** @var RegisterService $registerService */
        $registerService = $this->modx->services->get('ms3_register_service');

        // Устанавливаем EmailVerificationService
        /** @var EmailVerificationService $emailVerification */
        $emailVerification = $this->modx->services->get('ms3_email_verification_service');
        $registerService->setEmailVerification($emailVerification);

        // Регистрация
        $result = $registerService->register([
            'email' => $email,
            'password' => $password,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'privacy_accepted' => $privacyAccepted,
        ]);

        if (!$result['success']) {
            return $this->failure($result['message']);
        }

        $customer = $result['customer'];

        // Автоматический вход после регистрации (опционально)
        $autoLogin = (bool)$this->modx->getOption('ms3_customer_auto_login_after_register', null, true);
        $requireEmailVerification = (bool)$this->modx->getOption('ms3_customer_require_email_verification', null, true);

        $tokenData = null;

        if ($autoLogin && !$requireEmailVerification) {
            /** @var AuthManager $authManager */
            $authManager = $this->modx->services->get('ms3_auth_manager');

            $ttl = (int)$this->modx->getOption('ms3_customer_api_token_ttl', null, 86400);
            $tokenObj = $authManager->createToken($customer, 'api', $ttl);

            if ($tokenObj) {
                // Сохраняем в сессию
                if (!isset($_SESSION['ms3'])) {
                    $_SESSION['ms3'] = [];
                }
                $_SESSION['ms3']['customer_id'] = $customer->id;
                $_SESSION['ms3']['customer_token'] = $tokenObj->get('token');

                $tokenData = [
                    'token' => $tokenObj->get('token'),
                    'expires_at' => $tokenObj->get('expires_at'),
                ];
            }
        }

        // Сбрасываем login rate limiter для этого IP
        $rateLimiter->reset('login', $ip);

        // Определяем URL для редиректа (только если автовход включен)
        $redirectUrl = '';
        if ($autoLogin && !$requireEmailVerification) {
            $redirectPageId = (int)$this->getProperty('redirect_page_id', 0);
            if (!$redirectPageId) {
                $redirectPageId = (int)$this->modx->getOption('ms3_customer_redirect_after_login', null, 0);
            }

            if ($redirectPageId > 0) {
                $redirectUrl = $this->modx->makeUrl($redirectPageId, '', '', 'full');
            }
        }

        return $this->success($this->modx->lexicon('ms3_customer_register_success'), [
            'customer' => [
                'id' => $customer->id,
                'email' => $customer->get('email'),
                'first_name' => $customer->get('first_name'),
                'last_name' => $customer->get('last_name'),
                'phone' => $customer->get('phone'),
                'email_verified' => !empty($customer->get('email_verified_at')),
            ],
            'token' => $tokenData,
            'email_verification_required' => $requireEmailVerification,
            'redirect_url' => $redirectUrl,
        ]);
    }
}
