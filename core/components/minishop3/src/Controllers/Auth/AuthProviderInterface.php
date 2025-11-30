<?php

namespace MiniShop3\Controllers\Auth;

use MiniShop3\Model\msCustomer;

/**
 * Interface AuthProviderInterface
 *
 * Определяет контракт для всех провайдеров аутентификации
 * (Password, SMS, OAuth, MagicLink и т.д.)
 *
 * Все кастомные классы аутентификации должны реализовывать этот интерфейс.
 * Паттерн аналогичен DeliveryProviderInterface.
 *
 * @package MiniShop3\Controllers\Auth
 */
interface AuthProviderInterface
{
    /**
     * Аутентификация пользователя по предоставленным данным
     *
     * @param array $credentials Данные для аутентификации (email/password, phone/code и т.д.)
     * @return msCustomer|null Объект клиента при успешной аутентификации, null при ошибке
     */
    public function authenticate(array $credentials): ?msCustomer;

    /**
     * Получить название провайдера
     *
     * @return string Уникальное имя провайдера (password, sms, oauth_google, magic_link и т.д.)
     */
    public function getName(): string;

    /**
     * Проверить, поддерживает ли провайдер данные credentials
     *
     * Например, PasswordAuthProvider проверит наличие 'email' и 'password',
     * SmsAuthProvider проверит наличие 'phone' и 'code'
     *
     * @param array $credentials Данные для проверки
     * @return bool true, если провайдер может обработать эти данные
     */
    public function supports(array $credentials): bool;
}
