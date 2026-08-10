<?php

namespace MiniShop3\Model;

use MiniShop3\MiniShop3;
use MiniShop3\Services\Product\ProductDataService;
use MiniShop3\Services\Product\ProductImageService;
use MODX\Revolution\Sources\modMediaSource;
use xPDO\Om\xPDOSimpleObject;
use xPDO\xPDO;

/**
 * Class msProductData
 *
 * @property string $article
 * @property float $price
 * @property float $old_price
 * @property float $weight
 * @property string $image
 * @property string $thumb
 * @property integer|null $preview_file_id
 * @property integer $vendor_id
 * @property string $made_in
 * @property boolean $new
 * @property boolean $popular
 * @property boolean $favorite
 * @property array $tags
 * @property array $color
 * @property array $size
 * @property integer $source_id
 *
 * @property msProductOption[] $Options
 * @property msProductFile[] $Files
 * @property msCategoryMember[] $Categories
 *
 * @package MiniShop3\Model
 */
class msProductData extends xPDOSimpleObject
{
    /** @var MiniShop3 $ms3 */
    public $ms3;
    public $source;
    /** @var modMediaSource $mediaSource */
    public $mediaSource;
    protected $optionKeys = null;

    /** @var msProductOption|null $msProductOptionInstance */
    protected $msProductOptionInstance = null;

    /** @var ProductDataService|null */
    protected $productDataService;

    /** @var ProductImageService|null */
    protected $productImageService;

    /**
     * msProductData constructor.
     *
     * @param xPDO $xpdo
     */
    public function __construct(xPDO $xpdo)
    {
        parent::__construct($xpdo);
        if ($this->xpdo->services->has('ms3')) {
            $this->ms3 = $this->xpdo->services->get('ms3');
        }
    }

    /**
     * All json fields of product are synchronized with msProduct Options
     *
     * @param bool|int|null $cacheFlag
     *
     * @return bool
     */
    public function save($cacheFlag = null)
    {
        $service = $this->getProductDataService();
        $service->prepareObject($this);

        $save = parent::save($cacheFlag);

        if ($save) {
            $service->saveCategories($this);
            $service->saveOptions($this);
            $service->saveLinks($this);
        }

        return $save;
    }


    /**
     * @param bool $force
     *
     * @return array
     */
    public function getOptionKeys($force = false)
    {
        if ($this->optionKeys === null || $force) {
            $this->optionKeys = $this->getProductDataService()->getOptionKeys($this);
        }

        return $this->optionKeys;
    }

    /**
     * @return array
     */
    public function getOptionFields()
    {
        return $this->getProductDataService()->getOptionFields($this);
    }

    private function loadProductOptionInstance()
    {
        $this->msProductOptionInstance = $this->xpdo->newObject(msProductOption::class);
    }


    /**
     * @return array
     */
    public function getArraysValues()
    {
        $arrays = [];
        foreach ($this->_fieldMeta as $name => $field) {
            if (strtolower($field['phptype']) === 'json') {
                $arrays[$name] = parent::get($name);
            }
        }

        return $arrays;
    }

    /**
     * @param mixed $values Array, scalar (wrapped) or null. Empty result normalizes to null.
     *
     * @return array|null
     */
    public function prepareOptionValues($values = null)
    {
        if ($values === null) {
            return null;
        }

        if (!is_array($values)) {
            $values = [$values];
        }
        $values = array_map('trim', $values);
        $values = array_keys(array_flip($values));
        $values = array_diff($values, ['']);

        if (empty($values)) {
            $values = null;
        }

        return $values;
    }

    /**
     * @param array $ancestors
     *
     * @return bool
     */
    public function remove(array $ancestors = [])
    {
        $this->getProductDataService()->removeProduct($this, $ancestors);
        return parent::remove($ancestors);
    }

    /**
     *
     */
    public function generateAllThumbnails()
    {
        $this->getProductImageService()->generateAllThumbnails($this);
    }

    /**
     * @param string $ctx
     *
     * @return bool|modMediaSource|null|object
     */
    public function initializeMediaSource($ctx = '')
    {
        if (empty($ctx)) {
            $product = $this->getOne('Product');
            $ctx = $product->get('context_key');
        }

        return $this->getProductImageService()->initializeMediaSource($this, $ctx);
    }

    /**
     *
     */
    public function rankProductImages()
    {
    }

    /**
     * @return bool|mixed
     */
    public function updateProductImage()
    {
        return $this->getProductImageService()->updateProductImage($this);
    }

    /**
     * @param array|string $k
     * @param array|string|null $format
     * @param array|string|null $formatTemplate
     *
     * @return array|null
     */
    public function get($k, $format = null, $formatTemplate = null)
    {
        if (is_array($k)) {
            $array = [];
            foreach ($k as $v) {
                $array[$v] = isset($this->_fieldMeta[$v])
                    ? parent::get($v, $format, $formatTemplate)
                    : $this->get($v, $format, $formatTemplate);
            }

            return $array;
        } else {
            $value = null;
            switch ($k) {
                case 'categories':
                    $c = $this->xpdo->newQuery(msCategoryMember::class, ['product_id' => $this->id]);
                    $c->select('category_id');
                    if ($c->prepare() && $c->stmt->execute()) {
                        $value = $c->stmt->fetchAll(\PDO::FETCH_COLUMN);
                    }
                    break;
                case 'options':
                    if (empty($this->msProductOptionInstance)) {
                        $this->loadProductOptionInstance();
                    }

                    $value = $this->msProductOptionInstance->getForProduct(parent::get('id'));
                    break;
                case 'links':
                    $value = ['master' => [], 'slave' => []];
                    $c = $this->xpdo->newQuery(msProductLink::class, ['master' => $this->id]);
                    $c->select('link,slave');
                    if ($c->prepare() && $c->stmt->execute()) {
                        while ($row = $c->stmt->fetch(\PDO::FETCH_ASSOC)) {
                            if (isset($value['master'][$row['link']])) {
                                $value['master'][$row['link']][] = $row['slave'];
                            } else {
                                $value['master'][$row['link']] = [$row['slave']];
                            }
                        }
                    }

                    $c = $this->xpdo->newQuery(msProductLink::class, ['slave' => $this->id]);
                    $c->select('link,master');
                    if ($c->prepare() && $c->stmt->execute()) {
                        while ($row = $c->stmt->fetch(\PDO::FETCH_ASSOC)) {
                            if (isset($value['slave'][$row['link']])) {
                                $value['slave'][$row['link']][] = $row['master'];
                            } else {
                                $value['slave'][$row['link']] = [$row['master']];
                            }
                        }
                    }
                    break;
                default:
                    $value = parent::get($k, $format, $formatTemplate);
            }

            return $value;
        }
    }

    /**
     * Return product price
     *
     * @param array $data Any additional data for price modification
     *
     * @return mixed|string
     */
    public function getPrice($data = [])
    {
        if (empty($data)) {
            $data = $this->toArray();
        }
        return $this->getProductDataService()->getModifiedPrice($this, $data);
    }

    /**
     * Return product weight.
     *
     * @param array $data Any additional data for weight modification
     *
     * @return mixed|string
     */
    public function getWeight($data = [])
    {
        if (empty($data)) {
            $data = $this->toArray();
        }
        return $this->getProductDataService()->getModifiedWeight($this, $data);
    }

    /* Returns prepared product fields.
    *
    * @return array $result Prepared fields of product.
    * */
    public function modifyFields($data = [])
    {
        if (empty($data)) {
            $data = $this->toArray();
        }
        return $this->getProductDataService()->getModifiedFields($this, $data);
    }

    /**
     * Get product data service (lazy loading)
     *
     * @return ProductDataService
     */
    protected function getProductDataService(): ProductDataService
    {
        if ($this->productDataService === null) {
            if ($this->xpdo->services->has('ms3_product_data_service')) {
                $this->productDataService = $this->xpdo->services->get('ms3_product_data_service');
            } else {
                $this->productDataService = new ProductDataService($this->xpdo);
            }
        }

        return $this->productDataService;
    }

    /**
     * Get product image service (lazy loading)
     *
     * @return ProductImageService
     */
    protected function getProductImageService(): ProductImageService
    {
        if ($this->productImageService === null) {
            if ($this->xpdo->services->has('ms3_product_image')) {
                $this->productImageService = $this->xpdo->services->get('ms3_product_image');
            } else {
                $this->productImageService = new ProductImageService($this->xpdo);
            }
        }

        return $this->productImageService;
    }
}
