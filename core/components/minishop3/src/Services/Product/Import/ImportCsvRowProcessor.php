<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product\Import;

use MiniShop3\Model\msProduct;
use MiniShop3\Utils\EventGate;
use MODX\Revolution\modResource;
use MODX\Revolution\modX;

/**
 * Maps one CSV row to product payload and triggers upsert.
 */
final class ImportCsvRowProcessor
{
    public function __construct(
        private ImportCsvContext $ctx,
        private ImportCsvProductUpserter $upserter,
        private ImportCsvRowFieldMapper $fieldMapper = new ImportCsvRowFieldMapper(),
    ) {
    }

    /**
     * @param list<string> $csv
     */
    public function process(array $csv): bool
    {
        $this->ctx->modx->log(modX::LOG_LEVEL_INFO, "Raw data for import: \n" . print_r($csv, true));

        $mapped = $this->fieldMapper->map(
            $this->ctx->params['keys'],
            $csv,
            fn (string $vendorName): int => $this->upserter->resolveVendor($vendorName),
            (bool) $this->ctx->params['update'],
        );

        if ($mapped['missingField'] !== null) {
            $error = $this->ctx->modx->lexicon(
                'ms3_utilities_file_field_nf',
                ['field' => $mapped['missingField'], 'row' => $this->ctx->rows]
            );
            $this->ctx->modx->log(modX::LOG_LEVEL_ERROR, $error);
            $this->ctx->errors++;

            return false;
        }

        $data = $mapped['data'];
        $gallery = $mapped['gallery'];
        $tvData = $mapped['tvData'];
        $optionData = $mapped['optionData'];

        $event = EventGate::invokeRaw($this->ctx->modx, 'msOnImportRow', [
            'row' => $this->ctx->rows,
            'csv' => $csv,
            'data' => &$data,
            'tvData' => &$tvData,
            'optionData' => &$optionData,
            'gallery' => &$gallery,
        ]);
        $returnedValues = $event['returnedValues'];
        $data = EventGate::applyReturnedArray($data, $returnedValues, 'data');
        $tvData = EventGate::applyReturnedArray($tvData, $returnedValues, 'tvData');
        $optionData = EventGate::applyReturnedArray($optionData, $returnedValues, 'optionData');
        $gallery = EventGate::applyReturnedArray($gallery, $returnedValues, 'gallery');
        if ($event['cancelled']) {
            $this->ctx->skipped++;

            return true;
        }

        if (empty($data['pagetitle'])) {
            $this->ctx->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[Import] Row {$this->ctx->rows}: Missing required field 'pagetitle'"
            );
            $this->ctx->errors++;

            return false;
        }

        if (empty($data['parent'])) {
            $this->ctx->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[Import] Row {$this->ctx->rows}: Missing required field 'parent'"
            );
            $this->ctx->errors++;

            return false;
        }

        $parentId = (int) $data['parent'];
        $parent = $this->ctx->modx->getObject(modResource::class, ['id' => $parentId]);
        if (!$parent) {
            $this->ctx->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[Import] Row {$this->ctx->rows}: Parent resource with id={$parentId} not found"
            );
            $this->ctx->errors++;

            return false;
        }

        if (empty($data['class_key'])) {
            $data['class_key'] = msProduct::class;
        }
        if (empty($data['context_key'])) {
            $data['context_key'] = $parent->get('context_key');
        }

        $data['tvs'] = $this->ctx->params['tv_enabled'] || $tvData !== [];

        foreach ($tvData as $tvName => $tvValue) {
            $tvId = $this->upserter->resolveTvId($tvName);
            if ($tvId) {
                $data['tv' . $tvId] = $tvValue;
            } else {
                $this->ctx->modx->log(
                    modX::LOG_LEVEL_WARN,
                    "[Import] Row {$this->ctx->rows}: TV '$tvName' not found, skipping"
                );
            }
        }

        $this->ctx->modx->log(modX::LOG_LEVEL_INFO, "Array with importing data: \n" . print_r($data, true));

        $exists = $this->upserter->findExistingProduct($data);

        $action = 'Create';
        if ($exists) {
            $key = $this->ctx->params['key'];
            $keyValue = $data[$key] ?? 'N/A';
            $this->ctx->modx->log(modX::LOG_LEVEL_INFO, "Key $key = $keyValue has duplicate.");

            if (!$this->ctx->params['update']) {
                $this->ctx->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "Skipping line with $key = \"$keyValue\" because update is disabled."
                );
                $this->ctx->skipped++;

                return true;
            }
            $action = 'Update';
            $data['id'] = $exists->id;
        }

        $this->upserter->runAction($action, $data, $gallery, $optionData);

        return true;
    }
}
