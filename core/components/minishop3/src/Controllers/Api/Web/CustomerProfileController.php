<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Customer\CustomerPublicDto;
use MODX\Revolution\modX;
use Rakit\Validation\Validator;

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
    /**
     * Field names never editable via POST /api/v1/customer/add (security & system counters).
     *
     * @var list<string>
     */
    private const PROFILE_QUICK_UPDATE_FORBIDDEN = [
        'id',
        'token',
        'user_id',
        'password',
        'email_verified_at',
        'is_active',
        'is_blocked',
        'failed_login_attempts',
        'blocked_until',
        'created_at',
        'updated_at',
        'last_login_at',
        'orders_count',
        'total_spent',
        'last_order_at',
        'privacy_accepted_at',
        'privacy_ip',
    ];

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
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    public function update(array $data): array
    {
        if (empty($_SESSION['ms3']['customer_id'])) {
            return $this->error($this->modx->lexicon('ms3_customer_err_login_required'));
        }

        /** @var msCustomer $customer */
        $customer = $this->getCurrentCustomer();

        if (!$customer) {
            return $this->error($this->modx->lexicon('ms3_err_customer_nf'));
        }

        $customerId = (int)$customer->get('id');
        $validator = new Validator();
        $validation = $validator->make($data, $this->getProfileFieldRules());

        $validation->validate();

        if ($validation->fails()) {
            $errors = $validation->errors()->firstOfAll();
            $_SESSION['ms3']['customer_profile_errors'] = $errors;

            return $this->error(
                $this->modx->lexicon('ms3_customer_err_validation'),
                ['errors' => $errors]
            );
        }

        $newEmail = trim($data['email']);

        if (!$this->isEmailAvailable($customer, $newEmail)) {
            $_SESSION['ms3']['customer_profile_errors'] = [
                'email' => $this->modx->lexicon('ms3_customer_err_email_exists')
            ];
            return $this->error($this->modx->lexicon('ms3_customer_err_email_exists'));
        }

        $this->resetEmailVerificationIfChanged($customer, $newEmail);

        $customer->set('first_name', trim($data['first_name']));
        $customer->set('last_name', trim($data['last_name']));
        $customer->set('email', $newEmail);
        $customer->set('phone', trim($data['phone']));

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
            ['customer' => CustomerPublicDto::fromCustomer($customer)]
        );
    }

    /**
     * Update a single customer profile field.
     *
     * POST /api/v1/customer/add
     *
     * @param array $data Request data with key and value
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    public function updateField(array $data): array
    {
        if (empty($_SESSION['ms3']['customer_id'])) {
            return $this->error($this->modx->lexicon('ms3_customer_err_login_required'));
        }

        $customer = $this->getCurrentCustomer();
        if (!$customer) {
            return $this->error($this->modx->lexicon('ms3_err_customer_nf'));
        }

        $key = trim((string) ($data['key'] ?? ''));
        if ($key === '') {
            return $this->error($this->modx->lexicon('ms3_customer_key_empty'));
        }

        $this->ms3->loadMap();
        $fieldMeta = $this->modx->getFieldMeta(msCustomer::class);
        if (!is_array($fieldMeta) || $fieldMeta === []) {
            return $this->error($this->modx->lexicon('ms3_customer_err_field_not_allowed'));
        }

        if (in_array($key, self::PROFILE_QUICK_UPDATE_FORBIDDEN, true)) {
            return $this->error($this->modx->lexicon('ms3_customer_err_field_not_allowed'));
        }

        if (!isset($fieldMeta[$key])) {
            return $this->error($this->modx->lexicon('ms3_customer_err_field_not_allowed'));
        }

        $rules = $this->getProfileFieldRules();
        if (isset($rules[$key])) {
            $value = trim((string) ($data['value'] ?? ''));
            $validation = (new Validator())->make([$key => $value], [$key => $rules[$key]]);
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
            return $this->error($this->modx->lexicon('ms3_customer_err_email_exists'));
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
                'customer' => CustomerPublicDto::fromCustomer($customer),
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
     * Success response
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
     * Error response
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
