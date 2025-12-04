<?php

namespace MiniShop3\Model;

use MiniShop3\MiniShop3;
use MiniShop3\Services\Product\ProductService;
use MODX\Revolution\modResource;
use xPDO\Om\xPDOObject;
use xPDO\xPDO;

/**
 * Class msProduct
 *
 * @property string $class_key
 *
 * @property msProductData $Data
 * @property msCategoryMember[] $Categories
 * @property msProductOption[] $Options
 *
 * @package MiniShop3\Model
 */
class msProduct extends modResource
{
    /** @var MiniShop3 $ms3 */
    public $ms3;
    public $showInContextMenu = true;
    public $allowChildrenResources = false;
    /** @var msProductData $data */
    protected $Data;
    protected $dataRelated = [];
    /** @var msVendor $Vendor */
    protected $Vendor;
    protected $options = null;
    protected $originalFieldMeta;

    /** @var ProductService|null */
    protected $productService;

    /**
     * msProduct constructor.
     *
     * @param xPDO $xpdo
     */
    public function __construct(xPDO &$xpdo)
    {
        parent::__construct($xpdo);
        if ($xpdo->services->has('ms3')) {
            $this->ms3 = $xpdo->services->get('ms3');
        }
        $this->originalFieldMeta = $this->_fieldMeta;

        $aggregates = $this->xpdo->getAggregates(msProductData::class);
        $composites = $this->xpdo->getComposites(msProductData::class);
        $this->dataRelated = array_merge(array_keys($aggregates), array_keys($composites));
    }

    /**
     * @param string $k
     * @param null $v
     * @param string $vType
     *
     * @return bool
     */
    public function set($k, $v = null, $vType = '')
    {
        return isset($this->originalFieldMeta[$k])
            ? parent::set($k, $v, $vType)
            : $this->loadData()->set($k, $v, $vType);
    }

    /**
     * @param xPDO $xpdo
     * @param string $className
     * @param null $criteria
     * @param bool $cacheFlag
     *
     * @return array
     */
    public static function loadCollection(xPDO &$xpdo, $className, $criteria = null, $cacheFlag = true)
    {
        if (!is_object($criteria)) {
            $criteria = $xpdo->getCriteria($className, $criteria, $cacheFlag);
        }
        $xpdo->addDerivativeCriteria($className, $criteria);
        return parent::loadCollection($xpdo, $className, $criteria, $cacheFlag);
    }

    /**
     * @param xPDO $modx
     *
     * @return string
     */
    public static function getControllerPath(xPDO &$modx)
    {
        $path = $modx->getOption(
            'ms3_core_path',
            null,
            $modx->getOption('core_path') . 'components/minishop3/'
        );
        return $path . 'controllers/product/';
    }

    /**
     * @return array
     */
    public function getContextMenuText()
    {
        $this->xpdo->lexicon->load('minishop3:default');

        return [
            'text_create' => $this->xpdo->lexicon('ms3_product'),
            'text_create_here' => $this->xpdo->lexicon('ms3_product_create_here'),
        ];
    }

    /**
     * @param xPDO $xpdo
     * @param string $className
     * @param null $criteria
     * @param bool $cacheFlag
     *
     * @return msProduct
     */
    public static function load(xPDO &$xpdo, $className, $criteria = null, $cacheFlag = true)
    {
        if (!is_object($criteria)) {
            $criteria = $xpdo->getCriteria($className, $criteria, $cacheFlag);
        }
        $xpdo->addDerivativeCriteria($className, $criteria);
        return parent::load($xpdo, $className, $criteria, $cacheFlag);
    }

    /**
     * @return null|string
     */
    public function getResourceTypeName()
    {
        $this->xpdo->lexicon->load('minishop3:default');

        return $this->xpdo->lexicon('ms3_product_type');
    }

    /**
     * @param null $cacheFlag
     *
     * @return bool
     */
    public function save($cacheFlag = null)
    {
        $oldClassKey = parent::get('class_key');

        $this->getProductService()->handleProductSave($this, $oldClassKey);

        return parent::save($cacheFlag);
    }

    /**
     * @param array|string $k
     * @param null $format
     * @param null $formatTemplate
     *
     * @return array|mixed|null|xPDOObject
     */
    public function get($k, $format = null, $formatTemplate = null)
    {
        if (is_array($k)) {
            $array = [];
            foreach ($k as $v) {
                $array[$v] = isset($this->originalFieldMeta[$v])
                    ? parent::get($v, $format, $formatTemplate)
                    : $this->get($v, $format, $formatTemplate);
            }

            return $array;
        }

        if (isset($this->originalFieldMeta[$k])) {
            return parent::get($k, $format, $formatTemplate);
        }

        if (strpos($k, 'vendor_') !== false || strpos($k, 'vendor.') !== false) {
            return $this->loadVendor()->get(substr($k, 7), $format, $formatTemplate);
        }
        if (isset($this->loadData()->_fields[$k])) {
            return $this->loadData()->get($k, $format, $formatTemplate);
        }
        if (
            in_array($k, $this->loadData()->getOptionKeys()) ||
            (($optFields = explode('.', $k)) && in_array($optFields[0], $this->loadData()->getOptionKeys()))
        ) {
            if (isset($this->$k)) {
                return $this->$k;
            }
            $this->loadOptions();
            return $this->options[$k] ?? null;
        }
        return parent::get($k, $format, $formatTemplate);
    }

    /**
     * @param string $key
     * @param mixed $val
     *
     * @return bool
     */
    protected function _setRaw($key, $val)
    {
        return isset($this->originalFieldMeta[$key])
            ? parent::_setRaw($key, $val)
            : $this->loadData()->_setRaw($key, $val);
    }

    /**
     * @param string $keyPrefix
     * @param bool $rawValues
     * @param bool $excludeLazy
     * @param bool $includeRelated
     *
     * @return array
     */
    public function toArray($keyPrefix = '', $rawValues = false, $excludeLazy = false, $includeRelated = false)
    {
        $original = parent::toArray($keyPrefix, $rawValues, $excludeLazy, $includeRelated);
        $additional = array_merge(
            $this->loadData()->toArray($keyPrefix, $rawValues, $excludeLazy, $includeRelated),
            $this->loadOptions(),
            $this->loadVendor()->toArray($keyPrefix . 'vendor.', $rawValues, $excludeLazy, $includeRelated)
        );
        $intersect = array_keys(array_intersect_key($original, $additional));
        foreach ($intersect as $key) {
            unset($additional[$key]);
        }

        return array_merge($original, $additional);
    }

    /**
     * @return msProductData|null|object|\xPDOObject
     */
    public function loadData()
    {
        if (!is_object($this->Data) || !($this->Data instanceof msProductData)) {
            $this->Data = $this->getOne('Data');
            if (!$this->Data) {
                $this->Data = $this->xpdo->newObject(msProductData::class);
                parent::addOne($this->Data, 'Data');
            }
        }

        return $this->Data;
    }

    /**
     * Loads product vendor
     */
    public function loadVendor()
    {
        if (!is_object($this->Vendor) || !($this->Vendor instanceof msVendor)) {
            if (!$this->Vendor = $this->getOne('Vendor')) {
                $this->Vendor = $this->xpdo->newObject(msVendor::class);
            }
        }

        return $this->Vendor;
    }

    /**
     * Loads product options
     */
    public function loadOptions()
    {
        if ($this->options === null) {
            $this->options = $this->xpdo->call(msProductOption::class, 'loadOptions', [
                $this->xpdo,
                $this->get('id'),
            ]);
        }

        return $this->options;
    }

    /**
     * @param string $alias
     * @param null $criteria
     * @param bool $cacheFlag
     *
     * @return null|xPDOObject
     */
    public function & getOne($alias, $criteria = null, $cacheFlag = true)
    {
        $object = in_array($alias, $this->dataRelated)
            ? $this->loadData()->getOne($alias, $criteria, $cacheFlag)
            : parent::getOne($alias, $criteria, $cacheFlag);

        return $object;
    }

    /**
     * @param xPDOObject $obj
     * @param string $alias
     *
     * @return bool
     */
    public function addOne(&$obj, $alias = '')
    {
        if (empty($alias)) {
            if ($obj->_alias == $obj->_class) {
                $aliases = $this->_getAliases($obj->_class, 1);
                if (!empty($aliases)) {
                    $obj->_alias = reset($aliases);
                }
            }
            $alias = $obj->_alias;
        }

        return in_array($alias, $this->dataRelated)
            ? $this->loadData()->addOne($obj, $alias)
            : parent::addOne($obj, $alias);
    }

    /**
     * @param string $alias
     * @param null $criteria
     * @param bool $cacheFlag
     *
     * @return array
     */
    public function & getMany($alias, $criteria = null, $cacheFlag = false)
    {
        $objects = in_array($alias, $this->dataRelated)
            ? $this->loadData()->getMany($alias, $criteria, $cacheFlag)
            : parent::getMany($alias, $criteria, $cacheFlag);

        return $objects;
    }

    /**
     * @param mixed $obj
     * @param string $alias
     *
     * @return bool
     */
    public function addMany(&$obj, $alias = '')
    {
        return in_array($alias, $this->dataRelated)
            ? $this->loadData()->addMany($obj, $alias)
            : parent::addMany($obj, $alias);
    }

    /**
     * Returns names of fields for msProduct and msProductData
     *
     * @return array
     */
    public function getDataFieldsNames()
    {
        return array_keys($this->loadData()->_fieldMeta);
    }

    /**
     * @return array
     */
    public function getResourceFieldsNames()
    {
        return array_keys($this->originalFieldMeta);
    }

    /**
     * @return array
     */
    public function getAllFieldsNames()
    {
        return array_merge($this->getResourceFieldsNames(), $this->getDataFieldsNames());
    }

    /**
     * @param array $options
     *
     * @return msProduct
     */
    public function duplicate(array $options = [])
    {
        $newProduct = parent::duplicate($options);

        $this->getProductService()->duplicateProduct($this, $newProduct);

        return $newProduct;
    }

    /**
     * Returns array with all neighborhood products
     *
     * @return array $arr Array with neighborhood from left and right
     */
    public function getNeighborhood()
    {
        return $this->getProductService()->getNeighborProducts($this);
    }

    /**
     * @return string
     */
    public function process()
    {
        $this->getProductService()->processForDisplay($this);

        return parent::process();
    }

    /**
     *
     */
    public function generateAllThumbnails()
    {
        $this->loadData()->generateAllThumbnails();
    }

    /**
     * @return bool|\modMediaSource|null|object
     */
    public function initializeMediaSource()
    {
        return $this->loadData()->initializeMediaSource(parent::get('context_key'));
    }

    /**
     * @return bool|mixed
     */
    public function updateProductImage()
    {
        return $this->loadData()->updateProductImage();
    }

    /**
     * @param array $data
     *
     * @return mixed|string
     */
    public function getPrice($data = [])
    {
        return $this->loadData()->getPrice($data);
    }

    /**
     * @param array $data
     *
     * @return mixed|string
     */
    public function getWeight($data = [])
    {
        return $this->loadData()->getWeight($data);
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function modifyFields($data = [])
    {
        return $this->loadData()->modifyFields($data);
    }

    /**
     * Get product service (lazy loading)
     *
     * @return ProductService
     */
    protected function getProductService(): ProductService
    {
        if ($this->productService === null) {
            if ($this->xpdo->services->has('ms3_product_service')) {
                $this->productService = $this->xpdo->services->get('ms3_product_service');
            } else {
                $this->productService = new ProductService($this->xpdo);
            }
        }

        return $this->productService;
    }
}
