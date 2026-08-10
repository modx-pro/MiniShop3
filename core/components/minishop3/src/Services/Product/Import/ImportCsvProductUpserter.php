<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product\Import;

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msVendor;
use MODX\Revolution\modResource;
use MODX\Revolution\modX;

/**
 * Product create/update via MODX processors plus vendor/TV resolution.
 */
final class ImportCsvProductUpserter
{
    public function __construct(
        private ImportCsvContext $ctx,
        private ImportCsvOptionHandler $optionHandler,
        private ImportCsvGalleryHandler $galleryHandler,
    ) {
    }

    public function findExistingProduct(array $data): ?modResource
    {
        $isProduct = strtolower($data['class_key']) === strtolower(msProduct::class);

        $q = $this->ctx->modx->newQuery($data['class_key']);
        $classAlias = $isProduct ? 'msProduct' : 'modResource';
        $q->setClassAlias($classAlias);
        $q->where([
            'deleted' => 0,
            'class_key' => $data['class_key'],
        ]);
        $q->select($classAlias . '.id');

        if ($isProduct) {
            $q->innerJoin(msProductData::class, 'Data', $classAlias . '.id = Data.id');
        }

        $key = $this->ctx->params['key'];
        $keyValue = $data[$key] ?? null;

        if ($keyValue === null) {
            return null;
        }

        $resourceFields = $this->ctx->modx->getFields($data['class_key']);
        if (isset($resourceFields[$key])) {
            $q->where([$key => $keyValue]);
        } elseif ($isProduct) {
            $q->where(['Data.' . $key => $keyValue]);
        }

        $q->prepare();
        $this->ctx->modx->log(modX::LOG_LEVEL_INFO, "SQL query for check for duplicate: \n" . $q->toSql());

        /** @var modResource|null $existing */
        $existing = $this->ctx->modx->getObject($data['class_key'], $q);

        return $existing;
    }

    public function resolveVendor(string $vendorName): int
    {
        if ($vendorName === '') {
            return 0;
        }

        if (is_numeric($vendorName)) {
            return (int) $vendorName;
        }

        $vendor = $this->ctx->modx->getObject(msVendor::class, ['name' => $vendorName]);
        if ($vendor) {
            return (int) $vendor->get('id');
        }

        $vendor = $this->ctx->modx->newObject(msVendor::class);
        $vendor->set('name', $vendorName);
        if ($vendor->save()) {
            $this->ctx->modx->log(modX::LOG_LEVEL_INFO, "Created new vendor: $vendorName");

            return (int) $vendor->get('id');
        }

        return 0;
    }

    public function resolveTvId(string $tvNameOrId): int
    {
        if (isset($this->ctx->tvCache[$tvNameOrId])) {
            return $this->ctx->tvCache[$tvNameOrId];
        }

        if (is_numeric($tvNameOrId)) {
            $tvId = (int) $tvNameOrId;
            $tv = $this->ctx->modx->getObject(\MODX\Revolution\modTemplateVar::class, ['id' => $tvId]);
            if ($tv) {
                $this->ctx->tvCache[$tvNameOrId] = $tvId;

                return $tvId;
            }
            $this->ctx->tvCache[$tvNameOrId] = 0;

            return 0;
        }

        $tv = $this->ctx->modx->getObject(\MODX\Revolution\modTemplateVar::class, ['name' => $tvNameOrId]);
        if ($tv) {
            $tvId = (int) $tv->get('id');
            $this->ctx->tvCache[$tvNameOrId] = $tvId;

            return $tvId;
        }

        $this->ctx->tvCache[$tvNameOrId] = 0;

        return 0;
    }

    public function runAction(string $action, array $data, array $gallery = [], array $optionData = []): void
    {
        $this->ctx->modx->error->reset();
        $response = $this->ctx->modx->runProcessor('MiniShop3\\Processors\\Product\\' . $action, $data);

        if ($response->isError()) {
            $this->ctx->modx->log(
                modX::LOG_LEVEL_ERROR,
                "Error on $action: \n" . print_r($response->getAllErrors(), true)
            );
            $this->ctx->errors++;

            return;
        }

        if ($action === 'Update') {
            $this->ctx->updated++;
        } else {
            $this->ctx->created++;
        }

        $resource = $response->getObject();
        $this->ctx->modx->log(modX::LOG_LEVEL_INFO, "Successful $action: \n" . print_r($resource, true));

        $productId = $resource['id'] ?? null;
        if (!$productId) {
            return;
        }

        if ($optionData !== []) {
            $this->optionHandler->saveForProduct((int) $productId, $optionData);
        }

        if ($gallery !== []) {
            $this->galleryHandler->importForResource($resource, $gallery);
        }
    }
}
