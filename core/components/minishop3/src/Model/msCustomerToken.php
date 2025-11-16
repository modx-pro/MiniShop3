<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Class msCustomerToken
 *
 * Модель для хранения токенов аутентификации клиентов
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
     * Типы токенов
     */
    const TYPE_API = 'api';
    const TYPE_REFRESH = 'refresh';
    const TYPE_MAGIC_LINK = 'magic_link';
    const TYPE_EMAIL_VERIFICATION = 'email_verification';

    /**
     * Проверка истечения срока действия токена
     *
     * @return bool True если токен истек
     */
    public function isExpired()
    {
        $expiresAt = strtotime($this->get('expires_at'));
        return $expiresAt < time();
    }

    /**
     * Проверка валидности токена
     *
     * @return bool True если токен валиден (не истек)
     */
    public function isValid()
    {
        return !$this->isExpired();
    }

    /**
     * Обновление времени последнего использования
     *
     * @return bool
     */
    public function markAsUsed()
    {
        $this->set('used_at', date('Y-m-d H:i:s'));
        return $this->save();
    }

    /**
     * Получение клиента по токену
     *
     * @return msCustomer|null
     */
    public function getCustomer()
    {
        return $this->getOne('Customer');
    }
}
