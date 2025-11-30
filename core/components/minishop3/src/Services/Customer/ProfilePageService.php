<?php

namespace MiniShop3\Services\Customer;

/**
 * ProfilePageService - сервис страницы профиля клиента
 *
 * Отображает личные данные клиента с возможностью редактирования.
 * Показывает статус подтверждения email и кнопку повторной отправки письма.
 *
 * Пример использования в сниппете:
 * ```php
 * [[!msCustomer?
 *   &service=`profile`
 *   &tpl=`ms3_customer_profile`
 * ]]
 * ```
 *
 * @package MiniShop3\Services\Customer
 */
class ProfilePageService extends CustomerPageService
{
    /**
     * Получить сырые данные профиля
     *
     * @return array Данные профиля клиента
     */
    public function getData(): array
    {
        // Получить данные клиента
        $customerData = $this->customer->toArray();

        // Проверить статус подтверждения email
        $emailVerified = !empty($customerData['email_verified_at']);
        $emailVerifiedAt = $emailVerified
            ? date('d.m.Y H:i', strtotime($customerData['email_verified_at']))
            : null;

        // Проверить статус подтверждения телефона (будущая функциональность)
        $phoneVerified = !empty($customerData['phone_verified_at']);
        $phoneVerifiedAt = $phoneVerified
            ? date('d.m.Y H:i', strtotime($customerData['phone_verified_at']))
            : null;

        // Подготовить данные
        return [
            'customer' => $customerData,
            'email_verified' => $emailVerified,
            'email_verified_at' => $emailVerifiedAt,
            'phone_verified' => $phoneVerified,
            'phone_verified_at' => $phoneVerifiedAt,
            'errors' => $_SESSION['ms3']['customer_profile_errors'] ?? [],
            'success' => $_SESSION['ms3']['customer_profile_success'] ?? false,
        ];
    }

    /**
     * Рендерить страницу профиля
     *
     * @return string HTML содержимое
     */
    public function render(): string
    {
        $tpl = $this->modx->getOption(
            'tpl',
            $this->scriptProperties,
            'tpl.msCustomer.profile'
        );

        // Получить данные через метод getData()
        $data = $this->getData();

        // Очистить flash-сообщения после отображения
        unset($_SESSION['ms3']['customer_profile_errors']);
        unset($_SESSION['ms3']['customer_profile_success']);

        $chunk = $this->pdoFetch->getChunk($tpl, $data);
        return is_string($chunk) ? $chunk : '';
    }
}
