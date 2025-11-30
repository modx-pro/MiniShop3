<?php

namespace MiniShop3\Services\Customer;

use MiniShop3\Model\msCustomerAddress;

/**
 * AddressesPageService - сервис страницы управления адресами клиента
 *
 * Отображает список сохранённых адресов с CRUD функционалом:
 * - Просмотр списка адресов
 * - Добавление нового адреса
 * - Редактирование существующего
 * - Удаление адреса
 * - Установка адреса по умолчанию
 *
 * Пример использования в сниппете:
 * ```php
 * [[!msCustomer?
 *   &service=`addresses`
 *   &tpl=`ms3_customer_addresses`
 *   &addressTpl=`ms3_customer_address_row`
 * ]]
 * ```
 *
 * @package MiniShop3\Services\Customer
 */
class AddressesPageService extends CustomerPageService
{
    /**
     * Получить сырые данные страницы адресов
     *
     * @return array Данные адресов
     */
    public function getData(): array
    {
        // Получить режим отображения (list, edit, create)
        $mode = $_GET['mode'] ?? 'list';
        $addressId = isset($_GET['id']) ? (int)$_GET['id'] : null;

        // Базовые данные
        $data = [
            'mode' => $mode,
            'customer' => $this->customer->toArray(),
        ];

        // Данные в зависимости от режима
        switch ($mode) {
            case 'edit':
                if ($addressId) {
                    /** @var \MiniShop3\Model\msCustomerAddress $address */
                    $address = $this->modx->getObject(\MiniShop3\Model\msCustomerAddress::class, [
                        'id' => $addressId,
                        'customer_id' => $this->customerId,
                    ]);

                    if ($address) {
                        $data['address'] = $address->toArray();
                        $data['errors'] = $_SESSION['ms3']['address_form_errors'] ?? [];
                    } else {
                        $data['error'] = $this->modx->lexicon('ms3_customer_err_address_not_found');
                    }
                }
                break;

            case 'create':
                $data['address'] = $_SESSION['ms3']['address_form_data'] ?? [];
                $data['errors'] = $_SESSION['ms3']['address_form_errors'] ?? [];
                break;

            default: // list
                $addresses = $this->modx->getIterator(\MiniShop3\Model\msCustomerAddress::class, [
                    'customer_id' => $this->customerId,
                ]);

                $addressesData = [];
                /** @var \MiniShop3\Model\msCustomerAddress $address */
                foreach ($addresses as $address) {
                    $addressData = $address->toArray();

                    // Сформировать человекочитаемое название адреса
                    if (empty($addressData['name'])) {
                        $parts = array_filter([
                            $addressData['city'],
                            $addressData['street'],
                            $addressData['building'] ? 'д. ' . $addressData['building'] : null,
                            $addressData['room'] ? 'кв. ' . $addressData['room'] : null,
                        ]);
                        $addressData['display_name'] = implode(', ', $parts);
                    } else {
                        $addressData['display_name'] = $addressData['name'];
                    }

                    $addressesData[] = $addressData;
                }

                $data['addresses'] = $addressesData;
                $data['addresses_count'] = count($addressesData);
                $data['success'] = $_SESSION['ms3']['addresses_success'] ?? null;
                $data['error'] = $_SESSION['ms3']['addresses_error'] ?? null;
                break;
        }

        return $data;
    }

    /**
     * Рендерить страницу управления адресами
     *
     * @return string HTML содержимое
     */
    public function render(): string
    {
        // Получить режим отображения (list, edit, create)
        $mode = $_GET['mode'] ?? 'list';
        $addressId = isset($_GET['id']) ? (int)$_GET['id'] : null;

        // Рендеринг в зависимости от режима
        switch ($mode) {
            case 'edit':
                return $this->renderEditForm($addressId);
            case 'create':
                return $this->renderCreateForm();
            default:
                return $this->renderList();
        }
    }

    /**
     * Рендерить список адресов
     *
     * @return string HTML содержимое
     */
    protected function renderList(): string
    {
        $tpl = $this->modx->getOption(
            'tpl',
            $this->scriptProperties,
            'tpl.msCustomer.addresses'
        );

        $addressTpl = $this->modx->getOption(
            'addressTpl',
            $this->scriptProperties,
            'tpl.msCustomer.address.row'
        );

        // Получить все адреса клиента
        $addresses = $this->modx->getIterator(msCustomerAddress::class, [
            'customer_id' => $this->customerId,
        ]);

        $addressesData = [];
        /** @var msCustomerAddress $address */
        foreach ($addresses as $address) {
            $addressData = $address->toArray();

            // Сформировать человекочитаемое название адреса
            if (empty($addressData['name'])) {
                $parts = array_filter([
                    $addressData['city'],
                    $addressData['street'],
                    $addressData['building'] ? 'д. ' . $addressData['building'] : null,
                    $addressData['room'] ? 'кв. ' . $addressData['room'] : null,
                ]);
                $addressData['display_name'] = implode(', ', $parts);
            } else {
                $addressData['display_name'] = $addressData['name'];
            }

            // Рендеринг строки адреса
            $chunk = $this->pdoFetch->getChunk($addressTpl, $addressData);
            $addressesData[] = is_string($chunk) ? $chunk : '';
        }

        // Подготовить данные для шаблона
        $data = [
            'addresses' => implode("\n", $addressesData),
            'addresses_count' => count($addressesData),
            'customer' => $this->customer->toArray(),
            'success' => $_SESSION['ms3']['addresses_success'] ?? null,
            'error' => $_SESSION['ms3']['addresses_error'] ?? null,
        ];

        // Очистить flash-сообщения
        unset($_SESSION['ms3']['addresses_success']);
        unset($_SESSION['ms3']['addresses_error']);

        $chunk = $this->pdoFetch->getChunk($tpl, $data);
        return is_string($chunk) ? $chunk : '';
    }

    /**
     * Рендерить форму создания адреса
     *
     * @return string HTML содержимое
     */
    protected function renderCreateForm(): string
    {
        $tpl = $this->modx->getOption(
            'formTpl',
            $this->scriptProperties,
            'tpl.msCustomer.address.form'
        );

        $data = [
            'mode' => 'create',
            'address' => $_SESSION['ms3']['address_form_data'] ?? [],
            'errors' => $_SESSION['ms3']['address_form_errors'] ?? [],
            'customer' => $this->customer->toArray(),
        ];

        // Очистить данные формы
        unset($_SESSION['ms3']['address_form_data']);
        unset($_SESSION['ms3']['address_form_errors']);

        $chunk = $this->pdoFetch->getChunk($tpl, $data);
        return is_string($chunk) ? $chunk : '';
    }

    /**
     * Рендерить форму редактирования адреса
     *
     * @param int|null $addressId ID адреса для редактирования
     * @return string HTML содержимое
     */
    protected function renderEditForm(?int $addressId): string
    {
        if (!$addressId) {
            return $this->modx->lexicon('ms3_customer_err_address_not_found');
        }

        // Загрузить адрес и проверить владельца
        /** @var msCustomerAddress $address */
        $address = $this->modx->getObject(msCustomerAddress::class, [
            'id' => $addressId,
            'customer_id' => $this->customerId,
        ]);

        if (!$address) {
            return $this->modx->lexicon('ms3_customer_err_address_not_found');
        }

        $tpl = $this->modx->getOption(
            'formTpl',
            $this->scriptProperties,
            'tpl.msCustomer.address.form'
        );

        $data = [
            'mode' => 'edit',
            'address' => $address->toArray(),
            'errors' => $_SESSION['ms3']['address_form_errors'] ?? [],
            'customer' => $this->customer->toArray(),
        ];

        // Очистить ошибки формы
        unset($_SESSION['ms3']['address_form_errors']);

        $chunk = $this->pdoFetch->getChunk($tpl, $data);
        return is_string($chunk) ? $chunk : '';
    }
}
