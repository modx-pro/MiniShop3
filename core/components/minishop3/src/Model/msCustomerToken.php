<?php

namespace MiniShop3\Model;

use MiniShop3\Utils\ApiTokenExpiry;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOSimpleObject;

/**
 * Class msCustomerToken
 *
 * Model for storing customer authentication tokens
 *
 * @property integer $id
 * @property integer $customer_id
 * @property string $token Legacy plaintext column: empty for tokens minted after #927
 * @property string|null $token_hash SHA-256 hex hash of the token (#927)
 * @property string $type
 * @property string $expires_at
 * @property string $created_at
 * @property string $used_at
 *
 * @package MiniShop3\Model
 */
class msCustomerToken extends xPDOSimpleObject
{
    /**
     * Token types
     */
    const TYPE_API = 'api';
    const TYPE_REFRESH = 'refresh';
    const TYPE_MAGIC_LINK = 'magic_link';
    const TYPE_EMAIL_VERIFICATION = 'email_verification';
    const TYPE_PASSWORD_RESET = 'password_reset';

    /**
     * Plaintext of the token for the current request only (#927).
     *
     * Set when the token is minted or presented by the client and resolved
     * against the DB. Never persisted: save() writes only mapped fields,
     * and the DB keeps just the SHA-256 hash.
     */
    private ?string $plainToken = null;

    /**
     * Hold the request plaintext so get('token') can return it to callers
     * (API response, email link, cookie/session) without storing it in the DB.
     */
    public function setPlainToken(string $token): void
    {
        $this->plainToken = $token;
    }

    public function getPlainToken(): ?string
    {
        return $this->plainToken;
    }

    /**
     * get('token') returns the request plaintext when available (freshly
     * minted or resolved token), otherwise the stored legacy column value.
     *
     * @param string $k
     * @param string|null $format
     * @param string|null $formatTemplate
     * @return mixed
     */
    public function get($k, $format = null, $formatTemplate = null)
    {
        if ($k === 'token' && $this->plainToken !== null) {
            return $this->plainToken;
        }

        return parent::get($k, $format, $formatTemplate);
    }

    /**
     * Check if token is expired
     *
     * @return bool True if token is expired
     */
    public function isExpired()
    {
        return ApiTokenExpiry::isExpiresAtBefore((string) $this->get('expires_at'), time());
    }

    /**
     * Check token validity
     *
     * @return bool True if token is valid (not expired)
     */
    public function isValid()
    {
        return !$this->isExpired();
    }

    /**
     * Update last used time
     *
     * @return bool
     */
    public function markAsUsed()
    {
        $this->set('used_at', date('Y-m-d H:i:s'));
        return $this->save();
    }

    /**
     * Get customer by token
     *
     * @return msCustomer|xPDOObject|null
     */
    public function getCustomer()
    {
        return $this->getOne('Customer');
    }
}
