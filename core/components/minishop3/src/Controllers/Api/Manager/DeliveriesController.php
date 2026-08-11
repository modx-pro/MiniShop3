<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msDelivery;
use MiniShop3\Router\Response;
use MiniShop3\Services\Reference\Ms3ReferenceCrudService;
use MiniShop3\Services\Reference\ReferenceResourceConfig;
use MiniShop3\Services\Settings\SettingsComboListService;
use MODX\Revolution\modX;

/**
 * Manager API: deliveries CRUD + payment links (thin HTTP mapping).
 */
class DeliveriesController
{
    /**
     * Public fields for GET /api/mgr/deliveries-active (order-form dropdown).
     *
     * Must never include integration secrets: properties, class, validation_rules.
     */
    public const ACTIVE_DROPDOWN_FIELDS = [
        'id',
        'name',
        'price',
        'active',
        'position',
    ];

    protected modX $modx;
    protected Ms3ReferenceCrudService $crud;

    public function __construct(modX $modx, ?Ms3ReferenceCrudService $crud = null)
    {
        $this->modx = $modx;
        $this->crud = $crud ?? new Ms3ReferenceCrudService($modx, ReferenceResourceConfig::forDeliveries());
    }

    /**
     * Active deliveries for order-form dropdowns (no integration secrets).
     * GET /api/mgr/deliveries-active
     */
    public function getActiveDropdown(array $params = []): array
    {
        $this->modx->lexicon->load('minishop3:default');

        /** @var SettingsComboListService $comboList */
        $comboList = $this->modx->services->get('ms3_settings_combo_list');
        $results = [];
        foreach ($comboList->iterateActiveDeliveries(
            (int) ($params['id'] ?? 0),
            trim((string) ($params['query'] ?? ''))
        ) as $delivery) {
            $results[] = $this->formatActiveDropdownItem($delivery);
        }

        return Response::success(['results' => $results])->getData();
    }

    /**
     * Project a delivery row to the dropdown whitelist (no MODX required).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function projectActiveDropdownFields(array $data): array
    {
        return array_intersect_key($data, array_flip(self::ACTIVE_DROPDOWN_FIELDS));
    }

    public function getList(array $params = []): array
    {
        return $this->crud->getList($params);
    }

    public function get(array $params = []): array
    {
        return $this->crud->get($params);
    }

    public function create(array $data = []): array
    {
        return $this->crud->create($data);
    }

    public function update(array $data = []): array
    {
        return $this->crud->update($data);
    }

    public function delete(array $params = []): array
    {
        return $this->crud->delete($params);
    }

    public function sort(array $data = []): array
    {
        return $this->crud->sort($data);
    }

    public function bulkDelete(array $data = []): array
    {
        return $this->crud->bulkDelete($data);
    }

    public function updatePositions(array $data = []): array
    {
        return $this->crud->updatePositions($data);
    }

    public function getPayments(array $params = []): array
    {
        return $this->crud->listLinks($params);
    }

    public function addPayment(array $data = []): array
    {
        return $this->crud->addLink($data);
    }

    public function removePayment(array $params = []): array
    {
        return $this->crud->removeLink($params);
    }

    /**
     * Dropdown row: whitelist only + translated name.
     */
    protected function formatActiveDropdownItem(msDelivery $delivery): array
    {
        $data = [];
        foreach (self::ACTIVE_DROPDOWN_FIELDS as $field) {
            $raw = $delivery->get($field);
            $data[$field] = match ($field) {
                'id', 'position' => (int) $raw,
                'active' => (int) (bool) $raw,
                default => $raw,
            };
        }

        $name = (string) ($data['name'] ?? '');
        if ($name !== '' && str_starts_with($name, 'ms3_')) {
            $translated = $this->modx->lexicon($name);
            if ($translated !== $name) {
                $data['name'] = $translated;
            }
        }

        return $data;
    }
}
