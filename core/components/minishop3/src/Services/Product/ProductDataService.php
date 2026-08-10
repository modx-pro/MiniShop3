<?php

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msCategoryMember;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductFile;
use MiniShop3\Model\msProductLink;
use MiniShop3\Model\msProductOption;
use MiniShop3\Services\ExtraFields\RepeaterFieldService;
use MiniShop3\Utils\EventGate;
use MODX\Revolution\modX;

/**
 * Service for working with product data
 *
 * Handles saving, deleting and modifying msProductData,
 * including categories, options, links and plugin modifiers
 */
class ProductDataService
{
    /** @var modX */
    protected $modx;

    /** @var array<string, array>|null */
    protected ?array $productRepeaterFields = null;

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    protected function getRepeaterFieldService(): RepeaterFieldService
    {
        /** @var RepeaterFieldService $service */
        $service = $this->modx->services->get('ms3_repeater_field');

        return $service;
    }

    /**
     * @return array<string, array>
     */
    protected function getProductRepeaterFields(): array
    {
        if ($this->productRepeaterFields === null) {
            $this->productRepeaterFields = $this->getRepeaterFieldService()->getRepeaterFieldsForClass(
                msProductData::class
            );
        }

        return $this->productRepeaterFields;
    }

    /**
     * Prepare object before saving
     *
     * Performs comprehensive product data preparation:
     * - Prepare array fields (tags, color, size etc.) - remove duplicates, empty values
     * - Set source_id for new products
     * - Cast numeric and boolean fields to proper types (including extra fields)
     *
     * @param msProductData $productData
     * @return void
     */
    public function prepareObject(msProductData $productData): void
    {
        $repeaterFields = $this->getProductRepeaterFields();
        $repeaterService = $this->getRepeaterFieldService();

        foreach ($productData->getArraysValues() as $name => $array) {
            if (isset($repeaterFields[$name])) {
                $normalized = $repeaterService->processValue($array, $repeaterFields[$name]);
                $productData->set($name, $normalized);
                continue;
            }

            $array = $productData->prepareOptionValues($array);
            $productData->set($name, $array);
        }

        if ($productData->isNew()) {
            $productData->set('source_id', $this->modx->getOption('ms3_product_source_default', null, 1));
        }

        // Cast all numeric and boolean fields (including extra fields) to proper types
        // Prevents MySQL errors when empty string '' is sent for decimal/int/tinyint columns
        foreach ($productData->_fieldMeta as $key => $meta) {
            if ($key === 'id') {
                continue;
            }
            $phptype = $meta['phptype'] ?? '';
            $value = $productData->get($key);

            if ($phptype === 'float') {
                $productData->set($key, ($value === '' || $value === null) ? 0.0 : (float)$value);
            } elseif ($phptype === 'integer') {
                $productData->set($key, ($value === '' || $value === null) ? 0 : (int)$value);
            } elseif ($phptype === 'boolean') {
                $productData->set($key, ($value === '' || $value === null) ? false : (bool)$value);
            }
        }
    }

    /**
     * Save additional product categories
     *
     * Synchronizes msCategoryMember table with categories array from 'categories' field
     * Expected data format: JSON array [3,4,27]
     *
     * IMPORTANT: msProductData::get('categories') is overridden and reads from DB,
     * so we use reflection to get the value from $_fields (POST data)
     *
     * If `categories` was not sent (e.g. manager save before the Categories tab mounted its
     * hidden field), leave msCategoryMember untouched — same contract as saveLinks().
     *
     * @param msProductData $productData
     * @return void
     */
    public function saveCategories(msProductData $productData): void
    {
        $productId = $productData->get('id');

        $reflection = new \ReflectionClass($productData);
        $property = $reflection->getProperty('_fields');
        $property->setAccessible(true);
        $fields = $property->getValue($productData);

        if (!array_key_exists('categories', $fields)) {
            return;
        }

        $categories = $fields['categories'];

        if (is_string($categories)) {
            $categories = json_decode($categories, true);
            if (!is_array($categories)) {
                $categories = [];
            }
        } elseif (!is_array($categories)) {
            $categories = [];
        }

        $this->modx->removeCollection(msCategoryMember::class, ['product_id' => $productId]);

        foreach ($categories as $categoryId) {
            if (!empty($categoryId) && is_numeric($categoryId)) {
                /** @var msCategoryMember $member */
                $member = $this->modx->newObject(msCategoryMember::class);
                $member->set('product_id', $productId);
                $member->set('category_id', (int)$categoryId);
                $member->save();
            }
        }
    }

    /**
     * Save product options
     *
     * Synchronizes data from JSON fields with msProductOption table
     * via msProductOption::saveProductOptions() method
     *
     * @param msProductData $productData
     * @param array|null $options If null: built from JSON fields explicitly present in POST/_fields on
     *                           $productData (including cleared empty values). If array: explicit keys/values
     *                           (e.g. manager POST options-*); then $removeOther is honored.
     * @param bool $removeOther When $options is non-null: if true, delete msProductOption rows whose keys are absent
     *                         from $options. When $options is null: ignored — always treated as false so category-only
     *                         options not mirrored in JSON fields are preserved (#153, #158).
     * @return void
     */
    public function saveOptions(msProductData $productData, ?array $options = null, bool $removeOther = true): void
    {
        $productId = $productData->get('id');

        $optionsExplicit = $options !== null;
        $repeaterKeys = array_keys($this->getProductRepeaterFields());

        if ($options === null) {
            $options = [];
            $reflection = new \ReflectionClass($productData);
            $property = $reflection->getProperty('_fields');
            $property->setAccessible(true);
            $rawFields = $property->getValue($productData);

            foreach ($productData->_fieldMeta as $key => $meta) {
                if (($meta['phptype'] ?? '') !== 'json') {
                    continue;
                }
                if (in_array($key, $repeaterKeys, true)) {
                    continue;
                }
                if (!array_key_exists($key, $rawFields)) {
                    continue;
                }

                $fieldValue = $productData->get($key);
                $options[$key] = !empty($fieldValue) ? $fieldValue : null;
            }
        }

        // When options=null we only sync JSON fields — do not remove custom category options
        $removeOther = $optionsExplicit ? $removeOther : false;

        /** @var msProductOption $optionInstance */
        $optionInstance = $this->modx->newObject(msProductOption::class);
        $optionInstance->saveProductOptions($productId, $options, $removeOther);
    }

    /**
     * Save product links
     *
     * Synchronizes msProductLink table with links array from 'links' field
     * Links can be master->slave (product is master) and slave->master (product is dependent)
     *
     * IMPORTANT: Only syncs if 'links' field was explicitly passed in POST data.
     * Links are managed via separate UI, so we don't touch them on regular product save.
     *
     * @param msProductData $productData
     * @return void
     */
    public function saveLinks(msProductData $productData): void
    {
        $productId = $productData->get('id');

        // Use reflection to check if 'links' was explicitly passed in POST data
        $reflection = new \ReflectionClass($productData);
        $property = $reflection->getProperty('_fields');
        $property->setAccessible(true);
        $fields = $property->getValue($productData);

        // If 'links' key doesn't exist in POST data - don't touch existing links
        if (!array_key_exists('links', $fields)) {
            return;
        }

        $links = $fields['links'];

        if (is_string($links)) {
            $links = json_decode($links, true);
        }

        if (!is_array($links)) {
            return;
        }

        $this->modx->removeCollection(msProductLink::class, ['master' => $productId]);

        foreach ($links as $link) {
            if (!empty($link['slave']) && !empty($link['link'])) {
                /** @var msProductLink $productLink */
                $productLink = $this->modx->newObject(msProductLink::class);
                $productLink->set('master', $productId);
                $productLink->set('slave', $link['slave']);
                $productLink->set('link', $link['link']);
                $productLink->save();
            }
        }
    }

    /**
     * Remove product with all related data
     *
     * Deletes options, categories, links, files and media source directories
     * Cleans database from all product traces
     *
     * @param msProductData $productData
     * @param array $ancestors
     * @return bool
     */
    public function removeProduct(msProductData $productData, array $ancestors = []): bool
    {
        $productId = $productData->get('id');

        $this->modx->removeCollection(msProductOption::class, ['product_id' => $productId]);

        $this->modx->removeCollection(msCategoryMember::class, ['product_id' => $productId]);

        $this->modx->removeCollection(msProductLink::class, [
            'master' => $productId,
            'OR:slave:=' => $productId
        ]);

        if ($productData->xpdo->getCount(msProductFile::class, ['product_id' => $productId]) > 0) {
            $source = $productData->initializeMediaSource($productData->Product->get('context_key'));
            if ($source) {
                $files = $productData->xpdo->getIterator(msProductFile::class, ['product_id' => $productId]);
                /** @var msProductFile $file */
                foreach ($files as $file) {
                    $file->remove();
                }
            }
        }

        // Remove empty product catalog directory via ProductImageService
        /** @var ProductImageService $imageService */
        $imageService = $this->modx->services->get('ms3_product_image');
        if ($imageService) {
            $imageService->removeProductCatalog($productData);
        }

        return true;
    }

    /**
     * Get product price with plugin modifiers
     *
     * Invokes msOnGetProductPrice event for price modification.
     * Supports plugin chaining: each plugin can read/modify price via $modx->eventData
     *
     * Plugin example:
     * ```php
     * case 'msOnGetProductPrice':
     *     $price = $modx->eventData['msOnGetProductPrice']['price'] ?? $scriptProperties['price'];
     *     $newPrice = $price * 0.9; // 10% discount
     *     $modx->eventData['msOnGetProductPrice']['price'] = $newPrice;
     *     $modx->event->returnedValues['price'] = $newPrice;
     *     break;
     * ```
     *
     * @param msProductData $productData
     * @param array $data Additional product data
     * @return mixed|string
     */
    public function getModifiedPrice(msProductData $productData, array $data = [])
    {
        $eventName = 'msOnGetProductPrice';
        $price = !empty($data['price'])
            ? $data['price']
            : $productData->get('price');

        return $this->invokeProductModifier(
            $eventName,
            ['price' => $price, 'data' => $data],
            ['price' => $price, 'data' => $data],
            'price',
            $price,
        );
    }

    /**
     * Get product weight with plugin modifiers
     *
     * Invokes msOnGetProductWeight event for weight modification.
     * Supports plugin chaining: each plugin can read/modify weight via $modx->eventData
     *
     * Plugin example:
     * ```php
     * case 'msOnGetProductWeight':
     *     $weight = $modx->eventData['msOnGetProductWeight']['weight'] ?? $scriptProperties['weight'];
     *     $newWeight = $weight + 0.5; // Add packaging weight
     *     $modx->eventData['msOnGetProductWeight']['weight'] = $newWeight;
     *     $modx->event->returnedValues['weight'] = $newWeight;
     *     break;
     * ```
     *
     * @param msProductData $productData
     * @param array $data Additional product data
     * @return mixed|string
     */
    public function getModifiedWeight(msProductData $productData, array $data = [])
    {
        $eventName = 'msOnGetProductWeight';
        $weight = !empty($data['weight'])
            ? $data['weight']
            : $productData->get('weight');

        return $this->invokeProductModifier(
            $eventName,
            ['weight' => $weight, 'data' => $data],
            ['weight' => $weight, 'data' => $data],
            'weight',
            $weight,
        );
    }

    /**
     * Modify product fields via plugins
     *
     * Invokes msOnGetProductFields event for custom product field processing.
     * Supports plugin chaining: each plugin can read/modify fields via $modx->eventData
     *
     * Plugin example:
     * ```php
     * case 'msOnGetProductFields':
     *     $data = $modx->eventData['msOnGetProductFields']['data'] ?? $scriptProperties['data'];
     *     $data['custom_field'] = 'value';
     *     $modx->eventData['msOnGetProductFields']['data'] = $data;
     *     $modx->event->returnedValues['data'] = $data;
     *     break;
     * ```
     *
     * @param msProductData $productData
     * @param array $data Product fields
     * @return array Modified fields
     */
    public function getModifiedFields(msProductData $productData, array $data = []): array
    {
        $eventName = 'msOnGetProductFields';

        /** @var array<string, mixed> */
        return $this->invokeProductModifier(
            $eventName,
            ['data' => $data],
            ['data' => $data],
            'data',
            $data,
            true,
        );
    }

    /**
     * Get product option keys
     *
     * Delegates call to msProductOption to get list of all option keys
     *
     * @param msProductData $productData
     * @return array
     */
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
     * Get product option fields
     *
     * Delegates call to msProductOption to get option fields
     * with current values and ExtJS metadata
     *
     * @param msProductData $productData
     * @param array $keys Filter by option keys
     * @return array
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
     * Get product data by ID
     *
     * Loads msProduct and msProductData, merges their fields into one array
     * Used in API controllers to get complete product data
     *
     * @param int $productId Product ID
     * @return array|null Data array or null if not found
     */
    public function getProductData(int $productId): ?array
    {
        /** @var msProduct $product */
        $product = $this->modx->getObject(msProduct::class, $productId);

        if (!$product) {
            return null;
        }

        $productData = $product->loadData();

        if (!$productData) {
            return null;
        }

        $data = array_merge(
            $product->toArray(),
            $productData->toArray()
        );

        return $data;
    }

    /**
     * Allowed fields for inline / API update (msProductData)
     */
    protected static array $allowedUpdateFields = [
        'article', 'price', 'old_price', 'stock', 'weight',
        'vendor_id', 'made_in', 'new', 'popular', 'favorite',
    ];

    /**
     * Resource (modResource) fields updatable via same API (e.g. published)
     */
    protected static array $allowedResourceFields = ['published'];

    /**
     * Apply published state to product resource and save.
     * Invokes OnDocPublished / OnDocUnPublished for plugin compatibility.
     *
     * @param msProduct $product
     * @param int $published 0 or 1
     * @return bool True if saved successfully
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
        $eventName = $published ? 'OnDocPublished' : 'OnDocUnPublished';
        $this->modx->invokeEvent($eventName, [
            'id' => $product->get('id'),
            'resource' => $product,
        ]);
        return true;
    }

    /**
     * Validate productData update values (minimal server-side validation).
     *
     * @param array $filtered Filtered allowed fields
     * @return bool True if valid
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

    /** Error codes for updateProductData */
    public const ERROR_FORBIDDEN = 403;
    public const ERROR_NOT_FOUND = 404;
    public const ERROR_VALIDATION = 422;
    public const ERROR_SAVE = 500;

    /**
     * Update product data (msProductData and optionally resource fields like published).
     * Saves productData first, then resource (published). On resource failure, rolls back productData.
     *
     * @param int $productId Product ID
     * @param array $data Data to update
     * @return array Success: ['ok' => true, 'data' => array]. Error: ['ok' => false, 'code' => int, 'message' => string]
     */
    public function updateProductData(int $productId, array $data): array
    {
        if (!$this->modx->hasPermission('save_document')) {
            return ['ok' => false, 'code' => self::ERROR_FORBIDDEN, 'message' => 'Permission denied'];
        }

        /** @var msProduct $product */
        $product = $this->modx->getObject(msProduct::class, $productId);
        if (!$product) {
            return ['ok' => false, 'code' => self::ERROR_NOT_FOUND, 'message' => 'Product not found'];
        }
        if (!$product->checkPolicy('save')) {
            return ['ok' => false, 'code' => self::ERROR_FORBIDDEN, 'message' => 'Save permission denied'];
        }

        /** @var msProductData $productData */
        $productData = $product->loadData();
        if (!$productData) {
            return ['ok' => false, 'code' => self::ERROR_NOT_FOUND, 'message' => 'Product data not found'];
        }

        // Static whitelist + active repeater extra-field keys for msProductData.
        // Without the extra keys, repeater values get silently dropped here and
        // prepareObject() then normalises the in-memory null/empty to [] on save —
        // user input is lost with no error (#301).
        $allowedKeys = array_merge(
            self::$allowedUpdateFields,
            array_keys($this->getProductRepeaterFields())
        );
        $filtered = array_intersect_key($data, array_flip($allowedKeys));
        $resourceData = array_intersect_key($data, array_flip(self::$allowedResourceFields));

        if (!$this->validateProductDataUpdate($filtered)) {
            return ['ok' => false, 'code' => self::ERROR_VALIDATION, 'message' => 'Validation failed'];
        }

        $fieldsToUpdate = $filtered;

        $repeaterError = $this->normalizeRepeaterFieldsInPayload($fieldsToUpdate);
        if ($repeaterError !== null) {
            return ['ok' => false, 'code' => self::ERROR_VALIDATION, 'message' => $repeaterError];
        }
        $oldValues = [];
        foreach (array_keys($fieldsToUpdate) as $key) {
            $oldValues[$key] = $productData->get($key);
        }

        $productData->fromArray($fieldsToUpdate);
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
        $repeaterFields = $this->getProductRepeaterFields();
        if ($repeaterFields === []) {
            return null;
        }

        $repeaterService = $this->getRepeaterFieldService();
        $this->modx->lexicon->load('minishop3:default');

        foreach ($repeaterFields as $fieldKey => $config) {
            if (!array_key_exists($fieldKey, $payload)) {
                continue;
            }

            try {
                $payload[$fieldKey] = $repeaterService->processValue($payload[$fieldKey], $config);
            } catch (\InvalidArgumentException $e) {
                return $this->modx->lexicon('ms3_repeater_validation_error', [
                    'field' => $fieldKey,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * Invoke product modifier event with eventData chaining and returnedValues fallback.
     *
     * @param array<string, mixed> $eventData
     * @param array<string, mixed> $properties
     */
    private function invokeProductModifier(
        string $eventName,
        array $eventData,
        array $properties,
        string $valueKey,
        mixed $default,
        bool $patchViaApplyReturnedArray = false,
    ): mixed {
        if (empty($this->modx->eventMap[$eventName])) {
            return $default;
        }

        $this->modx->eventData[$eventName] = $eventData;
        EventGate::clearReturnedValues($this->modx);
        $this->modx->invokeEvent($eventName, $properties);

        $value = $default;
        if (isset($this->modx->eventData[$eventName][$valueKey])) {
            $fromEventData = $this->modx->eventData[$eventName][$valueKey];
            if ($patchViaApplyReturnedArray) {
                if (is_array($fromEventData)) {
                    $value = $fromEventData;
                }
            } else {
                $value = $fromEventData;
            }
        }

        $returnedValues = EventGate::getReturnedValues($this->modx);
        if ($patchViaApplyReturnedArray && is_array($default)) {
            $value = EventGate::applyReturnedArray(is_array($value) ? $value : $default, $returnedValues, $valueKey);
        } elseif (array_key_exists($valueKey, $returnedValues)) {
            $value = $returnedValues[$valueKey];
        }

        unset($this->modx->eventData[$eventName]);

        return $value;
    }
}
