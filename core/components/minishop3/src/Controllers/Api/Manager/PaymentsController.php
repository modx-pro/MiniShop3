<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Services\Reference\Ms3ReferenceCrudService;
use MiniShop3\Services\Reference\ReferenceResourceConfig;
use MODX\Revolution\modX;

/**
 * Manager API: payments CRUD + delivery links (thin HTTP mapping).
 */
class PaymentsController
{
    protected Ms3ReferenceCrudService $crud;

    public function __construct(modX $modx, ?Ms3ReferenceCrudService $crud = null)
    {
        $this->crud = $crud ?? new Ms3ReferenceCrudService($modx, ReferenceResourceConfig::forPayments());
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

    public function getDeliveries(array $params = []): array
    {
        return $this->crud->listLinks($params);
    }

    public function addDelivery(array $data = []): array
    {
        return $this->crud->addLink($data);
    }

    public function removeDelivery(array $params = []): array
    {
        return $this->crud->removeLink($params);
    }
}
