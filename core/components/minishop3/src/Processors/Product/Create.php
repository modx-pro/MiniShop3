<?php

namespace MiniShop3\Processors\Product;

use MiniShop3\Model\msProduct;
use MiniShop3\Utils\Utils;
use MODX\Revolution\modDocument;
use MODX\Revolution\Processors\Resource\Create as CreateProcessor;

class Create extends CreateProcessor
{
    use ProductDataPayloadTrait;

    public $classKey = msProduct::class;
    public $languageTopics = ['resource', 'minishop3:default'];
    public $permission = 'msproduct_save';
    public $beforeSaveEvent = 'OnBeforeDocFormSave';
    public $afterSaveEvent = 'OnDocFormSave';
    /** @var msProduct $object */
    public $object;

    /**
     * Parsed from options-* request fields in beforeSet(). Used for
     * ProductDataService::saveOptions(..., removeOther: true) after the resource exists — same contract as
     * {@see Update::$ms3ProductFormOptions} (#199). Null when the request had no options-* keys (#257).
     *
     * @var array<string, mixed>|null
     */
    protected $ms3ProductFormOptions = null;

    /**
     * @return bool|string
     */
    public function initialize()
    {
        $requestedClassKey = $this->getProperty('class_key');
        if ($requestedClassKey === null || $requestedClassKey === '' || $requestedClassKey === modDocument::class) {
            $this->setProperty('class_key', $this->classKey);
        }

        return parent::initialize();
    }

    /**
     * @return string
     */
    public function prepareAlias()
    {
        $id_as_alias = $this->workingContext->getOption('ms3_product_id_as_alias');
        if ($id_as_alias) {
            $alias = 'empty-resource-alias';
            $this->setProperty('alias', $alias);
        } else {
            $alias = parent::prepareAlias();
        }
        return $alias;
    }

    /**
     * @return array|string
     */
    public function beforeSet()
    {
        $this->ms3ProductFormOptions = null;
        $this->setDefaultProperties([
            'show_in_tree' => $this->modx->getOption('ms3_product_show_in_tree_default', null, false),
            'hidemenu' => $this->modx->getOption('hidemenu_default', null, true),
            'source_id' => $this->modx->getOption('ms3_product_source_default', null, 1),
            'template' => $this->modx->getOption(
                'ms3_template_product_default',
                null,
                $this->modx->getOption('default_template')
            ),
        ]);

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
     * @return mixed
     */
    public function beforeSave()
    {
        $this->object->set('isfolder', false);
        $this->applyProductDataPayload();

        return parent::beforeSave();
    }

    /**
     * @return mixed
     */
    public function afterSave()
    {
        if ($this->object->get('alias') == 'empty-resource-alias') {
            $this->object->set('alias', $this->object->get('id'));
            $this->object->save();
        }
        // Update resourceMap before OnDocSaveForm event
        $results = $this->modx->cacheManager->generateContext($this->object->get('context_key'));
        if (isset($results['resourceMap'])) {
            $this->modx->context->resourceMap = $results['resourceMap'];
        }
        if (isset($results['aliasMap'])) {
            $this->modx->context->aliasMap = $results['aliasMap'];
        }

        $result = parent::afterSave();

        // Same contract as Update::afterSave (#199): only sync when the request contained options-* keys (#257).
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
}
