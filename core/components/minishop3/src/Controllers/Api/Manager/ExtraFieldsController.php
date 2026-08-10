<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\ExtraFieldsService;
use MODX\Revolution\modX;

/**
 * Manager API for extra-fields CRUD (Vue ExtraFieldsManager).
 *
 * @package MiniShop3\Controllers\Api\Manager
 */
class ExtraFieldsController
{
    protected modX $modx;

    protected ExtraFieldsService $service;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        /** @var ExtraFieldsService $service */
        $service = $modx->services->get('ms3_extra_fields');
        $this->service = $service;
    }

    /**
     * GET /api/mgr/extra-fields
     */
    public function getList(array $params = []): array
    {
        try {
            $class = $params['class'] ?? null;
            $criteria = $class ? ['class' => $class] : [];
            $fields = $this->service->getFields($criteria);

            return Response::success([
                'fields' => $fields,
                'total' => count($fields),
            ])->getData();
        } catch (\Exception $e) {
            $this->logError($e);

            return Response::error(
                'Failed to load extra fields: ' . $e->getMessage(),
                HttpStatus::INTERNAL_SERVER_ERROR
            )->getData();
        }
    }

    /**
     * GET /api/mgr/extra-fields/{id}
     */
    public function get(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Field ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $field = $this->service->getField($id);

        if ($field === null) {
            return Response::error('Field not found', HttpStatus::NOT_FOUND)->getData();
        }

        return Response::success(['field' => $field])->getData();
    }

    /**
     * POST /api/mgr/extra-fields
     */
    public function create(): array
    {
        try {
            $data = $this->readJsonBody();

            if ($data === []) {
                return Response::error('Request body is empty', HttpStatus::BAD_REQUEST)->getData();
            }

            $result = $this->service->createField($data);

            if (!$result['success']) {
                return Response::error($result['message'], HttpStatus::BAD_REQUEST)->getData();
            }

            return Response::success([
                'message' => $result['message'],
                'field' => $result['data'],
                'migration' => $result['migration'],
            ])->getData();
        } catch (\Exception $e) {
            $this->logError($e);

            return Response::error(
                'Failed to create field: ' . $e->getMessage(),
                HttpStatus::INTERNAL_SERVER_ERROR
            )->getData();
        }
    }

    /**
     * PUT /api/mgr/extra-fields/{id}
     */
    public function update(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Field ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        try {
            $data = $this->readJsonBody();

            if ($data === []) {
                return Response::error('Request body is empty', HttpStatus::BAD_REQUEST)->getData();
            }

            $result = $this->service->updateField($id, $data);

            if (!$result['success']) {
                return Response::error($result['message'], HttpStatus::BAD_REQUEST)->getData();
            }

            return Response::success([
                'message' => $result['message'],
                'field' => $result['data'],
            ])->getData();
        } catch (\Exception $e) {
            $this->logError($e);

            return Response::error(
                'Failed to update field: ' . $e->getMessage(),
                HttpStatus::INTERNAL_SERVER_ERROR
            )->getData();
        }
    }

    /**
     * DELETE /api/mgr/extra-fields/{id}
     */
    public function delete(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);

        if (!$id) {
            return Response::error('Field ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        try {
            $result = $this->service->deleteField($id);

            if (!$result['success']) {
                return Response::error($result['message'], HttpStatus::BAD_REQUEST)->getData();
            }

            return Response::success([
                'message' => $result['message'],
                'migration' => $result['migration'],
            ])->getData();
        } catch (\Exception $e) {
            $this->logError($e);

            return Response::error(
                'Failed to delete field: ' . $e->getMessage(),
                HttpStatus::INTERNAL_SERVER_ERROR
            )->getData();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonBody(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);

        return is_array($data) ? $data : [];
    }

    private function logError(\Exception $e): void
    {
        $this->modx->log(modX::LOG_LEVEL_ERROR, '[ExtraFields API] ' . $e->getMessage());
    }
}
