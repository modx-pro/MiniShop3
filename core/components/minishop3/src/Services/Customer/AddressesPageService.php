<?php

namespace MiniShop3\Services\Customer;

use MiniShop3\Model\msCustomerAddress;

/**
 * AddressesPageService - customer address management page service
 *
 * Displays list of saved addresses with CRUD functionality:
 * - View address list
 * - Add new address
 * - Edit existing address
 * - Delete address
 * - Set default address
 *
 * Example usage in snippet:
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
     * Get raw address page data
     *
     * @return array Address data
     */
    public function getData(): array
    {
        $mode = $_GET['mode'] ?? 'list';
        $addressId = isset($_GET['id']) ? (int)$_GET['id'] : null;

        $data = [
            'mode' => $mode,
            'customer' => $this->customer->toArray(),
        ];

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

                    if (empty($addressData['name'])) {
                        $parts = array_filter([
                            $addressData['city'],
                            $addressData['street'],
                            $addressData['building'] ? 'building ' . $addressData['building'] : null,
                            $addressData['room'] ? 'room ' . $addressData['room'] : null,
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
     * Render address management page
     *
     * @return string HTML content
     */
    public function render(): string
    {
        $mode = $_GET['mode'] ?? 'list';
        $addressId = isset($_GET['id']) ? (int)$_GET['id'] : null;

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
     * Render address list
     *
     * @return string HTML content
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

        $addresses = $this->modx->getIterator(msCustomerAddress::class, [
            'customer_id' => $this->customerId,
        ]);

        $pageUrl = $this->modx->makeUrl($this->modx->resource->get('id'), '', '', 'full');

        $addressesData = [];
        /** @var msCustomerAddress $address */
        foreach ($addresses as $address) {
            $addressData = $address->toArray();
            $addressData['page_url'] = $pageUrl;

            if (empty($addressData['name'])) {
                $parts = array_filter([
                    $addressData['city'],
                    $addressData['street'],
                    $addressData['building'] ? 'building ' . $addressData['building'] : null,
                    $addressData['room'] ? 'room ' . $addressData['room'] : null,
                ]);
                $addressData['display_name'] = implode(', ', $parts);
            } else {
                $addressData['display_name'] = $addressData['name'];
            }

            $chunk = $this->pdoFetch->getChunk($addressTpl, $addressData);
            $addressesData[] = is_string($chunk) ? $chunk : '';
        }

        $data = [
            'addresses' => implode("\n", $addressesData),
            'addresses_count' => count($addressesData),
            'customer' => $this->customer->toArray(),
            'success' => $_SESSION['ms3']['addresses_success'] ?? null,
            'error' => $_SESSION['ms3']['addresses_error'] ?? null,
            'page_url' => $pageUrl,
        ];

        unset($_SESSION['ms3']['addresses_success']);
        unset($_SESSION['ms3']['addresses_error']);

        $chunk = $this->pdoFetch->getChunk($tpl, $data);
        return is_string($chunk) ? $chunk : '';
    }

    /**
     * Render address creation form
     *
     * @return string HTML content
     */
    protected function renderCreateForm(): string
    {
        $tpl = $this->modx->getOption(
            'formTpl',
            $this->scriptProperties,
            'tpl.msCustomer.address.form'
        );

        $pageUrl = $this->modx->makeUrl($this->modx->resource->get('id'), '', '', 'full');

        $data = [
            'mode' => 'create',
            'address' => $_SESSION['ms3']['address_form_data'] ?? [],
            'errors' => $_SESSION['ms3']['address_form_errors'] ?? [],
            'customer' => $this->customer->toArray(),
            'page_url' => $pageUrl,
        ];

        unset($_SESSION['ms3']['address_form_data']);
        unset($_SESSION['ms3']['address_form_errors']);

        $chunk = $this->pdoFetch->getChunk($tpl, $data);
        return is_string($chunk) ? $chunk : '';
    }

    /**
     * Render address edit form
     *
     * @param int|null $addressId Address ID to edit
     * @return string HTML content
     */
    protected function renderEditForm(?int $addressId): string
    {
        if (!$addressId) {
            return $this->modx->lexicon('ms3_customer_err_address_not_found');
        }

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

        $pageUrl = $this->modx->makeUrl($this->modx->resource->get('id'), '', '', 'full');

        $data = [
            'mode' => 'edit',
            'address' => $address->toArray(),
            'errors' => $_SESSION['ms3']['address_form_errors'] ?? [],
            'customer' => $this->customer->toArray(),
            'page_url' => $pageUrl,
        ];

        unset($_SESSION['ms3']['address_form_errors']);

        $chunk = $this->pdoFetch->getChunk($tpl, $data);
        return is_string($chunk) ? $chunk : '';
    }
}
