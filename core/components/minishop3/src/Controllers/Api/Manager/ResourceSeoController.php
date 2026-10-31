<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msProduct;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Seo\ResourceSeoService;
use MODX\Revolution\modResource;
use MODX\Revolution\modX;

/**
 * Manager API for native SEO overrides on msProduct / msCategory resources (#790).
 *
 * GET  /api/mgr/product-data/{id}/seo    — read overrides (msproduct_save)
 * PUT  /api/mgr/product-data/{id}/seo    — upsert / unset overrides (msproduct_save)
 * GET  /api/mgr/categories/{id}/seo     — read overrides (mscategory_save)
 * PUT  /api/mgr/categories/{id}/seo     — upsert / unset overrides (mscategory_save)
 *
 * Empty payload on PUT means "unset / fall back to defaults": the row is removed so the
 * public SEO projection falls through to the builder defaults.
 */
class ResourceSeoController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * GET — read native SEO overrides for a resource.
     *
     * @param array{resource_id?: int|string, class_key?: string} $params
     */
    public function get(array $params): Response
    {
        $resourceId = (int) ($params['resource_id'] ?? 0);
        $classKey = (string) ($params['class_key'] ?? '');

        $verify = $this->verifyResource($resourceId, $classKey);
        if ($verify instanceof Response) {
            return $verify;
        }

        return Response::success($this->service()->get($resourceId));
    }

    /**
     * PUT — upsert / unset native SEO overrides for a resource.
     *
     * @param array{resource_id?: int|string, class_key?: string} $params
     */
    public function update(array $params): Response
    {
        $resourceId = (int) ($params['resource_id'] ?? 0);
        $classKey = (string) ($params['class_key'] ?? '');

        $verify = $this->verifyResource($resourceId, $classKey);
        if ($verify instanceof Response) {
            return $verify;
        }

        $data = $this->getRequestData();
        $result = $this->service()->save($resourceId, $data);
        if (empty($result['ok'])) {
            return $this->lexiconError('ms3_err_seo_save_failed', HttpStatus::UNPROCESSABLE_ENTITY);
        }

        return Response::success($result['data']);
    }

    protected function service(): ResourceSeoService
    {
        /** @var ResourceSeoService */
        return $this->modx->services->get('ms3_resource_seo');
    }

    /**
     * @return Response|null null when the resource exists and matches the expected class_key.
     */
    private function verifyResource(int $resourceId, string $expectedClassKey): ?Response
    {
        if ($resourceId <= 0) {
            return Response::error('resource_id is required', HttpStatus::BAD_REQUEST);
        }

        /** @var modResource|null $resource */
        $resource = $this->modx->getObject(modResource::class, $resourceId);
        if ($resource === null) {
            return $this->lexiconError('ms3_err_resource_not_found', HttpStatus::NOT_FOUND);
        }

        $classKey = $resource->get('class_key');
        $allowed = $expectedClassKey !== ''
            ? [$expectedClassKey]
            : [msProduct::class, msCategory::class];
        if (!in_array($classKey, $allowed, true)) {
            return $this->lexiconError('ms3_err_seo_class_key_mismatch', HttpStatus::FORBIDDEN);
        }

        return null;
    }

    private function lexiconError(string $key, int $status): Response
    {
        $this->modx->lexicon->load('minishop3:default');

        return Response::error($this->modx->lexicon($key), $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestData(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }
}
