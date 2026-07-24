<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;
use MODX\Revolution\Processors\ProcessorResponse;

/**
 * CustomerAuthController — login, register, logout, password recovery (Web API).
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

    /**
     * @param array<string, mixed> $properties
     */
    private function runProcessor(string $processorClass, array $properties): Response
    {
        /** @var ProcessorResponse $response */
        $response = $this->modx->runProcessor($processorClass, $properties);

        return $this->toResponse($response);
    }

    private function toResponse(ProcessorResponse $response): Response
    {
        if ($response->isError()) {
            $payload = $response->getObject();
            $status = HttpStatus::BAD_REQUEST;
            if (is_array($payload) && isset($payload['code']) && is_numeric($payload['code'])) {
                $status = (int) $payload['code'];
            }

            return Response::error($response->getMessage(), $status);
        }

        return Response::success($response->getObject(), $response->getMessage());
    }
}
