<?php

namespace MiniShop3\Processors\Product;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msProduct;
use MiniShop3\Processors\Resource\EnsureTargetClassKeyTrait;
use MiniShop3\Utils\Utils;
use MODX\Revolution\modX;
use MODX\Revolution\Processors\Processor;
use MODX\Revolution\Processors\Resource\Update as UpdateProcessor;

class Update extends UpdateProcessor
{
    use EnsureTargetClassKeyTrait;
    use ProductDataPayloadTrait;

    public $classKey = msProduct::class;
    public $languageTopics = ['resource', 'minishop3:default'];
    public $permission = 'msproduct_save';
    public $beforeSaveEvent = 'OnBeforeDocFormSave';
    public $afterSaveEvent = 'OnDocFormSave';
    /** @var msProduct $object */
    public $object;

    /**
     * Values parsed from options-* request fields in beforeSet(). On MODX 3, getProperty('options') is
     * often empty after the parent afterSave() call, so this copy is used for
     * ProductDataService::saveOptions(..., removeOther: true) — #199. Null if the request had no options-*.
     *
     * @var array<string, mixed>|null
     */
    protected $ms3ProductFormOptions = null;

    /**
     * Allow for Resources to use derivative classes for their processors
     *
     * @static
     * @param modX $modx
     * @param string $className
     * @param array $properties
     * @return Processor
     */
    public static function getInstance(modX $modx, $className, $properties = [])
    {
        return new $className($modx, $properties);
    }

    /**
     * @return array|string
     */
    public function beforeSet()
    {
        $this->ms3ProductFormOptions = null;

        $this->captureProductDataPayload();

        $properties = $this->getProperties();
        $options = [];
        $hadOptionFieldsInRequest = false;
        foreach ($properties as $key => $value) {
            $optionKey = Utils::extractOptionKey($key);
            if ($optionKey !== null) {
                $hadOptionFieldsInRequest = true;
                $options[$optionKey] = Utils::decodeOptionValue($value);
                $this->unsetProperty($key);
            }
        }
        if ($hadOptionFieldsInRequest) {
            $this->ms3ProductFormOptions = $options;
        }
        if (!empty($options)) {
            $this->setProperty('options', $options);
        }

        if (!empty($properties['vendor_id'])) {
            $vendor_id = Utils::getVendorId($this->modx, $properties['vendor_id']);
            if ($vendor_id) {
                $this->setProperty('vendor_id', $vendor_id);
            }
        }

        return parent::beforeSet();
    }

    /**
     *
     */
    public function handleCheckBoxes()
    {
        parent::handleCheckBoxes();
        $this->setCheckbox('new');
        $this->setCheckbox('popular');
        $this->setCheckbox('favorite');
        $this->setCheckbox('show_in_tree');
    }

    /**
     * @return int|mixed|string
     */
    public function checkFriendlyAlias()
    {
        if ($this->workingContext->getOption('ms3_product_id_as_alias')) {
            $alias = $this->object->get('id');
            $this->setProperty('alias', $alias);
        } else {
            $alias = parent::checkFriendlyAlias();
        }

        return $alias;
    }

    /**
     * @return bool
     */
    public function beforeSave()
    {
        $this->object->set('isfolder', false);
        $this->applyProductDataPayload();

        return parent::beforeSave();
    }

    /**
     * Save product options after successful resource save
     *
     * @return bool|string
     */
    public function afterSave()
    {
        $result = parent::afterSave();

        if ($this->ms3ProductFormOptions !== null) {
            /** @var \MiniShop3\Model\msProductData $productData */
            $productData = $this->object->loadData();
            if ($productData) {
                $service = $this->modx->services->get('ms3_product_data_service');
                $service->saveOptions($productData, $this->ms3ProductFormOptions, true);
            }
        }

        return $result;
    }

    /**
     *
     */
    public function fixParents()
    {
        if (!$this->modx->getOption('auto_isfolder', null, true)) {
            return;
        }
        if (!empty($this->oldParent) && !($this->oldParent instanceof msCategory)) {
            $oldParentChildrenCount = $this->modx->getCount(
                \modResource::class,
                ['parent' => $this->oldParent->get('id')]
            );
            if ($oldParentChildrenCount <= 0 || $oldParentChildrenCount === null) {
                $this->oldParent->set('isfolder', false);
                $this->oldParent->save();
            }
        }

        if (!empty($this->newParent)) {
            $this->newParent->set('isfolder', true);
        }
    }
}
