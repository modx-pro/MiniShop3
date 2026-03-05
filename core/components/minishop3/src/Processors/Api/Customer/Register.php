<?php

namespace MiniShop3\Processors\Api\Customer;

use MiniShop3\Model\msCustomerToken;
use MiniShop3\Services\Customer\AuthManager;
use MiniShop3\Services\Customer\EmailVerificationService;
use MiniShop3\Services\Customer\RateLimiter;
use MiniShop3\Services\Customer\RegisterService;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Utils\CookieHelper;
use MODX\Revolution\Processors\Processor;

/**
 * Register - processor for registering a new customer
 *
 * Creates a new customer with validation and optional email verification.
 * On auto-login: binds existing session token to new customer (preserves guest cart).
 * Protected from spam via RateLimiter.
 *
 * @package MiniShop3\Processors\Api\Customer
 */
class Register extends Processor
{
    /**
     * @return array|string
     */
    public function process()
    {
        $this->modx->lexicon->load('minishop3:customer');

        $email = trim($this->getProperty('email', ''));
        $password = $this->getProperty('password', '');
        $firstName = trim($this->getProperty('first_name', ''));
        $lastName = trim($this->getProperty('last_name', ''));
        $phone = trim($this->getProperty('phone', ''));
        $privacyAccepted = (bool)$this->getProperty('privacy_accepted', false);

        if (empty($email)) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_email_required'));
        }

        if (empty($password)) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_password_required'));
        }

        $requirePrivacy = (bool)$this->modx->getOption('ms3_customer_require_privacy_consent', null, true);
        if ($requirePrivacy && !$privacyAccepted) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_privacy_required'));
        }

        /** @var RateLimiter $rateLimiter */
        $rateLimiter = $this->modx->services->get('ms3_rate_limiter');

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!$rateLimiter->check('register', $ip, 3, 3600)) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_register_rate_limit'));
        }

        /** @var RegisterService $registerService */
        $registerService = $this->modx->services->get('ms3_register_service');

        /** @var EmailVerificationService $emailVerification */
        $emailVerification = $this->modx->services->get('ms3_email_verification_service');
        $registerService->setEmailVerification($emailVerification);

        $result = $registerService->register([
            'email' => $email,
            'password' => $password,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'privacy_accepted' => $privacyAccepted,
        ]);

        if (!$result['success']) {
            return $this->failure($result['message']);
        }

        $customer = $result['customer'];

        $autoLogin = (bool)$this->modx->getOption('ms3_customer_auto_login_after_register', null, true);
        $requireEmailVerification = (bool)$this->modx->getOption('ms3_customer_require_email_verification', null, true);

        $tokenString = null;
        $expiresAt = null;

        if ($autoLogin && !$requireEmailVerification) {
            // Use existing token from cookie/session instead of creating new one
            $currentToken = CookieHelper::getTokenFromCookie();
            if (empty($currentToken)) {
                $currentToken = $_SESSION['ms3']['customer_token'] ?? '';
            }

            $tokenObj = null;

            if (!empty($currentToken)) {
                $tokenObj = $this->modx->getObject(msCustomerToken::class, [
                    'token' => $currentToken,
                    'type' => msCustomerToken::TYPE_API,
                ]);

                if ($tokenObj) {
                    // Bind customer to existing token
                    $tokenObj->set('customer_id', $customer->id);

                    $ttl = (int)$this->modx->getOption('ms3_customer_token_ttl', null, 604800);
                    $tokenObj->set('expires_at', date('Y-m-d H:i:s', time() + $ttl));
                    $tokenObj->save();

                    $tokenString = $tokenObj->get('token');
                    $expiresAt = $tokenObj->get('expires_at');

                    // Bind draft order to customer
                    /** @var OrderDraftManager $draftManager */
                    $draftManager = $this->modx->services->get('ms3_order_draft_manager');
                    $draftManager->bindDraftToCustomer($tokenString, $customer->id);
                }
            }

            // Edge case: no valid existing token
            if (!$tokenObj) {
                /** @var AuthManager $authManager */
                $authManager = $this->modx->services->get('ms3_auth_manager');
                $ttl = (int)$this->modx->getOption('ms3_customer_token_ttl', null, 604800);
                $tokenObj = $authManager->createToken($customer, 'api', $ttl);

                if ($tokenObj) {
                    $tokenString = $tokenObj->get('token');
                    $expiresAt = $tokenObj->get('expires_at');
                    CookieHelper::setTokenCookie($this->modx, $tokenString);
                }
            }

            if ($tokenString) {
                if (!isset($_SESSION['ms3'])) {
                    $_SESSION['ms3'] = [];
                }
                $_SESSION['ms3']['customer_id'] = $customer->id;
                $_SESSION['ms3']['customer_token'] = $tokenString;
                $_SESSION['ms3']['customer_token_expires'] = strtotime($expiresAt);
            }
        }

        $rateLimiter->reset('login', $ip);

        $redirectUrl = '';
        if ($autoLogin && !$requireEmailVerification) {
            $redirectPageId = (int)$this->getProperty('redirect_page_id', 0);
            if (!$redirectPageId) {
                $redirectPageId = (int)$this->modx->getOption('ms3_customer_redirect_after_login', null, 0);
            }

            if ($redirectPageId > 0) {
                $redirectUrl = $this->modx->makeUrl($redirectPageId, '', '', 'full');
            }
        }

        return $this->success($this->modx->lexicon('ms3_customer_register_success'), [
            'customer' => [
                'id' => $customer->id,
                'email' => $customer->get('email'),
                'first_name' => $customer->get('first_name'),
                'last_name' => $customer->get('last_name'),
                'phone' => $customer->get('phone'),
                'email_verified' => !empty($customer->get('email_verified_at')),
            ],
            'token' => $tokenString,
            'expires_at' => $expiresAt,
            'email_verification_required' => $requireEmailVerification,
            'redirect_url' => $redirectUrl,
        ]);
    }

}
