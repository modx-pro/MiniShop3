<?php

namespace MiniShop3\Processors\Settings\Option;

use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msOption;
use MiniShop3\Model\msProductOption;
use MODX\Revolution\Processors\Model\DuplicateProcessor;

class Duplicate extends DuplicateProcessor
{
    /** @var msOption $object */
    public $object;
    public $classKey = msOption::class;
    public $objectType = 'ms_option';
    public $languageTopics = ['default', 'minishop2:default'];
    public $nameField = 'key';


    /**
     *
     */
    public function afterSave()
    {
        $this->duplicateCategories();
        $this->duplicateProducts();
    }


    /**
     *
     */
    public function duplicateCategories()
    {
        if ($this->getProperty('copy_categories', false)) {
            $cats = $this->object->getMany('OptionCategories');
            if (is_array($cats) && !empty($cats)) {
                /** @var msCategoryOption $cat */
                foreach ($cats as $cat) {
                    /** @var msCategoryOption $newCat */
                    $newCat = $this->modx->newObject(msCategoryOption::class);
                    $newCat->fromArray($cat->toArray());
                    $newCat->set('option_id', $this->newObject->get('id'));
                    $newCat->set('category_id', $cat->get('category_id'));
                    $newCat->save();
                }
            }
        }
    }


    /**
     *
     */
    public function duplicateProducts()
    {
        if ($this->getProperty('copy_values', false)) {
            $products = $this->object->getMany('OptionProducts');
            if (is_array($products) && !empty($products)) {
                /** @var msProductOption $product */
                foreach ($products as $product) {
                    /** @var msProductOption $newProduct */
                    $newProduct = $this->modx->newObject(msProductOption::class);
                    $newProduct->set('key', $this->newObject->get('key'));
                    $newProduct->set('product_id', $product->get('product_id'));
                    $newProduct->set('value', $product->get('value'));
                    $newProduct->save();
                }
            }
        }
    }
}
