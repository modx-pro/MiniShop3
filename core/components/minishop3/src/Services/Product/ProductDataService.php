<?php

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductOption;
use MiniShop3\Services\ExtraFields\KeyValueFieldService;
use MiniShop3\Services\ExtraFields\RepeaterFieldService;
use MODX\Revolution\modX;

/**
 * Service for working with product data
 *
 * Handles saving, deleting and modifying msProductData,
 * including categories, options, links and plugin modifiers
 */
class ProductDataService
{
    /** Error codes for updateProductData */
    public const ERROR_FORBIDDEN = 403;
    public const ERROR_NOT_FOUND = 404;
    public const ERROR_VALIDATION = 422;
    public const ERROR_SAVE = 500;

    /** Allowed fields for inline / API update (msProductData) */
    protected static array $allowedUpdateFields = [
        'article', 'price', 'old_price', 'stock', 'weight',
        'vendor_id', 'made_in', 'new', 'popular', 'favorite',
    ];

    /** Resource (modResource) fields updatable via same API (e.g. published) */
    protected static array $allowedResourceFields = ['published'];

    /** @var modX */
    protected $modx;

    protected ProductRepeaterSupport $repeaterSupport;
    protected ProductKeyValueSupport $keyValueSupport;
    protected ProductCategoryMembershipWriter $categoryWriter;
    protected ProductOptionsWriter $optionsWriter;
    protected ProductLinksWriter $linksWriter;
    protected ProductModifierHooks $modifierHooks;
    protected ProductRemovalHelper $removalHelper;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->repeaterSupport = new ProductRepeaterSupport($modx);
        $this->keyValueSupport = new ProductKeyValueSupport($modx);
        $this->categoryWriter = new ProductCategoryMembershipWriter($modx);
        $this->optionsWriter = new ProductOptionsWriter($modx);
        $this->linksWriter = new ProductLinksWriter($modx);
        $this->modifierHooks = new ProductModifierHooks($modx);
        $this->removalHelper = new ProductRemovalHelper($modx);
    }

    protected function getRepeaterFieldService(): RepeaterFieldService
    {
        return $this->repeaterSupport->getRepeaterFieldService();
    }

    /**
     * Overridable hook for tests (see TestableProductDataService).
     *
     * @return array<string, array>
     */
    protected function getProductRepeaterFields(): array
    {
        return $this->repeaterSupport->getProductRepeaterFields();
    }

    protected function getKeyValueFieldService(): KeyValueFieldService
    {
        return $this->keyValueSupport->getKeyValueFieldService();
    }

    /**
     * Overridable hook for tests (see TestableProductDataService).
     *
     * @return array<string, array>
     */
    protected function getProductKeyValueFields(): array
    {
        return $this->keyValueSupport->getProductKeyValueFields();
    }

    /**
     * Prepare object before saving: array/repeater/key-value fields, source_id, numeric casts.
     */
    public function prepareObject(msProductData $productData): void
    {
        $repeaterFields = $this->getProductRepeaterFields();
        $keyValueFields = $this->getProductKeyValueFields();
        $repeaterService = $this->getRepeaterFieldService();
        $keyValueService = $this->getKeyValueFieldService();

        foreach ($productData->getArraysValues() as $name => $array) {
            if (isset($repeaterFields[$name])) {
                $productData->set($name, $repeaterService->processValue($array, $repeaterFields[$name]));
                continue;
            }
            if (isset($keyValueFields[$name])) {
                try {
                    $normalized = $keyValueService->processValue($array, $keyValueFields[$name]);
                    $productData->set($name, $normalized);
                } catch (\InvalidArgumentException $e) {
                    $this->modx->lexicon->load('minishop3:default');
                    throw new \InvalidArgumentException(
                        $this->modx->lexicon('ms3_key_value_validation_error', [
                            'field' => $name,
                            'error' => $e->getMessage(),
                        ]),
                        0,
                        $e
                    );
                }
                continue;
            }

            $productData->set($name, $productData->prepareOptionValues($array));
        }

        if ($productData->isNew()) {
            $productData->set('source_id', $this->modx->getOption('ms3_product_source_default', null, 1));
        }

        // Cast numeric/boolean fields (incl. extra fields) so '' does not break MySQL decimals/ints
        foreach ($productData->_fieldMeta as $key => $meta) {
            if ($key === 'id') {
                continue;
            }

            $phptype = $meta['phptype'] ?? '';
            $value = $productData->get($key);
            $isEmpty = $value === '' || $value === null;

            match ($phptype) {
                'float' => $productData->set($key, $isEmpty ? 0.0 : (float)$value),
                'integer' => $productData->set($key, $isEmpty ? 0 : (int)$value),
                'boolean' => $productData->set($key, $isEmpty ? false : (bool)$value),
                default => null,
            };
        }
    }

    public function saveCategories(msProductData $productData): void
    {
        $this->categoryWriter->saveCategories($productData);
    }

    /**
     * @param array|null $options If null: built from JSON fields explicitly present in POST/_fields on
     *                           $productData (including cleared empty values). If array: explicit keys/values
     *                           (e.g. manager POST options-*); then $removeOther is honored.
     * @param bool $removeOther When $options is non-null: if true, delete msProductOption rows whose keys are absent
     *                         from $options. When $options is null: ignored — always treated as false so category-only
     *                         options not mirrored in JSON fields are preserved (#153, #158).
     */
    public function saveOptions(msProductData $productData, ?array $options = null, bool $removeOther = true): void
    {
        $excludedJsonKeys = array_merge(
            array_keys($this->getProductRepeaterFields()),
            array_keys($this->getProductKeyValueFields())
        );
        $this->optionsWriter->saveOptions(
            $productData,
            $options,
            $removeOther,
            $excludedJsonKeys
        );
    }

    public function saveLinks(msProductData $productData): void
    {
        $this->linksWriter->saveLinks($productData);
    }

    public function removeProduct(msProductData $productData, array $ancestors = []): bool
    {
        return $this->removalHelper->removeProduct($productData, $ancestors);
    }

    /**
     * @param array $data Additional product data
     * @return mixed|string
     */
    public function getModifiedPrice(msProductData $productData, array $data = [])
    {
        return $this->modifierHooks->getModifiedPrice($productData, $data);
    }

    /**
     * @param array $data Additional product data
     * @return mixed|string
     */
    public function getModifiedWeight(msProductData $productData, array $data = [])
    {
        return $this->modifierHooks->getModifiedWeight($productData, $data);
    }

    /**
     * @param array $data Product fields
     * @return array Modified fields
     */
    public function getModifiedFields(msProductData $productData, array $data = []): array
    {
        return $this->modifierHooks->getModifiedFields($productData, $data);
    }

    public function getOptionKeys(msProductData $productData): array
    {
        $productId = $productData->get('id');

        /** @var msProductOption $option */
        $option = $this->modx->newObject(msProductOption::class);
        $option->set('product_id', $productId);

        $result = $option->getOptionKeys($productId);

        return is_array($result) ? $result : [];
    }

    /**
     * @param array $keys Filter by option keys
     */
    public function getOptionFields(msProductData $productData, array $keys = []): array
    {
        /** @var msProductOption $option */
        $option = $this->modx->newObject(msProductOption::class);
        $option->set('product_id', $productData->get('id'));
        $result = $option->getOptionFields($productData->get('id'));

        return is_array($result) ? $result : [];
    }

    /**
     * @return array|null Data array or null if not found
     */
    public function getProductData(int $productId): ?array
    {
        /** @var msProduct|null $product */
        $product = $this->modx->getObject(msProduct::class, $productId);
        if (!$product) {
            return null;
        }

        $productData = $product->loadData();
        if (!$productData) {
            return null;
        }

        return array_merge($product->toArray(), $productData->toArray());
    }

    /**
     * Apply published state to product resource and save.
     */
    protected function applyPublishedToResource(msProduct $product, int $published): bool
    {
        $product->set('published', $published);
        if ($published) {
            $product->set('publishedon', time());
            $product->set('publishedby', $this->modx->user->get('id'));
        } else {
            $product->set('publishedon', 0);
            $product->set('publishedby', 0);
        }

        if (!$product->save()) {
            return false;
        }

        $this->modx->invokeEvent($published ? 'OnDocPublished' : 'OnDocUnPublished', [
            'id' => $product->get('id'),
            'resource' => $product,
        ]);

        return true;
    }

    /**
     * Minimal server-side validation for productData update values.
     */
    protected function validateProductDataUpdate(array $filtered): bool
    {
        if (isset($filtered['price']) && (float)$filtered['price'] < 0) {
            return false;
        }
        if (isset($filtered['old_price']) && (float)$filtered['old_price'] < 0) {
            return false;
        }
        if (isset($filtered['stock'])) {
            if ((int)$filtered['stock'] != $filtered['stock'] || (int)$filtered['stock'] < 0) {
                return false;
            }
        }
        if (isset($filtered['weight']) && (float)$filtered['weight'] < 0) {
            return false;
        }

        return true;
    }

    /**
     * Update product data (msProductData and optionally resource fields like published).
     *
     * @return array Success: ['ok' => true, 'data' => array]. Error: ['ok' => false, 'code' => int, 'message' => string]
     */
    public function updateProductData(int $productId, array $data): array
    {
        if (!$this->modx->hasPermission('save_document')) {
            return ['ok' => false, 'code' => self::ERROR_FORBIDDEN, 'message' => 'Permission denied'];
        }

        /** @var msProduct|null $product */
        $product = $this->modx->getObject(msProduct::class, $productId);
        if (!$product) {
            return ['ok' => false, 'code' => self::ERROR_NOT_FOUND, 'message' => 'Product not found'];
        }
        if (!$product->checkPolicy('save')) {
            return ['ok' => false, 'code' => self::ERROR_FORBIDDEN, 'message' => 'Save permission denied'];
        }

        /** @var msProductData|null $productData */
        $productData = $product->loadData();
        if (!$productData) {
            return ['ok' => false, 'code' => self::ERROR_NOT_FOUND, 'message' => 'Product data not found'];
        }

        // Static whitelist + active repeater/key-value extra-field keys for msProductData.
        // Without the extra keys, values get silently dropped here and
        // prepareObject() then normalises the in-memory null/empty to [] on save —
        // user input is lost with no error (#301).
        $filtered = array_intersect_key(
            $data,
            array_flip(array_merge(
                self::$allowedUpdateFields,
                array_keys($this->getProductRepeaterFields()),
                array_keys($this->getProductKeyValueFields())
            ))
        );
        $resourceData = array_intersect_key($data, array_flip(self::$allowedResourceFields));

        if (!$this->validateProductDataUpdate($filtered)) {
            return ['ok' => false, 'code' => self::ERROR_VALIDATION, 'message' => 'Validation failed'];
        }

        $repeaterError = $this->normalizeRepeaterFieldsInPayload($filtered);
        if ($repeaterError !== null) {
            return ['ok' => false, 'code' => self::ERROR_VALIDATION, 'message' => $repeaterError];
        }
        $keyValueError = $this->normalizeKeyValueFieldsInPayload($filtered);
        if ($keyValueError !== null) {
            return ['ok' => false, 'code' => self::ERROR_VALIDATION, 'message' => $keyValueError];
        }

        $oldValues = [];
        foreach (array_keys($filtered) as $key) {
            $oldValues[$key] = $productData->get($key);
        }

        $productData->fromArray($filtered);
        if (!$productData->save()) {
            return ['ok' => false, 'code' => self::ERROR_SAVE, 'message' => 'Failed to save product data'];
        }

        if (isset($resourceData['published'])) {
            $published = $resourceData['published'] ? 1 : 0;
            if (!$this->applyPublishedToResource($product, $published)) {
                $productData->fromArray($oldValues);
                $productData->save();

                return ['ok' => false, 'code' => self::ERROR_SAVE, 'message' => 'Failed to update published state'];
            }
        }

        $result = $productData->toArray();
        if (isset($resourceData['published'])) {
            $result['published'] = (bool)$product->get('published');
        }

        return ['ok' => true, 'data' => $result];
    }

    /**
     * Validate and normalize repeater extra fields in manager API payload.
     *
     * @param array<string, mixed> $payload
     */
    protected function normalizeRepeaterFieldsInPayload(array &$payload): ?string
    {
        return $this->repeaterSupport->normalizeRepeaterFieldsInPayload(
            $payload,
            $this->getProductRepeaterFields()
        );
    }

    /**
     * Validate and normalize key-value extra fields in manager API payload.
     *
     * @param array<string, mixed> $payload
     */
    protected function normalizeKeyValueFieldsInPayload(array &$payload): ?string
    {
        return $this->keyValueSupport->normalizeKeyValueFieldsInPayload(
            $payload,
            $this->getProductKeyValueFields()
        );
    }
}
