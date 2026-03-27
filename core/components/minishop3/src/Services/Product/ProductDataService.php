<?php

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msCategoryMember;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductFile;
use MiniShop3\Model\msProductLink;
use MiniShop3\Model\msProductOption;
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

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Prepare object before saving
     *
     * Performs comprehensive product data preparation:
     * - Prepare array fields (tags, color, size etc.) - remove duplicates, empty values
     * - Set source_id for new products
     * - Cast numeric fields (price, old_price, weight) to float type
     *
     * @param msProductData $productData
     * @return void
     */
    public function prepareObject(msProductData $productData): void
    {
        foreach ($productData->getArraysValues() as $name => $array) {
            $array = $productData->prepareOptionValues($array);
            $productData->set($name, $array);
        }

        if ($productData->isNew()) {
            $productData->set('source_id', $this->modx->getOption('ms3_product_source_default', null, 1));
        }

        // Cast all numeric and boolean fields (including extra fields) to proper types
        // Prevents MySQL errors when empty string '' is sent for decimal/int/tinyint columns
        foreach ($productData->_fieldMeta as $key => $meta) {
            $phptype = $meta['phptype'] ?? '';
            if ($phptype === 'float') {
                $value = $productData->get($key);
                if ($value === '' || $value === null) {
                    $productData->set($key, 0.0);
                } else {
                    $productData->set($key, (float)$value);
                }
            } elseif ($phptype === 'integer') {
                $value = $productData->get($key);
                if ($value === '' || $value === null) {
                    $productData->set($key, 0);
                } else {
                    $productData->set($key, (int)$value);
                }
            } elseif ($phptype === 'boolean') {
                $value = $productData->get($key);
                if ($value === '' || $value === null) {
                    $productData->set($key, false);
                } else {
                    $productData->set($key, (bool)$value);
                }
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
        $categories = $fields['categories'] ?? null;

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
     * @param array|null $options Options to save (if null - collected from JSON fields)
     * @param bool $removeOther Remove options not in $options array (default true for JSON fields, false for custom options)
     * @return void
     */
    public function saveOptions(msProductData $productData, ?array $options = null, bool $removeOther = true): void
    {
        $productId = $productData->get('id');

        $optionsExplicit = $options !== null;
        if ($options === null) {
            $options = [];
            foreach ($productData->_fieldMeta as $key => $value) {
                if ($value['phptype'] === 'json' && !empty($productData->get($key))) {
                    // Use field name as key, not numeric index from array_merge
                    $options[$key] = $productData->get($key);
                }
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

        // Early return if no plugins registered for this event
        if (empty($this->modx->eventMap[$eventName])) {
            return $price;
        }

        // Initialize eventData for plugin chaining
        $this->modx->eventData[$eventName] = [
            'price' => $price,
            'data' => $data,
        ];

        // Clear previous returnedValues
        if (isset($this->modx->event->returnedValues)) {
            $this->modx->event->returnedValues = null;
        }

        $this->modx->invokeEvent($eventName, [
            'price' => $price,
            'data' => $data,
        ]);

        // Priority 1: Read from eventData (plugin chain result)
        if (isset($this->modx->eventData[$eventName]['price'])) {
            $price = $this->modx->eventData[$eventName]['price'];
        }

        // Priority 2: Check returnedValues for backward compatibility
        if (isset($this->modx->event->returnedValues['price'])) {
            $price = $this->modx->event->returnedValues['price'];
        }

        // Cleanup
        unset($this->modx->eventData[$eventName]);

        return $price;
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

        // Early return if no plugins registered for this event
        if (empty($this->modx->eventMap[$eventName])) {
            return $weight;
        }

        // Initialize eventData for plugin chaining
        $this->modx->eventData[$eventName] = [
            'weight' => $weight,
            'data' => $data,
        ];

        // Clear previous returnedValues
        if (isset($this->modx->event->returnedValues)) {
            $this->modx->event->returnedValues = null;
        }

        $this->modx->invokeEvent($eventName, [
            'weight' => $weight,
            'data' => $data,
        ]);

        // Priority 1: Read from eventData (plugin chain result)
        if (isset($this->modx->eventData[$eventName]['weight'])) {
            $weight = $this->modx->eventData[$eventName]['weight'];
        }

        // Priority 2: Check returnedValues for backward compatibility
        if (isset($this->modx->event->returnedValues['weight'])) {
            $weight = $this->modx->event->returnedValues['weight'];
        }

        // Cleanup
        unset($this->modx->eventData[$eventName]);

        return $weight;
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

        // Early return if no plugins registered for this event
        if (empty($this->modx->eventMap[$eventName])) {
            return $data;
        }

        // Initialize eventData for plugin chaining
        $this->modx->eventData[$eventName] = [
            'data' => $data,
        ];

        // Clear previous returnedValues
        if (isset($this->modx->event->returnedValues)) {
            $this->modx->event->returnedValues = null;
        }

        $this->modx->invokeEvent($eventName, ['data' => $data]);

        // Priority 1: Read from eventData (plugin chain result)
        if (isset($this->modx->eventData[$eventName]['data']) && is_array($this->modx->eventData[$eventName]['data'])) {
            $data = $this->modx->eventData[$eventName]['data'];
        }

        // Priority 2: Check returnedValues for backward compatibility
        if (isset($this->modx->event->returnedValues['data']) && is_array($this->modx->event->returnedValues['data'])) {
            $data = $this->modx->event->returnedValues['data'];
        }

        // Cleanup
        unset($this->modx->eventData[$eventName]);

        return $data;
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

        $filtered = array_intersect_key($data, array_flip(self::$allowedUpdateFields));
        $resourceData = array_intersect_key($data, array_flip(self::$allowedResourceFields));

        if (!$this->validateProductDataUpdate($filtered)) {
            return ['ok' => false, 'code' => self::ERROR_VALIDATION, 'message' => 'Validation failed'];
        }

        $fieldsToUpdate = array_intersect_key($filtered, array_flip(self::$allowedUpdateFields));
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
}
