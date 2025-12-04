<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Class msCustomerToken
 *
 * Model for storing customer authentication tokens
 *
 * @property integer $id
 * @property integer $customer_id
 * @property string $token
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

    /**
     * Check if token is expired
     *
     * @return bool True if token is expired
     */
    public function isExpired()
    {
        $expiresAt = strtotime($this->get('expires_at'));
        return $expiresAt < time();
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
     * @return msCustomer|null
     */
    public function getCustomer()
    {
        return $this->getOne('Customer');
    }
}
