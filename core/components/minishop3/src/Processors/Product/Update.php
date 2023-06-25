<?php

namespace MiniShop3\Processors\Product;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msProduct;
use MODX\Revolution\modX;
use MODX\Revolution\Processors\Processor;
use MODX\Revolution\Processors\Resource\Update as UpdateProcessor;

class Update extends UpdateProcessor
{
    public $classKey = msProduct::class;
    public $languageTopics = ['resource', 'minishop3:default'];
    public $permission = 'msproduct_save';
    public $beforeSaveEvent = 'OnBeforeDocFormSave';
    public $afterSaveEvent = 'OnDocFormSave';
    /** @var msProduct $object */
    public $object;

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
        /** @var Processor $processor */
        return new $className($modx, $properties);
    }

    /**
     * @return array|string
     */
    public function beforeSet()
    {
        $properties = $this->getProperties();
        $options = [];
        foreach ($properties as $key => $value) {
            if (strpos($key, 'options-') === 0) {
                $options[substr($key, 8)] = $value;
                $this->unsetProperty($key);
            }
        }
        if (!empty($options)) {
            $this->setProperty('options', $options);
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
        if ($this->workingContext->getOption('ms_product_id_as_alias')) {
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

        return parent::beforeSave();
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
