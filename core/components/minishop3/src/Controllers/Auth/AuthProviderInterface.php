<?php

namespace MiniShop3\Controllers\Auth;

use MiniShop3\Model\msCustomer;

/**
 * Interface AuthProviderInterface
 *
 * Defines contract for all authentication providers
 * (Password, SMS, OAuth, MagicLink, etc.)
 *
 * All custom authentication classes must implement this interface.
 * Pattern is similar to DeliveryProviderInterface.
 *
 * @package MiniShop3\Controllers\Auth
 */
interface AuthProviderInterface
{
    /**
     * Authenticate user with provided credentials
     *
     * @param array $credentials Authentication data (email/password, phone/code, etc.)
     * @return msCustomer|null Customer object on successful authentication, null on error
     */
    public function authenticate(array $credentials): ?msCustomer;

    /**
     * Get provider name
     *
     * @return string Unique provider name (password, sms, oauth_google, magic_link, etc.)
     */
    public function getName(): string;

    /**
     * Check if provider supports given credentials
     *
     * For example, PasswordAuthProvider will check for 'email' and 'password',
     * SmsAuthProvider will check for 'phone' and 'code'
     *
     * @param array $credentials Data to check
     * @return bool true if provider can handle these credentials
     */
    public function supports(array $credentials): bool;
}
