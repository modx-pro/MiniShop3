<?php

namespace MiniShop3\Processors\Api\Customer;

use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Customer\AuthManager;
use MiniShop3\Services\Customer\RateLimiter;
use MODX\Revolution\Processors\Processor;

/**
 * ForgotPassword - password recovery request processor
 *
 * Creates password reset token and sends email with instructions.
 * Protected from spam via RateLimiter.
 *
 * @package MiniShop3\Processors\Api\Customer
 */
class ForgotPassword extends Processor
{
    /**
     * @return array|string
     */
    public function process()
    {
        $email = trim($this->getProperty('email', ''));

        if (empty($email)) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_email_required'));
        }

        /** @var RateLimiter $rateLimiter */
        $rateLimiter = $this->modx->services->get('ms3_rate_limiter');

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!$rateLimiter->check('forgot_password', $ip, 3, 3600)) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_forgot_password_rate_limit'));
        }

        if (!$rateLimiter->check('forgot_password_email', $email, 1, 300)) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_forgot_password_email_cooldown'));
        }

        /** @var msCustomer $customer */
        $customer = $this->modx->getObject(msCustomer::class, ['email' => $email]);

        $message = $this->modx->lexicon('ms3_customer_forgot_password_success');

        if (!$customer) {
            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_DEBUG,
                "[ForgotPassword] Customer not found for email: {$email}"
            );
            return $this->success($message);
        }

        if (!$customer->get('is_active') || $customer->get('is_blocked')) {
            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_WARN,
                "[ForgotPassword] Customer #{$customer->id} is inactive or blocked"
            );
            return $this->success($message);
        }

        /** @var AuthManager $authManager */
        $authManager = $this->modx->services->get('ms3_auth_manager');

        $authManager->revokeTokens($customer, 'password_reset');

        $ttl = (int)$this->modx->getOption('ms3_password_reset_token_ttl', null, 3600);
        $tokenObj = $authManager->createToken($customer, 'password_reset', $ttl);

        if (!$tokenObj) {
            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_ERROR,
                "[ForgotPassword] Failed to create reset token for customer #{$customer->id}"
            );
            return $this->success($message);
        }

        $sent = $this->sendResetEmail($customer, $tokenObj->get('token'), $ttl);

        if (!$sent) {
            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_ERROR,
                "[ForgotPassword] Failed to send reset email to {$email}"
            );
        }

        return $this->success($message);
    }

    /**
     * Send password reset email with instructions
     *
     * @param msCustomer $customer
     * @param string $token
     * @param int $ttl
     * @return bool
     */
    protected function sendResetEmail(msCustomer $customer, string $token, int $ttl): bool
    {
        $email = $customer->get('email');
        $siteName = $this->modx->getOption('site_name');
        $siteUrl = $this->modx->getOption('site_url');

        $resetUrl = $siteUrl . 'reset-password?token=' . $token;

        $subject = $this->modx->lexicon('ms3_password_reset_subject', ['site' => $siteName]);
        $body = $this->modx->lexicon('ms3_password_reset_body', [
            'first_name' => $customer->get('first_name') ?: 'Customer',
            'url' => $resetUrl,
            'site' => $siteName,
            'ttl_minutes' => round($ttl / 60),
        ]);

        $this->modx->getService('mail', 'mail.modPHPMailer');
        $this->modx->mail->set(\modMail::MAIL_BODY, $body);
        $this->modx->mail->set(\modMail::MAIL_FROM, $this->modx->getOption('emailsender'));
        $this->modx->mail->set(\modMail::MAIL_FROM_NAME, $siteName);
        $this->modx->mail->set(\modMail::MAIL_SUBJECT, $subject);
        $this->modx->mail->address('to', $email);

        $sent = $this->modx->mail->send();
        $this->modx->mail->reset();

        if ($sent) {
            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_INFO,
                "[ForgotPassword] Reset email sent to {$email}"
            );
        }

        return $sent;
    }
}
