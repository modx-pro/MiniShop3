<?php

namespace MiniShop3\Services\Customer;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerToken;
use MODX\Revolution\modX;

/**
 * EmailVerificationService - сервис подтверждения email адресов
 *
 * Генерирует токены подтверждения и отправляет письма.
 * Проверяет токены и активирует email клиентов.
 *
 * Пример использования:
 * ```php
 * $emailService = $modx->services->get('ms3_email_verification_service');
 *
 * // Отправка письма с подтверждением
 * $emailService->sendVerificationEmail($customer);
 *
 * // Проверка токена из письма
 * $customer = $emailService->verifyToken($token);
 * if ($customer) {
 *     echo "Email подтвержден!";
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
     * Установить AuthManager для работы с токенами
     *
     * @param AuthManager $authManager
     * @return void
     */
    public function setAuthManager(AuthManager $authManager): void
    {
        $this->authManager = $authManager;
    }

    /**
     * Отправить письмо с подтверждением email
     *
     * @param msCustomer $customer
     * @return bool true при успехе
     */
    public function sendVerificationEmail(msCustomer $customer): bool
    {
        // Проверка, что email еще не подтвержден
        if ($customer->get('email_verified_at')) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[EmailVerificationService] Email already verified for customer #{$customer->id}"
            );
            return false;
        }

        // Удаляем старые токены подтверждения
        if ($this->authManager) {
            $this->authManager->revokeTokens($customer, 'email_verification');
        }

        // Создаем новый токен (срок действия 24 часа)
        $ttl = (int)$this->modx->getOption('ms3_email_verification_token_ttl', null, 86400);

        if ($this->authManager) {
            $tokenObj = $this->authManager->createToken($customer, 'email_verification', $ttl);
        } else {
            // Fallback если AuthManager не установлен
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

        // Генерируем ссылку подтверждения
        $siteUrl = $this->modx->getOption('site_url');
        $verificationUrl = $siteUrl . 'verify-email?token=' . $token;

        // Подготовка письма
        $email = $customer->get('email');
        $siteName = $this->modx->getOption('site_name');

        $subject = $this->modx->lexicon('ms3_email_verification_subject', ['site' => $siteName]);
        $body = $this->modx->lexicon('ms3_email_verification_body', [
            'first_name' => $customer->get('first_name') ?: 'Клиент',
            'url' => $verificationUrl,
            'site' => $siteName,
            'ttl_hours' => round($ttl / 3600),
        ]);

        // Отправка письма
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
     * Проверить токен подтверждения и активировать email
     *
     * @param string $token Токен из письма
     * @return msCustomer|null Клиент при успехе, null при ошибке
     */
    public function verifyToken(string $token): ?msCustomer
    {
        if ($this->authManager) {
            $customer = $this->authManager->validateToken($token, 'email_verification');
        } else {
            // Fallback если AuthManager не установлен
            /** @var msCustomerToken $tokenObj */
            $tokenObj = $this->modx->getObject(msCustomerToken::class, [
                'token' => $token,
                'type' => 'email_verification',
            ]);

            if (!$tokenObj) {
                return null;
            }

            // Проверка истечения
            if (strtotime($tokenObj->get('expires_at')) < time()) {
                $tokenObj->remove();
                return null;
            }

            // Проверка использования
            if ($tokenObj->get('used_at')) {
                return null;
            }

            $customer = $tokenObj->getOne('Customer');
            if (!$customer) {
                return null;
            }

            // Отмечаем использование
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

        // Активируем email
        $customer->set('email_verified_at', date('Y-m-d H:i:s'));
        $customer->save();

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[EmailVerificationService] Email verified for customer #{$customer->id}"
        );

        return $customer;
    }

    /**
     * Проверить, подтвержден ли email клиента
     *
     * @param msCustomer $customer
     * @return bool
     */
    public function isVerified(msCustomer $customer): bool
    {
        return !empty($customer->get('email_verified_at'));
    }

    /**
     * Повторная отправка письма с подтверждением
     *
     * @param msCustomer $customer
     * @return array ['success' => bool, 'message' => string]
     */
    public function resendVerificationEmail(msCustomer $customer): array
    {
        // Проверка, что email еще не подтвержден
        if ($this->isVerified($customer)) {
            return [
                'success' => false,
                'message' => $this->modx->lexicon('ms3_email_already_verified'),
            ];
        }

        // Rate limiting - не чаще 1 раза в 5 минут
        $lastSent = $_SESSION['ms3_email_verification_sent'][$customer->id] ?? 0;
        $cooldown = 300; // 5 минут

        if (time() - $lastSent < $cooldown) {
            $remaining = $cooldown - (time() - $lastSent);
            return [
                'success' => false,
                'message' => $this->modx->lexicon('ms3_email_verification_cooldown', ['seconds' => $remaining]),
            ];
        }

        // Отправка
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
