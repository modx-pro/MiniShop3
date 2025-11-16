<?php

namespace MiniShop3\Processors\Api\Customer;

use MiniShop3\Services\Customer\AuthManager;
use MiniShop3\Services\Customer\RateLimiter;
use MODX\Revolution\Processors\Processor;

/**
 * Login - процессор входа клиента
 *
 * Аутентифицирует клиента и создает API токен для сессии.
 * Защищен от брутфорса через RateLimiter.
 *
 * @package MiniShop3\Processors\Api\Customer
 */
class Login extends Processor
{
    /**
     * @return array|string
     */
    public function process()
    {
        $email = trim($this->getProperty('email', ''));
        $password = $this->getProperty('password', '');

        // Валидация входных данных
        if (empty($email) || empty($password)) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_login_required'));
        }

        /** @var RateLimiter $rateLimiter */
        $rateLimiter = $this->modx->services->get('ms3_rate_limiter');

        // Rate limiting по IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $maxAttempts = 5;
        $windowSeconds = 300; // 5 минут

        if (!$rateLimiter->check('login', $ip, $maxAttempts, $windowSeconds)) {
            $attempts = $rateLimiter->getAttempts('login', $ip);
            return $this->failure(
                $this->modx->lexicon('ms3_customer_err_login_rate_limit', [
                    'attempts' => $attempts,
                    'max' => $maxAttempts,
                    'minutes' => round($windowSeconds / 60)
                ])
            );
        }

        /** @var AuthManager $authManager */
        $authManager = $this->modx->services->get('ms3_auth_manager');

        // Аутентификация
        $customer = $authManager->authenticate([
            'email' => $email,
            'password' => $password,
        ]);

        if (!$customer) {
            // Неудачная попытка - увеличиваем счетчик
            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_WARN,
                "[Login] Failed login attempt for email: {$email} from IP: {$ip}"
            );

            return $this->failure($this->modx->lexicon('ms3_customer_err_login_invalid'));
        }

        // Успешный вход - сбрасываем rate limiter
        $rateLimiter->reset('login', $ip);

        // Создаем API токен для сессии
        $ttl = (int)$this->modx->getOption('ms3_customer_api_token_ttl', null, 86400); // 24 часа
        $tokenObj = $authManager->createToken($customer, 'api', $ttl);

        if (!$tokenObj) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_token_create'));
        }

        // Сохраняем в сессию
        if (!isset($_SESSION['ms3'])) {
            $_SESSION['ms3'] = [];
        }
        $_SESSION['ms3']['customer_id'] = $customer->id;
        $_SESSION['ms3']['customer_token'] = $tokenObj->get('token');

        return $this->success('', [
            'customer' => [
                'id' => $customer->id,
                'email' => $customer->get('email'),
                'first_name' => $customer->get('first_name'),
                'last_name' => $customer->get('last_name'),
                'phone' => $customer->get('phone'),
                'email_verified' => !empty($customer->get('email_verified_at')),
            ],
            'token' => $tokenObj->get('token'),
            'expires_at' => $tokenObj->get('expires_at'),
        ]);
    }
}
