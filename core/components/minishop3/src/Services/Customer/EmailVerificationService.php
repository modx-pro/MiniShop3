<?php

namespace MiniShop3\Services\Customer;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerToken;
use MODX\Revolution\Mail\modMail;
use MODX\Revolution\modX;

/**
 * EmailVerificationService - email address verification service
 *
 * Generates verification tokens and sends emails.
 * Validates tokens and activates customer emails.
 *
 * Example usage:
 * ```php
 * $emailService = $modx->services->get('ms3_email_verification_service');
 *
 * // Send verification email
 * $emailService->sendVerificationEmail($customer);
 *
 * // Verify token from email
 * $customer = $emailService->verifyToken($token);
 * if ($customer) {
 *     echo "Email verified!";
 * }
 * ```
 *
 * @package MiniShop3\Services
 */
class EmailVerificationService
{
    /** @var modX */
    protected modX $modx;

    /** @var AuthManager|null */
    protected ?AuthManager $authManager = null;

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Set AuthManager for token operations
     *
     * @param AuthManager $authManager
     * @return void
     */
    public function setAuthManager(AuthManager $authManager): void
    {
        $this->authManager = $authManager;
    }

    /**
     * Send email verification message
     *
     * @param msCustomer $customer
     * @return bool true on success
     */
    public function sendVerificationEmail(msCustomer $customer): bool
    {
        if ($customer->get('email_verified_at')) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[EmailVerificationService] Email already verified for customer #{$customer->id}"
            );
            return false;
        }

        if ($this->authManager) {
            $this->authManager->revokeTokens($customer, 'email_verification');
        }

        $ttl = (int)$this->modx->getOption('ms3_email_verification_token_ttl', null, 86400);

        if ($this->authManager) {
            $tokenObj = $this->authManager->createToken($customer, 'email_verification', $ttl);
        } else {
            /** @var msCustomerToken $tokenObj */
            $tokenObj = $this->modx->newObject(msCustomerToken::class);
            $tokenObj->set('customer_id', $customer->id);
            $tokenObj->set('token', bin2hex(random_bytes(64)));
            $tokenObj->set('type', 'email_verification');
            $tokenObj->set('expires_at', date('Y-m-d H:i:s', time() + $ttl));
            $tokenObj->save();
        }

        if (!$tokenObj) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[EmailVerificationService] Failed to create verification token for customer #{$customer->id}"
            );
            return false;
        }

        $token = $tokenObj->get('token');
        $verificationUrl = $this->buildEmailVerificationUrl($token);

        $email = $customer->get('email');
        $siteName = $this->modx->getOption('site_name');

        $subject = $this->modx->lexicon('ms3_email_verification_subject', ['site' => $siteName]);
        $body = $this->modx->lexicon('ms3_email_verification_body', [
            'first_name' => $customer->get('first_name') ?: 'Customer',
            'url' => $verificationUrl,
            'site' => $siteName,
            'ttl_hours' => round($ttl / 3600),
        ]);

        $this->modx->getService('mail', 'mail.modPHPMailer');
        $this->modx->mail->set(modMail::MAIL_BODY, $body);
        $this->modx->mail->set(modMail::MAIL_FROM, $this->modx->getOption('emailsender'));
        $this->modx->mail->set(modMail::MAIL_FROM_NAME, $siteName);
        $this->modx->mail->set(modMail::MAIL_SUBJECT, $subject);
        $this->modx->mail->address('to', $email);

        $sent = $this->modx->mail->send();
        $this->modx->mail->reset();

        if ($sent) {
            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                "[EmailVerificationService] Verification email sent to {$email}"
            );
            return true;
        }

        $this->modx->log(
            modX::LOG_LEVEL_ERROR,
            "[EmailVerificationService] Failed to send verification email to {$email}"
        );

        return false;
    }

    /**
     * Public URL for email confirmation (GET Web API, see routes/web.php).
     *
     * Optional system setting ms3_email_verification_url: use [[+token]] or {token} placeholder
     * for a custom page; leave empty to use api.php route (fixes GH-226 when verify-email page is absent).
     */
    protected function buildEmailVerificationUrl(string $token): string
    {
        $custom = trim((string) $this->modx->getOption('ms3_email_verification_url', null, ''));
        if ($custom !== '') {
            return str_replace(
                ['[[+token]]', '{token}'],
                [rawurlencode($token), rawurlencode($token)],
                $custom
            );
        }

        $assetsUrl = $this->modx->getOption(
            'ms3_assets_url',
            null,
            $this->modx->getOption('assets_url', null, '') . 'components/minishop3/'
        );
        $apiBase = rtrim($assetsUrl, '/') . '/api.php';
        if (!str_starts_with($apiBase, 'http://') && !str_starts_with($apiBase, 'https://') && !str_starts_with($apiBase, '//')) {
            $apiBase = rtrim($this->modx->getOption('site_url', null, ''), '/') . '/' . ltrim($apiBase, '/');
        }

        return self::buildDefaultVerifyRequestUrl($apiBase, $token, true);
    }

    /**
     * Собирает query для Web API подтверждения email (тесты и единая логика с письмом).
     *
     * @param string $apiPhpAbsoluteUrl Полный URL до api.php
     * @param bool   $includeHtmlParam  false — только JSON; true — в ссылку добавляется html=1 (редирект после клика в письме)
     */
    public static function buildDefaultVerifyRequestUrl(string $apiPhpAbsoluteUrl, string $token, bool $includeHtmlParam = true): string
    {
        $sep = str_contains($apiPhpAbsoluteUrl, '?') ? '&' : '?';
        $q = 'route=' . rawurlencode('/api/v1/customer/email/verify')
            . '&token=' . rawurlencode($token);
        if ($includeHtmlParam) {
            $q .= '&html=1';
        }

        return $apiPhpAbsoluteUrl . $sep . $q;
    }

    /**
     * Verify token and activate email
     *
     * @param string $token Token from email
     * @return msCustomer|null Customer on success, null on error
     */
    public function verifyToken(string $token): ?msCustomer
    {
        if ($this->authManager) {
            $customer = $this->authManager->validateToken($token, 'email_verification');
        } else {
            /** @var msCustomerToken $tokenObj */
            $tokenObj = $this->modx->getObject(msCustomerToken::class, [
                'token' => $token,
                'type' => 'email_verification',
            ]);

            if (!$tokenObj) {
                return null;
            }

            if (strtotime($tokenObj->get('expires_at')) < time()) {
                $tokenObj->remove();
                return null;
            }

            if ($tokenObj->get('used_at')) {
                return null;
            }

            $customer = $tokenObj->getOne('Customer');
            if (!$customer) {
                return null;
            }

            $tokenObj->set('used_at', date('Y-m-d H:i:s'));
            $tokenObj->save();
        }

        if (!$customer) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[EmailVerificationService] Invalid or expired token: {$token}"
            );
            return null;
        }

        $customer->set('email_verified_at', date('Y-m-d H:i:s'));
        $customer->save();

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[EmailVerificationService] Email verified for customer #{$customer->id}"
        );

        return $customer;
    }

    /**
     * Check if customer email is verified
     *
     * @param msCustomer $customer
     * @return bool
     */
    public function isVerified(msCustomer $customer): bool
    {
        return !empty($customer->get('email_verified_at'));
    }

    /**
     * Resend verification email
     *
     * @param msCustomer $customer
     * @return array ['success' => bool, 'message' => string]
     */
    public function resendVerificationEmail(msCustomer $customer): array
    {
        if ($this->isVerified($customer)) {
            return [
                'success' => false,
                'message' => $this->modx->lexicon('ms3_email_already_verified'),
            ];
        }

        $lastSent = $_SESSION['ms3_email_verification_sent'][$customer->id] ?? 0;
        $cooldown = 300;

        if (time() - $lastSent < $cooldown) {
            $remaining = $cooldown - (time() - $lastSent);
            return [
                'success' => false,
                'message' => $this->modx->lexicon('ms3_email_verification_cooldown', ['seconds' => $remaining]),
            ];
        }

        $sent = $this->sendVerificationEmail($customer);

        if ($sent) {
            $_SESSION['ms3_email_verification_sent'][$customer->id] = time();
            return [
                'success' => true,
                'message' => $this->modx->lexicon('ms3_email_verification_sent'),
            ];
        }

        return [
            'success' => false,
            'message' => $this->modx->lexicon('ms3_email_verification_send_failed'),
        ];
    }
}
