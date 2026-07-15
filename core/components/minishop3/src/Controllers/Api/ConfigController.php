<?php

namespace MiniShop3\Controllers\Api;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;

/**
 * API controller for field configuration management
 */
class ConfigController extends BaseApiController
{
    /**
     * GET /api/mgr/config/page-fields/{page_key}
     * Get page field configuration from ms3_product_fields
     *
     * @param array $params
     * @return Response
     */
    public function getPageFields(array $params): Response
    {
        $pageKey = $params['page_key'] ?? '';

        if (empty($pageKey)) {
            return Response::error('Page key is required', HttpStatus::BAD_REQUEST);
        }

        try {
            /** @var \MiniShop3\Services\ConfigService */
            $configService = $this->modx->services->get('ms3_config_service');

            $config = $configService->getPageFields($pageKey);

            return Response::success($config);
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ConfigController] ' . $e->getMessage());
            return Response::error('Failed to load config: ' . $e->getMessage(), HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/mgr/config/page-fields/{page_key}/all
     * Get all available fields (including hidden) from ms3_product_fields
     *
     * @param array $params
     * @return Response
     */
    public function getAllPageFields(array $params): Response
    {
        $pageKey = $params['page_key'] ?? '';

        if (empty($pageKey)) {
            return Response::error('Page key is required', HttpStatus::BAD_REQUEST);
        }

        try {
            /** @var \MiniShop3\Services\ConfigService */
            $configService = $this->modx->services->get('ms3_config_service');

            $result = $configService->getAllPageFields($pageKey);

            return Response::success($result);
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ConfigController] ' . $e->getMessage());
            return Response::error('Failed to load fields: ' . $e->getMessage(), HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * PUT /api/mgr/config/page-fields/{page_key}
     * Save field configuration to ms3_product_fields
     *
     * @param array $params
     * @return Response
     */
    public function updatePageFields(array $params): Response
    {
        $pageKey = $params['page_key'] ?? '';

        if (empty($pageKey)) {
            return Response::error('Page key is required', HttpStatus::BAD_REQUEST);
        }

        $data = $this->getRequestData();

        if (!isset($data['fields']) || !is_array($data['fields'])) {
            return Response::error('Fields array is required', HttpStatus::BAD_REQUEST);
        }

        try {
            /** @var \MiniShop3\Services\ConfigService */
            $configService = $this->modx->services->get('ms3_config_service');

            $success = $configService->saveFieldsConfig($data['fields']);

            if ($success) {
                return Response::success([
                    'message' => 'Configuration saved successfully',
                ]);
            }

            return Response::error('Failed to save configuration', HttpStatus::INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ConfigController] ' . $e->getMessage());
            return Response::error('Failed to save config: ' . $e->getMessage(), HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/mgr/config/sections/{page_key}
     * Get page sections with lexicon translations
     *
     * @param array $params
     * @return Response
     */
    public function getSections(array $params): Response
    {
        $pageKey = $params['page_key'] ?? '';

        if (empty($pageKey)) {
            return Response::error('Page key is required', HttpStatus::BAD_REQUEST);
        }

        try {
            /** @var \MiniShop3\Services\ConfigService */
            $configService = $this->modx->services->get('ms3_config_service');

            $sections = $configService->getSections($pageKey);

            return Response::success([
                'sections' => $sections
            ]);
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ConfigController] ' . $e->getMessage());
            return Response::error('Failed to load sections: ' . $e->getMessage(), HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * PUT /api/mgr/config/sections/{page_key}
     * Save sections (order, visibility)
     *
     * @param array $params
     * @return Response
     */
    public function updateSections(array $params): Response
    {
        $pageKey = $params['page_key'] ?? '';

        if (empty($pageKey)) {
            return Response::error('Page key is required', HttpStatus::BAD_REQUEST);
        }

        $data = $this->getRequestData();

        if (!isset($data['sections']) || !is_array($data['sections'])) {
            return Response::error('Sections array is required', HttpStatus::BAD_REQUEST);
        }

        try {
            /** @var \MiniShop3\Services\ConfigService */
            $configService = $this->modx->services->get('ms3_config_service');

            $success = $configService->saveSections($pageKey, $data['sections']);

            if ($success) {
                return Response::success([
                    'message' => 'Sections saved successfully',
                ]);
            } else {
                return Response::error('Failed to save sections', HttpStatus::INTERNAL_SERVER_ERROR);
            }
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ConfigController] ' . $e->getMessage());
            return Response::error('Failed to save sections: ' . $e->getMessage(), HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * DELETE /api/mgr/config/sections/{page_key}/{section_key}
     * Delete section (custom sections only)
     *
     * @param array $params
     * @return Response
     */
    public function deleteSection(array $params): Response
    {
        $pageKey = $params['page_key'] ?? '';
        $sectionKey = $params['section_key'] ?? '';

        if (empty($pageKey) || empty($sectionKey)) {
            return Response::error('Page key and section key are required', HttpStatus::BAD_REQUEST);
        }

        try {
            /** @var \MiniShop3\Services\ConfigService */
            $configService = $this->modx->services->get('ms3_config_service');

            $success = $configService->deleteSection($pageKey, $sectionKey);

            if ($success) {
                return Response::success([
                    'message' => 'Section deleted successfully',
                ]);
            } else {
                return Response::error('Failed to delete section (base sections cannot be deleted)', HttpStatus::BAD_REQUEST);
            }
        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ConfigController] ' . $e->getMessage());
            return Response::error('Failed to delete section: ' . $e->getMessage(), HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }
}
