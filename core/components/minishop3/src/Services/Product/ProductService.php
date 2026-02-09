<?php

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msVendor;
use MiniShop3\MiniShop3;
use MODX\Revolution\modResource;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

/**
 * Service for working with products
 *
 * Extracts business logic from msProduct model into separate service
 * to improve testability and separation of concerns
 */
class ProductService
{
    /** @var modX */
    protected $modx;

    /** @var MiniShop3|null */
    protected $ms3;

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;

        if ($modx->services->has('ms3')) {
            $this->ms3 = $modx->services->get('ms3');
        }
    }

    /**
     * Handle product save
     *
     * If product changes type (was not msProduct, became msProduct),
     * then remove old msProductData and show in tree
     *
     * @param msProduct $product
     * @param string $oldClassKey Old class_key before save
     * @return bool
     */
    public function handleProductSave(msProduct $product, string $oldClassKey): bool
    {
        if (!$product->isNew() && $oldClassKey !== msProduct::class) {
            $product->loadData()->remove();

            $product->set('show_in_tree', true);
        } else {
            $product->loadData();
        }

        return true;
    }

    /**
     * Handle resource-to-product conversion
     *
     * When a regular resource is converted to msProduct (class_key change):
     * 1. Creates msProductData record (required for product to appear in grids)
     * 2. Sets show_in_tree based on ms3_product_show_in_tree_default setting
     *
     * @param modResource $resource The resource being saved
     * @return bool True if conversion was handled, false if not applicable
     */
    public function handleConversion(modResource $resource): bool
    {
        // Only process if resource is now an msProduct
        if ($resource->get('class_key') !== msProduct::class) {
            return false;
        }

        // Check if msProductData exists - if not, this is a converted resource
        $productData = $this->modx->getObject(msProductData::class, ['id' => $resource->get('id')]);
        if ($productData) {
            // Product data exists - this is a normal product, not a conversion
            return false;
        }

        // Create msProductData record for converted resource
        $productData = $this->modx->newObject(msProductData::class);
        $productData->set('id', $resource->get('id'));
        $productData->save();

        // Set show_in_tree based on system setting
        $showInTree = (bool)$this->modx->getOption('ms3_product_show_in_tree_default', null, false);
        $resource->set('show_in_tree', $showInTree);
        $resource->save();

        $this->modx->log(
            modX::LOG_LEVEL_INFO,
            "[MiniShop3] Resource #{$resource->get('id')} converted to product: msProductData created, show_in_tree=" . ($showInTree ? 'true' : 'false')
        );

        return true;
    }

    /**
     * Get neighbor products (left and right)
     *
     * Used for navigation through products of the same level
     *
     * @param msProduct $product
     * @return array ['left' => [id1, id2, ...], 'right' => [id3, id4, ...]]
     */
    public function getNeighborProducts(msProduct $product): array
    {
        $query = $this->modx->newQuery(msProduct::class, [
            'parent' => $product->get('parent'),
            'class_key' => msProduct::class
        ]);
        $query->sortby('menuindex', 'ASC');
        $query->select('id');

        if (!$query->prepare() || !$query->stmt->execute()) {
            return ['left' => [], 'right' => []];
        }

        $ids = $query->stmt->fetchAll(\PDO::FETCH_COLUMN);
        $currentIndex = array_search($product->get('id'), $ids);

        if ($currentIndex === false) {
            return ['left' => [], 'right' => []];
        }

        $left = [];
        $right = [];

        foreach ($ids as $index => $id) {
            if ($index < $currentIndex) {
                $left[] = $id;
            } elseif ($index > $currentIndex) {
                $right[] = $id;
            }
        }

        return [
            'left' => array_reverse($left),
            'right' => $right,
        ];
    }

    /**
     * Process product for frontend display
     *
     * Prepares product data, formats prices, weights,
     * sets placeholders and loads lexicons
     *
     * @param msProduct $product
     * @return void
     */
    public function processForDisplay(msProduct $product): void
    {
        /** @var msProductData $data */
        if ($data = $product->getOne('Data')) {
            $placeholders = $data->toArray();

            $originalPrice = $placeholders['price'];
            $placeholders['price'] = $product->getPrice($placeholders);

            if ($placeholders['price'] < $originalPrice) {
                $placeholders['old_price'] = $originalPrice;
            }

            $placeholders['weight'] = $product->getWeight($placeholders);

            $placeholders = $product->modifyFields($placeholders);

            if ($this->ms3) {
                $placeholders['price'] = $this->ms3->format->price($placeholders['price']);
                $placeholders['old_price'] = $this->ms3->format->price($placeholders['old_price']);
                $placeholders['weight'] = $this->ms3->format->weight($placeholders['weight']);
            }

            unset($placeholders['id']);

            $this->modx->setPlaceholders($placeholders);

            $product->loadOptions();
            $this->modx->setPlaceholders($product->options ?? []);
        }

        /** @var msVendor $vendor */
        if ($vendor = $product->getOne('Vendor')) {
            $this->modx->setPlaceholders($vendor->toArray('vendor.'));
        }

        $this->modx->lexicon->load('minishop3:default');
        $this->modx->lexicon->load('minishop3:cart');
        $this->modx->lexicon->load('minishop3:product');
    }
}
