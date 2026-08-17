<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MiniShop3\Router\ApiErrorCode;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Customer\CustomerPublicDto;
use MiniShop3\Services\Validation\ValidationService;
use MODX\Revolution\modX;

/**
 * CustomerProfileController - Customer profile management API controller
 *
 * Handles customer personal data updates.
 *
 * Endpoints:
 * - PUT /api/v1/customer/profile - update profile
 *
 * @package MiniShop3\Controllers\Api\Web
 */
class CustomerProfileController
{
    /** @var modX */
    protected modX $modx;

    /** @var MiniShop3 */
    protected MiniShop3 $ms3;

    /**
     * @param modX $modx
     * @param MiniShop3 $ms3
     */
    public function __construct(modX $modx, MiniShop3 $ms3)
    {
        $this->modx = $modx;
        $this->ms3 = $ms3;
        $this->modx->lexicon->load('minishop3:customer');
    }

    /**
     * Update customer profile
     *
     * PUT /api/v1/customer/profile
     *
     * @param array $data Form data
     * @return Response
     */
    public function update(array $data): Response
    {
        if (empty($_SESSION['ms3']['customer_id'])) {
            return Response::error(
                $this->modx->lexicon('ms3_customer_err_login_required'),
                HttpStatus::UNAUTHORIZED
            );
        }

        /** @var msCustomer $customer */
        $customer = $this->getCurrentCustomer();

        if (!$customer) {
            return Response::error(
                $this->modx->lexicon('ms3_err_customer_nf'),
                HttpStatus::UNAUTHORIZED
            );
        }

        $customerId = (int)$customer->get('id');
        $this->ms3->loadMap();
        $editableKeys = CustomerPublicDto::editableFieldKeys($this->modx, $this->ms3);
        $data = array_intersect_key($data, array_flip($editableKeys));

        if ($data === []) {
            return $this->error($this->modx->lexicon('ms3_customer_err_validation'));
        }

        // Partial update: validate only the core profile rules for fields
        // actually present in $data. Missing core fields are left untouched
        // rather than rejected (#424 review).
        $rules = array_intersect_key($this->getProfileFieldRules(), $data);

        $validation = $this->getValidationService()->make($data, $rules);
        $validation->validate();

        if ($validation->fails()) {
            $errors = $validation->errors()->firstOfAll();
            $_SESSION['ms3']['customer_profile_errors'] = $errors;

            return $this->error(
                $this->modx->lexicon('ms3_customer_err_validation'),
                ['errors' => $errors]
            );
        }

        $fieldMeta = $this->modx->getFieldMeta(msCustomer::class);
        if (!is_array($fieldMeta) || $fieldMeta === []) {
            return $this->error($this->modx->lexicon('ms3_customer_err_save'));
        }

        foreach ($data as $key => $rawValue) {
            if ($key === 'email') {
                $newEmail = trim((string) $rawValue);
                if (!$this->isEmailAvailable($customer, $newEmail)) {
                    $emailError = $this->modx->lexicon('ms3_customer_err_email_exists');
                    $_SESSION['ms3']['customer_profile_errors'] = [
                        'email' => $emailError,
                    ];

                    return $this->error($emailError, ['errors' => ['email' => $emailError]]);
                }
                $this->resetEmailVerificationIfChanged($customer, $newEmail);
                $customer->set('email', $newEmail);
                continue;
            }

            if (isset($rules[$key])) {
                $customer->set($key, trim((string) $rawValue));
                continue;
            }

            $customer->set($key, $this->normalizeQuickProfileValue($rawValue, $fieldMeta[$key]));
        }

        if (!$customer->save()) {
            return $this->error($this->modx->lexicon('ms3_customer_err_save'));
        }

        $_SESSION['ms3']['customer_profile_success'] = true;

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[CustomerProfileController] Profile updated for customer #{$customerId}"
        );

        return $this->success(
            $this->modx->lexicon('ms3_customer_profile_updated'),
            ['customer' => CustomerPublicDto::fromCustomer($customer, $this->modx, $this->ms3)]
        );
    }

    /**
     * Update a single customer profile field.
     *
     * POST /api/v1/customer/add
     *
     * @param array $data Request data with key and value
     * @return Response
     */
    public function updateField(array $data): Response
    {
        if (empty($_SESSION['ms3']['customer_id'])) {
            return Response::error(
                $this->modx->lexicon('ms3_customer_err_login_required'),
                HttpStatus::UNAUTHORIZED
            );
        }

        $customer = $this->getCurrentCustomer();
        if (!$customer) {
            return Response::error(
                $this->modx->lexicon('ms3_err_customer_nf'),
                HttpStatus::UNAUTHORIZED
            );
        }

        $key = trim((string) ($data['key'] ?? ''));
        if ($key === '') {
            return $this->error($this->modx->lexicon('ms3_customer_key_empty'));
        }

        $this->ms3->loadMap();
        $editableKeys = CustomerPublicDto::editableFieldKeys($this->modx, $this->ms3);
        if (!in_array($key, $editableKeys, true)) {
            return $this->error($this->modx->lexicon('ms3_customer_err_field_not_allowed'));
        }

        $fieldMeta = $this->modx->getFieldMeta(msCustomer::class);
        if (!is_array($fieldMeta) || !isset($fieldMeta[$key])) {
            return $this->error($this->modx->lexicon('ms3_customer_err_field_not_allowed'));
        }

        $rules = $this->getProfileFieldRules();
        if (isset($rules[$key])) {
            $value = trim((string) ($data['value'] ?? ''));
            $validation = $this->getValidationService()->make([$key => $value], [$key => $rules[$key]]);
            $validation->validate();

            if ($validation->fails()) {
                $errors = $validation->errors()->firstOfAll();

                return $this->error(
                    $this->modx->lexicon('ms3_customer_err_validation'),
                    ['errors' => $errors]
                );
            }
        } else {
            $value = $this->normalizeQuickProfileValue($data['value'] ?? null, $fieldMeta[$key]);
        }

        if ($key === 'email' && !$this->isEmailAvailable($customer, (string) $value)) {
            $emailError = $this->modx->lexicon('ms3_customer_err_email_exists');

            return $this->error($emailError, ['errors' => ['email' => $emailError]]);
        }

        if ($key === 'email') {
            $this->resetEmailVerificationIfChanged($customer, (string) $value);
        }

        $customer->set($key, $value);

        if (!$customer->save()) {
            return $this->error($this->modx->lexicon('ms3_customer_err_save'));
        }

        return $this->success(
            $this->modx->lexicon('ms3_customer_profile_updated'),
            [
                $key => $customer->get($key),
                'customer' => CustomerPublicDto::fromCustomer($customer, $this->modx, $this->ms3),
            ]
        );
    }

    protected function getCurrentCustomer(): ?msCustomer
    {
        if (empty($_SESSION['ms3']['customer_id'])) {
            return null;
        }

        $customer = $this->modx->getObject(msCustomer::class, (int)$_SESSION['ms3']['customer_id']);

        return $customer instanceof msCustomer ? $customer : null;
    }

    protected function getProfileFieldRules(): array
    {
        return [
            'first_name' => 'required|min:2|max:100',
            'last_name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'phone' => 'required|min:10|max:20',
        ];
    }

    /**
     * Resolve the canonical validation service from MODX DI.
     */
    protected function getValidationService(): ValidationService
    {
        $service = $this->modx->services->get('ms3_validation_service');

        return $service instanceof ValidationService ? $service : new ValidationService();
    }

    /**
     * Coerce a single-field quick update value using xPDO field metadata (extra columns & OE fields).
     *
     * @param mixed $raw
     * @param array<string, mixed> $meta
     * @return mixed
     */
    private function normalizeQuickProfileValue(mixed $raw, array $meta): mixed
    {
        $phptype = isset($meta['phptype']) ? (string) $meta['phptype'] : 'string';

        return match ($phptype) {
            'integer' => (int) $raw,
            'float', 'double' => is_numeric($raw) ? (float) $raw : 0.0,
            'boolean' => $this->normalizeBooleanQuickValue($raw),
            default => trim((string) $raw),
        };
    }

    private function normalizeBooleanQuickValue(mixed $raw): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }
        $s = strtolower(trim((string) $raw));

        return in_array($s, ['1', 'true', 'yes', 'on'], true);
    }

    protected function isEmailAvailable(msCustomer $customer, string $email): bool
    {
        if ((string)$customer->get('email') === $email) {
            return true;
        }

        return !$this->modx->getObject(msCustomer::class, [
            'email' => $email,
            'id:!=' => $customer->get('id'),
        ]);
    }

    protected function resetEmailVerificationIfChanged(msCustomer $customer, string $email): void
    {
        $oldEmail = (string)$customer->get('email');
        if ($oldEmail === $email) {
            return;
        }

        $customer->set('email_verified_at', null);

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[CustomerProfileController] Email changed for customer #{$customer->get('id')}: {$oldEmail} → {$email}. Verification reset."
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function success(string $message = '', array $data = []): Response
    {
        return Response::success($data, $message);
    }

    /**
     * Profile validation / business errors.
     * Field map goes to top-level `errors`; also mirrored in `data.errors` for one-release BC (#572).
     *
     * @param array<string, mixed> $data
     */
    protected function error(string $message, array $data = []): Response
    {
        $fieldErrors = (isset($data['errors']) && is_array($data['errors'])) ? $data['errors'] : null;
        $isValidation = $fieldErrors !== null;

        return Response::errorWithCode(
            $isValidation ? ApiErrorCode::VALIDATION_FAILED : ApiErrorCode::BUSINESS_RULE,
            $message,
            $isValidation ? HttpStatus::UNPROCESSABLE_ENTITY : HttpStatus::BAD_REQUEST,
            $fieldErrors,
            $data !== [] ? $data : null,
        );
    }
}
