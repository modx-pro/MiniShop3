<?php

namespace MiniShop3\Processors\Api\Customer;

use MiniShop3\Model\msCustomerToken;
use MiniShop3\Services\Customer\AuthManager;
use MiniShop3\Services\Customer\RateLimiter;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Utils\CookieHelper;
use MODX\Revolution\Processors\Processor;

/**
 * Login - customer login processor
 *
 * Authenticates customer and binds existing session token to customer.
 * Token does NOT change on login — guest cart is preserved.
 * Protected from brute-force via RateLimiter.
 *
 * @package MiniShop3\Processors\Api\Customer
 */
class Login extends Processor
{
    /**
     * @return array|string
     */
    public function process()
    {
        $this->modx->lexicon->load('minishop3:customer');

        $email = trim($this->getProperty('email', ''));
        $password = $this->getProperty('password', '');

        if (empty($email) || empty($password)) {
            return $this->failure($this->modx->lexicon('ms3_customer_err_login_required'));
        }

        /** @var RateLimiter $rateLimiter */
        $rateLimiter = $this->modx->services->get('ms3_rate_limiter');

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $maxAttempts = 5;
        $windowSeconds = 300;

        if (!$rateLimiter->check('login', $ip, $maxAttempts, $windowSeconds)) {
            $attempts = $rateLimiter->getAttempts('login', $ip);
            return $this->failure(
                $this->modx->lexicon('ms3_customer_err_login_rate_limit', [
                    'attempts' => $attempts,
                    'max' => $maxAttempts,
                    'minutes' => round($windowSeconds / 60)
                ])
            );
        }

        /** @var AuthManager $authManager */
        $authManager = $this->modx->services->get('ms3_auth_manager');

        $customer = $authManager->authenticate([
            'email' => $email,
            'password' => $password,
        ]);

        if (!$customer) {
            $this->modx->log(
                \MODX\Revolution\modX::LOG_LEVEL_WARN,
                "[Login] Failed login attempt for email: {$email} from IP: {$ip}"
            );

            return $this->failure($this->modx->lexicon('ms3_customer_err_login_invalid'));
        }

        $rateLimiter->reset('login', $ip);

        // Use existing token from cookie/session instead of creating new one
        $currentToken = CookieHelper::getTokenFromCookie();
        if (empty($currentToken)) {
            $currentToken = $_SESSION['ms3']['customer_token'] ?? '';
        }

        $tokenObj = null;
        $tokenString = '';
        $expiresAt = '';

        if (!empty($currentToken)) {
            // Find existing token in DB
            $tokenObj = $this->modx->getObject(msCustomerToken::class, [
                'token' => $currentToken,
                'type' => msCustomerToken::TYPE_API,
            ]);

            if ($tokenObj) {
                // Bind customer to existing token
                $tokenObj->set('customer_id', $customer->id);

                // Extend TTL
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

        // Edge case: no valid existing token — create new one
        if (!$tokenObj) {
            $ttl = (int)$this->modx->getOption('ms3_customer_token_ttl', null, 604800);
            $tokenObj = $authManager->createToken($customer, 'api', $ttl);

            if (!$tokenObj) {
                return $this->failure($this->modx->lexicon('ms3_customer_err_token_create'));
            }

            $tokenString = $tokenObj->get('token');
            $expiresAt = $tokenObj->get('expires_at');

            // Set cookie for new token
            CookieHelper::setTokenCookie($this->modx, $tokenString);
        }

        // Update session
        if (!isset($_SESSION['ms3'])) {
            $_SESSION['ms3'] = [];
        }
        $_SESSION['ms3']['customer_id'] = $customer->id;
        $_SESSION['ms3']['customer_token'] = $tokenString;
        $_SESSION['ms3']['customer_token_expires'] = strtotime($expiresAt);

        $redirectPageId = (int)$this->getProperty('redirect_page_id', 0);
        if (!$redirectPageId) {
            $redirectPageId = (int)$this->modx->getOption('ms3_customer_redirect_after_login', null, 0);
        }

        $redirectUrl = '';
        if ($redirectPageId > 0) {
            $redirectUrl = $this->modx->makeUrl($redirectPageId, '', '', 'full');
        }

        return $this->success('', [
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
            'redirect_url' => $redirectUrl,
        ]);
    }

}
