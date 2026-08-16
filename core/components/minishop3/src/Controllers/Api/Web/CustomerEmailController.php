<?php

declare(strict_types=1);

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MiniShop3\Router\ApiErrorCode;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Customer\AuthManager;
use MiniShop3\Services\Customer\CustomerPublicDto;
use MiniShop3\Services\Customer\EmailVerificationService;
use MODX\Revolution\modX;

/**
 * CustomerEmailController - Email verification API controller
 *
 * Endpoints:
 * - POST /api/v1/customer/email/resend-verification
 * - GET /api/v1/customer/email/verify
 *
 * @package MiniShop3\Controllers\Api\Web
 */
class CustomerEmailController
{
    protected modX $modx;

    protected MiniShop3 $ms3;

    protected EmailVerificationService $emailVerification;

    public function __construct(modX $modx, MiniShop3 $ms3)
    {
        $this->modx = $modx;
        $this->ms3 = $ms3;
        $this->modx->lexicon->load('minishop3:customer');

        $this->emailVerification = $this->modx->services->get('ms3_email_verification_service');
    }

    /**
     * POST /api/v1/customer/email/resend-verification
     */
    public function resendVerification(): Response
    {
        if (empty($_SESSION['ms3']['customer_id'])) {
            return Response::error(
                $this->modx->lexicon('ms3_customer_err_login_required'),
                HttpStatus::UNAUTHORIZED
            );
        }

        $customerId = (int) $_SESSION['ms3']['customer_id'];

        /** @var msCustomer|null $customer */
        $customer = $this->modx->getObject(msCustomer::class, $customerId);

        if (!$customer instanceof msCustomer) {
            return Response::error(
                $this->modx->lexicon('ms3_err_customer_nf'),
                HttpStatus::UNAUTHORIZED
            );
        }

        $result = $this->emailVerification->resendVerificationEmail($customer);

        if (!empty($result['success'])) {
            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                "[CustomerEmailController] Verification email resent to customer #{$customerId}"
            );

            return Response::success(
                $result['data'] ?? null,
                isset($result['message']) ? (string) $result['message'] : ''
            );
        }

        return Response::error(
            (string) ($result['message'] ?? $this->modx->lexicon('ms3_err_unknown')),
            Response::statusFromProcessorObject($result)
        );
    }

    /**
     * GET /api/v1/customer/email/verify?token={token}
     *
     * - `format=json` — всегда JSON (интеграции, отладка).
     * - `html=1` (как в ссылке из письма) — после успеха/ошибки HTTP 302 на сайт (см. GH-226).
     *
     * @param array<string, mixed> $params
     */
    public function verify(array $params): Response
    {
        $formatJson = ($params['format'] ?? '') === 'json';
        $htmlFlow = ($params['html'] ?? '') === '1';

        $token = $params['token'] ?? '';

        if ($token === '') {
            if ($htmlFlow && !$formatJson) {
                return Response::redirect($this->buildEmailVerificationFailedRedirectUrl(), 302);
            }

            return Response::errorWithCode(
                ApiErrorCode::BAD_REQUEST,
                $this->modx->lexicon('ms3_customer_err_token_required'),
                HttpStatus::BAD_REQUEST
            );
        }

        $customer = $this->emailVerification->verifyToken((string) $token);

        if (!$customer) {
            if ($htmlFlow && !$formatJson) {
                return Response::redirect($this->buildEmailVerificationFailedRedirectUrl(), 302);
            }

            return Response::errorWithCode(
                ApiErrorCode::BAD_REQUEST,
                $this->modx->lexicon('ms3_customer_err_email_verification_invalid'),
                HttpStatus::BAD_REQUEST
            );
        }

        /** @var AuthManager $authManager */
        $authManager = $this->modx->services->get('ms3_auth_manager');
        $session = $authManager->establishApiSession($customer);

        if (!$session) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[CustomerEmailController] Email verified for customer #{$customer->id} but API session bind failed"
            );

            if ($htmlFlow && !$formatJson) {
                return Response::redirect($this->buildEmailVerificationSuccessRedirectUrl(), 302);
            }

            // Email is verified; auto-login failed (same UX as html=1 redirect without session)
            return Response::success(
                [
                    'customer_id' => $customer->id,
                    'customer' => CustomerPublicDto::fromCustomer($customer, $this->modx, $this->ms3),
                    'token' => null,
                    'expires_at' => null,
                ],
                $this->modx->lexicon('ms3_customer_email_verified')
            );
        }

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[CustomerEmailController] Email verified and customer #{$customer->id} auto-logged in"
        );

        if ($htmlFlow && !$formatJson) {
            return Response::redirect($this->buildEmailVerificationSuccessRedirectUrl(), 302);
        }

        return Response::success(
            [
                'customer_id' => $customer->id,
                'customer' => CustomerPublicDto::fromCustomer($customer, $this->modx, $this->ms3),
                'token' => $session['token'],
                'expires_at' => $session['expires_at'],
            ],
            $this->modx->lexicon('ms3_customer_email_verified')
        );
    }

    /**
     * Куда вести пользователя после успешной верификации (браузер, html=1)
     */
    protected function buildEmailVerificationSuccessRedirectUrl(): string
    {
        $target = trim((string) $this->modx->getOption('ms3_email_verification_success_url', null, ''));
        if ($target === '') {
            $target = rtrim((string) $this->modx->getOption('site_url', null, '/'), '/');
        }
        $sep = str_contains($target, '?') ? '&' : '?';

        return $target . $sep . 'ms3_email_verified=1';
    }

    /**
     * Куда вести при невалидном/просроченном токене (браузер, html=1)
     */
    protected function buildEmailVerificationFailedRedirectUrl(): string
    {
        $base = rtrim((string) $this->modx->getOption('site_url', null, '/'), '/');

        return $base . '?ms3_email_verified=0';
    }
}
