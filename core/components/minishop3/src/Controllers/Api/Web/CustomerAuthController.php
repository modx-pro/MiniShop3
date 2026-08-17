<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Customer\CustomerSessionService;
use MiniShop3\Services\TokenService;
use MODX\Revolution\modX;

/**
 * CustomerAuthController — login, register, logout, password recovery, session (Web API).
 *
 * Delegates to Processors\Api\Customer\* and maps processor failures to HTTP responses.
 */
class CustomerAuthController
{
    public function __construct(protected modX $modx)
    {
    }

    /**
     * POST /api/v1/customer/login
     */
    public function loginFromRequest(): Response
    {
        return $this->login($this->readJsonBody());
    }

    /**
     * POST /api/v1/customer/register
     */
    public function registerFromRequest(): Response
    {
        return $this->register($this->readJsonBody());
    }

    /**
     * POST /api/v1/customer/forgot-password
     */
    public function forgotPasswordFromRequest(): Response
    {
        return $this->forgotPassword($this->readJsonBody());
    }

    /**
     * POST /api/v1/customer/reset-password
     */
    public function resetPasswordFromRequest(): Response
    {
        return $this->resetPassword($this->readJsonBody());
    }

    /**
     * GET /api/v1/customer/me
     */
    public function me(): Response
    {
        $payload = $this->sessionService()->buildMePayload($this->requestToken());
        if ($payload === null) {
            return Response::error('ms3_err_token_invalid', HttpStatus::UNAUTHORIZED);
        }

        return Response::success($payload);
    }

    /**
     * POST /api/v1/customer/token/refresh
     */
    public function refreshToken(): Response
    {
        /** @var TokenService $tokenService */
        $tokenService = $this->modx->services->get('ms3_token_service');
        $rotated = $tokenService->rotateApiToken($this->requestToken());
        if ($rotated === null) {
            return Response::error('ms3_err_token_invalid', HttpStatus::UNAUTHORIZED);
        }

        return Response::success($rotated);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonBody(): array
    {
        $input = file_get_contents('php://input');

        return json_decode($input, true) ?: [];
    }

    /**
     * POST /api/v1/customer/login
     *
     * @param array<string, mixed> $data
     */
    public function login(array $data): Response
    {
        return $this->runProcessor('MiniShop3\Processors\Api\Customer\Login', [
            'email' => $data['email'] ?? '',
            'password' => $data['password'] ?? '',
        ]);
    }

    /**
     * POST /api/v1/customer/register
     *
     * @param array<string, mixed> $data
     */
    public function register(array $data): Response
    {
        return $this->runProcessor('MiniShop3\Processors\Api\Customer\Register', [
            'email' => $data['email'] ?? '',
            'password' => $data['password'] ?? '',
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'phone' => $data['phone'] ?? '',
            'privacy_accepted' => !empty($data['privacy_accepted']),
        ]);
    }

    /**
     * POST /api/v1/customer/logout
     */
    public function logout(): Response
    {
        return $this->runProcessor('MiniShop3\Processors\Api\Customer\Logout', []);
    }

    /**
     * POST /api/v1/customer/forgot-password
     *
     * @param array<string, mixed> $data
     */
    public function forgotPassword(array $data): Response
    {
        return $this->runProcessor('MiniShop3\Processors\Api\Customer\ForgotPassword', [
            'email' => $data['email'] ?? '',
        ]);
    }

    /**
     * POST /api/v1/customer/reset-password
     *
     * @param array<string, mixed> $data
     */
    public function resetPassword(array $data): Response
    {
        return $this->runProcessor('MiniShop3\Processors\Api\Customer\ResetPassword', [
            'token' => $data['token'] ?? '',
            'password' => $data['password'] ?? '',
            'password_confirm' => $data['password_confirm'] ?? '',
        ]);
    }

    private function requestToken(): string
    {
        return TokenService::resolveTokenFromRequest();
    }

    private function sessionService(): CustomerSessionService
    {
        /** @var TokenService $tokenService */
        $tokenService = $this->modx->services->get('ms3_token_service');
        $ms3 = $this->modx->services->get('ms3');

        return new CustomerSessionService($this->modx, $tokenService, $ms3);
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function runProcessor(string $processorClass, array $properties): Response
    {
        $response = $this->modx->runProcessor($processorClass, $properties);

        if (!is_object($response)) {
            return Response::error(
                'Processor failed',
                HttpStatus::INTERNAL_SERVER_ERROR
            );
        }

        return Response::fromProcessor($response);
    }
}
