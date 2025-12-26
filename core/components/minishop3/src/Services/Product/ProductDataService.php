<?php

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msCategoryMember;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductLink;
use MiniShop3\Model\msProductOption;
use MiniShop3\Processors\RemoveCatalogs;
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

        $productData->set('price', (float)$productData->get('price'));
        $productData->set('old_price', (float)$productData->get('old_price'));
        $productData->set('weight', (float)$productData->get('weight'));
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
     * @return void
     */
    public function saveOptions(msProductData $productData, ?array $options = null): void
    {
        $productId = $productData->get('id');

        if ($options === null) {
            $options = [];
            foreach ($productData->_fieldMeta as $key => $value) {
                if ($value['phptype'] === 'json' && !empty($productData->get($key))) {
                    $options = array_merge($options, $productData->get($key));
                }
            }
        }

        /** @var msProductOption $optionInstance */
        $optionInstance = $this->modx->newObject(msProductOption::class);
        $optionInstance->saveProductOptions($productId, $options);
    }

    /**
     * Save product links
     *
     * Synchronizes msProductLink table with links array from 'links' field
     * Links can be master->slave (product is master) and slave->master (product is dependent)
     *
     * @param msProductData $productData
     * @return void
     */
    public function saveLinks(msProductData $productData): void
    {
        $productId = $productData->get('id');
        $links = $productData->get('links');

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

        if ($productData->xpdo->getCount('msProductFile', ['product_id' => $productId]) > 0) {
            $source = $productData->initializeMediaSource($productData->Product->get('context_key'));
            if ($source) {
                $files = $productData->xpdo->getIterator('msProductFile', ['product_id' => $productId]);
                /** @var \msProductFile $file */
                foreach ($files as $file) {
                    $file->remove();
                }
            }
        }

        RemoveCatalogs::process($productData->xpdo, $productId);

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
     * Update product data
     *
     * Loads product by ID, updates msProductData fields and saves
     * Used in API controllers to update product data
     *
     * @param int $productId Product ID
     * @param array $data Data to update
     * @return array|null Updated data or null on error
     */
    public function updateProductData(int $productId, array $data): ?array
    {
        /** @var msProduct $product */
        $product = $this->modx->getObject(msProduct::class, $productId);

        if (!$product) {
            return null;
        }

        /** @var msProductData $productData */
        $productData = $product->loadData();

        if (!$productData) {
            return null;
        }

        $productData->fromArray($data);

        if ($productData->save()) {
            return $productData->toArray();
        }

        return null;
    }
}
