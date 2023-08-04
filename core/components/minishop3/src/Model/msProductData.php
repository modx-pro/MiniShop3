<?php

namespace MiniShop3\Model;

use MiniShop3\MiniShop3;
use MiniShop3\Processors\Gallery\RemoveCatalogs;
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
 * @property integer $vendor_id
 * @property string $made_in
 * @property boolean $new
 * @property boolean $popular
 * @property boolean $favorite
 * @property array $tags
 * @property array $color
 * @property array $size
 * @property integer $source
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

    /** @var msProductOption $msProductOptionInstance */
    protected $msProductOptionInstance = null;

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
     * @param null $cacheFlag
     *
     * @return bool
     */
    public function save($cacheFlag = null)
    {
        $this->prepareObject();
        $save = parent::save($cacheFlag);
        $this->saveProductCategories();
        $this->saveProductOptions();
        $this->saveProductLinks();

        return $save;
    }

    /**
     *
     */
    public function prepareObject()
    {
        // prepare "array" fields
        foreach ($this->getArraysValues() as $name => $array) {
            $array = $this->prepareOptionValues($array);
            parent::set($name, $array);
        }

        if ($this->isNew()) {
            parent::set('source_id', $this->xpdo->getOption('ms3_product_source_default', null, 1));
        }

        parent::set('price', (float)parent::get('price'));
        parent::set('old_price', (float)parent::get('old_price'));
        parent::set('weight', (float)parent::get('weight'));
    }

    /**
     * @param bool $force
     *
     * @return array
     */
    public function getOptionKeys($force = false)
    {
        if ($this->optionKeys === null || $force) {
            if (empty($this->msProductOptionInstance)) {
                $this->loadProductOptionInstance();
            }

            $this->optionKeys = $this->msProductOptionInstance->getOptionKeys(parent::get('id'));
        }

        return $this->optionKeys;
    }

    /**
     * @return array
     */
    public function getOptionFields()
    {
        if (empty($this->msProductOptionInstance)) {
            $this->loadProductOptionInstance();
        }
        return $this->msProductOptionInstance->getOptionFields(parent::get('id'));
    }

    private function loadProductOptionInstance()
    {
        $this->msProductOptionInstance = $this->xpdo->newObject(msProductOption::class);
    }

    /**
     * Additional product categories
     */
    protected function saveProductCategories()
    {
        $categories = parent::get('categories');
        if (is_string($categories)) {
            $categories = json_decode($categories, true);
        }
        if (is_array($categories)) {
            $id = parent::get('id');
            $parent = parent::get('parent');

            $table = $this->xpdo->getTableName(msCategoryMember::class);
            $remove = $this->xpdo->prepare("DELETE FROM {$table} WHERE product_id = $id AND category_id = ?;");
            $add = $this->xpdo->prepare("INSERT INTO {$table} (product_id, category_id) VALUES ($id, ?);");

            // Plain array with all product categories
            if (isset($categories[0])) {
                if (!parent::isNew()) {
                    $this->xpdo->removeCollection(msCategoryMember::class, ['product_id' => $id]);
                }
                foreach ($categories as $category) {
                    if ($category != $parent) {
                        $add->execute([$category]);
                    }
                }
            } // Key-value array with categories to add of remove
            else {
                foreach ($categories as $category => $selected) {
                    if (!$selected) {
                        $remove->execute([$category]);
                    } elseif ($category != $parent) {
                        $add->execute([$category]);
                    }
                }
            }
            $remove->execute([$parent]);
        }
    }

    /**
     *  Shorthand for msProductOption::saveProductOptions
     */
    protected function saveProductOptions()
    {
        if (empty($this->msProductOptionInstance)) {
            $this->loadProductOptionInstance();
        }

        $dataOptions = $this->getArraysValues();
        $originalOptions = parent::get('options');
        $options = [];
        if (!empty($dataOptions)) {
            $options = $dataOptions;
        }
        if (!empty($originalOptions)) {
            $options = array_merge($options, $originalOptions);
        }
        $this->msProductOptionInstance->saveProductOptions(parent::get('id'), $options);
    }

    /**
     *
     */
    protected function saveProductLinks()
    {
        $links = parent::get('links');
        if (is_array($links)) {
            $table = $this->xpdo->getTableName(msProductLink::class);
            $add = $this->xpdo->prepare("INSERT INTO {$table} (link, master, slave) VALUES (?, ?, ?);");
            foreach ($links as $type => $values) {
                foreach ($values as $link => $ids) {
                    foreach ($ids as $id) {
                        if ($type == 'master') {
                            $add->execute([$link, $this->id, $id]);
                        } elseif ($type == 'slave') {
                            $add->execute([$link, $id, $this->id]);
                        }
                    }
                }
            }
        }
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
     * @param null $values
     *
     * @return array|null
     */
    public function prepareOptionValues($values = null)
    {
        if ($values) {
            if (!is_array($values)) {
                $values = [$values];
            }
            // fix duplicate, empty option values
            $values = array_map('trim', $values);
            $values = array_keys(array_flip($values));
            $values = array_diff($values, ['']);
            //sort($values);

            if (empty($values)) {
                $values = null;
            }
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
        $this->xpdo->removeCollection(msProductOption::class, ['product_id' => $this->id]);
        $this->xpdo->removeCollection(msCategoryMember::class, ['product_id' => $this->id]);
        $this->xpdo->removeCollection(msProductLink::class, ['master' => $this->id, 'OR:slave:=' => $this->id]);

        $files = $this->getMany('Files', ['parent_id' => 0]);
        /** @var msProductFile $file */
        foreach ($files as $file) {
            $file->remove();
        }

        RemoveCatalogs::process($this->xpdo, $this->id);

        return parent::remove($ancestors);
    }

    /**
     *
     */
    public function generateAllThumbnails()
    {
        $files = $this->xpdo->getIterator(msProductFile::class, [
            'type' => 'image',
            'parent_id' => 0,
        ]);

        /** @var msProductFile $file */
        foreach ($files as $file) {
            $file->generateThumbnails();
        }
    }

    /**
     * @param string $ctx
     *
     * @return bool|modMediaSource|null|object
     */
    public function initializeMediaSource($ctx = '')
    {
        if ($this->mediaSource = $this->xpdo->getObject(modMediaSource::class, ['id' => $this->get('source_id')])) {
            if (empty($ctx)) {
                $product = $this->getOne('Product');
                $ctx = $product->get('context_key');
            }
            $this->mediaSource->set('ctx', $ctx);
            $this->mediaSource->initialize();

            return $this->mediaSource;
        }

        return false;
    }

    /**
     *
     */
    public function rankProductImages()
    {
        // Check if need to update files ranks
        $c = $this->xpdo->newQuery(msProductFile::class, [
            'product_id' => $this->get('id'),
            'parent_id' => 0,
        ]);
        $c->select('MAX(`position`) + 1 as max');
        $c->select('COUNT(id) as total');
        $c->having('max <> total');
        if ($c->prepare() && $c->stmt->execute()) {
            if (!$c->stmt->rowCount()) {
                return;
            }
        }

        // Update ranks
        $c = $this->xpdo->newQuery(msProductFile::class, [
            'product_id' => $this->get('id'),
            'parent_id' => 0,
        ]);
        $c->select('id');
        $c->sortby('position ASC, createdon', 'ASC');

        if ($c->prepare() && $c->stmt->execute()) {
            $table = $this->xpdo->getTableName(msProductFile::class);
            $update = $this->xpdo->prepare("UPDATE {$table} SET `position` = ? WHERE (id = ? OR parent_id = ?)");
            $ids = $c->stmt->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($ids as $k => $id) {
                $update->execute([$k, $id, $id]);
            }

            $alter = $this->xpdo->prepare("ALTER TABLE {$table} ORDER BY `position` ASC");
            $alter->execute();
        }
    }

    /**
     * @return bool|mixed
     */
    public function updateProductImage()
    {
        $this->rankProductImages();
        $c = $this->xpdo->newQuery(msProductFile::class, [
            'product_id' => $this->id,
            'parent_id' => 0,
            'type' => 'image',
            //'active' => true,
        ]);
        $c->sortby('position', 'ASC');
        $c->limit(1);
        /** @var msProductFile $file */
        $file = $this->xpdo->getObject(msProductFile::class, $c);
        if ($file) {
            $thumb = $file->getFirstThumbnail();
            $arr = [
                'image' => $file->get('url'),
                'thumb' => !empty($thumb['url'])
                    ? $thumb['url']
                    : '',
            ];
        } else {
            $arr = [
                'image' => null,
                'thumb' => null,
            ];
        }

        $this->fromArray($arr);
        if (parent::save()) {
            /** @var msProduct $product */
            if ($product = $this->getOne('Product')) {
                $product->clearCache();
            }
        }

        if (empty($arr['thumb'])) {
            $arr['thumb'] = $this->ms3->config['defaultThumb'];
        }

        return $arr['thumb'];
    }

    /**
     * @param array|string $k
     * @param null $format
     * @param null $formatTemplate
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
        $price = parent::get('price');
        if (empty($data)) {
            $data = $this->toArray();
        }
        $params = [
            'product' => $this,
            'data' => $data,
            'price' => $price,
        ];
        $response = $this->ms3->invokeEvent('msOnGetProductPrice', $params);
        if ($response['success']) {
            $price = $params['price'] = $response['data']['price'];
        }

        return $price;
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
        $weight = parent::get('weight');
        if (empty($data)) {
            $data = $this->toArray();
        }
        $params = [
            'product' => $this,
            'data' => $data,
            'weight' => $weight,
        ];
        $response = $this->ms3->invokeEvent('msOnGetProductWeight', $params);
        if ($response['success']) {
            $weight = $params['weight'] = $response['data']['weight'];
        }

        return $weight;
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
        $params = [
            'product' => $this,
            'data' => $data,
        ];
        $response = $this->ms3->invokeEvent('msOnGetProductFields', $params);
        if ($response['success']) {
            unset($response['data']['product']);
            $data = array_merge($data, $response['data']);
        }

        return $data;
    }
}
