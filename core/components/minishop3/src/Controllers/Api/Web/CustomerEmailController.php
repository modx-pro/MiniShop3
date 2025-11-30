<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Customer\EmailVerificationService;
use MODX\Revolution\modX;

/**
 * CustomerEmailController - API контроллер подтверждения email
 *
 * Обрабатывает отправку и проверку писем подтверждения email.
 *
 * Endpoints:
 * - POST /api/v1/customer/email/resend-verification - повторная отправка письма
 * - GET /api/v1/customer/email/verify - проверка токена из письма
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

        // Получить сервис верификации email
        $this->emailVerification = $this->modx->services->get('ms3_email_verification_service');
    }

    /**
     * Повторная отправка письма подтверждения
     *
     * POST /api/v1/customer/email/resend-verification
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function resendVerification(): array
    {
        // Проверка авторизации
        if (empty($_SESSION['ms3']['customer_id'])) {
            return $this->error($this->modx->lexicon('ms3_customer_err_login_required'));
        }

        $customerId = (int)$_SESSION['ms3']['customer_id'];

        // Загрузить клиента
        /** @var msCustomer $customer */
        $customer = $this->modx->getObject(msCustomer::class, $customerId);

        if (!$customer) {
            return $this->error($this->modx->lexicon('ms3_err_customer_nf'));
        }

        // Использовать метод resendVerificationEmail из сервиса
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
     * Проверка токена подтверждения из письма
     *
     * GET /api/v1/customer/email/verify?token={token}
     *
     * @param array $params Параметры запроса
     * @return array ['success' => bool, 'message' => string]
     */
    public function verify(array $params): array
    {
        $token = $params['token'] ?? '';

        if (empty($token)) {
            return $this->error($this->modx->lexicon('ms3_customer_err_token_required'));
        }

        // Проверить токен через сервис
        $customer = $this->emailVerification->verifyToken($token);

        if (!$customer) {
            return $this->error($this->modx->lexicon('ms3_customer_err_email_verification_invalid'));
        }

        // Автоматически авторизовать клиента после подтверждения email
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
     * Успешный ответ
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
     * Ответ с ошибкой
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
