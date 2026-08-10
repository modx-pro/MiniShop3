<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\ModelField\ModelFieldSectionService;
use MiniShop3\Services\ModelField\ModelFieldService;
use MODX\Revolution\modX;

/**
 * API controller for managing model fields configuration.
 *
 * Thin HTTP layer: parse params → service → Response.
 *
 * @package MiniShop3\Controllers\Api\Manager
 */
class ModelFieldsController
{
    protected modX $modx;
    protected ModelFieldService $fieldService;
    protected ModelFieldSectionService $sectionService;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        /** @var ModelFieldService $fieldService */
        $fieldService = $modx->services->get('ms3_model_field_service');
        $this->fieldService = $fieldService;
        /** @var ModelFieldSectionService $sectionService */
        $sectionService = $modx->services->get('ms3_model_field_section_service');
        $this->sectionService = $sectionService;
    }

    /**
     * GET /api/mgr/model-fields
     */
    public function getList(array $params = []): array
    {
        return $this->mapResult($this->fieldService->getList($params));
    }

    /**
     * GET /api/mgr/model-fields/{id}
     */
    public function get(array $params = []): array
    {
        return $this->mapResult($this->fieldService->get($params));
    }

    /**
     * POST /api/mgr/model-fields
     */
    public function create(array $params = []): array
    {
        return $this->mapResult($this->fieldService->create($params));
    }

    /**
     * PUT /api/mgr/model-fields/{id}
     */
    public function update(array $params = []): array
    {
        return $this->mapResult($this->fieldService->update($params));
    }

    /**
     * DELETE /api/mgr/model-fields/{id}
     */
    public function delete(array $params = []): array
    {
        return $this->mapResult($this->fieldService->delete($params));
    }

    /**
     * GET /api/mgr/model-fields/models
     */
    public function getModels(): array
    {
        return $this->mapResult($this->fieldService->getModels());
    }

    /**
     * GET /api/mgr/model-fields/visible/{model}
     */
    public function getVisibleFields(array $params = []): array
    {
        return $this->mapResult($this->fieldService->getVisibleFields($params));
    }

    /**
     * PUT /api/mgr/model-fields/ranks
     */
    public function updateRanks(array $params = []): array
    {
        return $this->mapResult($this->fieldService->updateRanks($params));
    }

    /**
     * GET /api/mgr/model-fields/sections/{model}
     */
    public function getSections(array $params = []): array
    {
        return $this->mapResult($this->sectionService->getSections($params));
    }

    /**
     * POST /api/mgr/model-fields/sections
     */
    public function createSection(array $params = []): array
    {
        return $this->mapResult($this->sectionService->createSection($params));
    }

    /**
     * PUT /api/mgr/model-fields/sections/{id}
     */
    public function updateSection(array $params = []): array
    {
        return $this->mapResult($this->sectionService->updateSection($params));
    }

    /**
     * DELETE /api/mgr/model-fields/sections/{id}
     */
    public function deleteSection(array $params = []): array
    {
        return $this->mapResult($this->sectionService->deleteSection($params));
    }

    /**
     * PUT /api/mgr/model-fields/sections/ranks
     */
    public function updateSectionRanks(array $params = []): array
    {
        return $this->mapResult($this->sectionService->updateSectionRanks($params));
    }

    /**
     * GET /api/mgr/model-fields/combo-options/{model}
     */
    public function getComboOptions(array $params = []): array
    {
        return $this->mapResult($this->fieldService->getComboOptions($params));
    }

    /**
     * GET /api/mgr/model-fields/combo-options/{model}/{field_name}
     */
    public function getFieldComboOptions(array $params = []): array
    {
        return $this->mapResult($this->fieldService->getFieldComboOptions($params));
    }

    /**
     * Map a domain result array to the HTTP Response envelope.
     *
     * @param array{success: bool, data?: mixed, message?: string, error?: string, created?: bool} $result
     */
    private function mapResult(array $result): array
    {
        if (empty($result['success'])) {
            $status = match ((string)($result['error'] ?? 'bad_request')) {
                'not_found' => HttpStatus::NOT_FOUND,
                'server_error' => HttpStatus::INTERNAL_SERVER_ERROR,
                default => HttpStatus::BAD_REQUEST,
            };

            return Response::error(
                (string)($result['message'] ?? 'Request failed'),
                $status
            )->getData();
        }

        $status = !empty($result['created']) ? HttpStatus::CREATED : HttpStatus::OK;

        return Response::success(
            $result['data'] ?? null,
            $result['message'] ?? null,
            $status
        )->getData();
    }
}
