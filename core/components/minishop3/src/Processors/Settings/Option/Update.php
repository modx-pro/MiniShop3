<?php

namespace MiniShop3\Processors\Settings\Option;

use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msOption;
use MiniShop3\Model\msProductOption;
use MODX\Revolution\Processors\Model\UpdateProcessor;

class Update extends UpdateProcessor
{
    /** @var msOption $object */
    public $object;
    public $classKey = msOption::class;
    public $objectType = 'ms3_option';
    public $languageTopics = ['minishop3:default'];
    public $permission = 'mssetting_save';
    protected $oldKey = null;

    /**
     * @return bool
     */
    public function beforeSet()
    {
        $key = $this->getProperty('key');
        if (empty($key)) {
            $this->addFieldError('key', $this->modx->lexicon($this->objectType . '_err_name_ns'));
        }
        $key = str_replace('.', '_', $key);

        $oldKey = $this->object->get('key');
        if (($oldKey != $key)) {
            if ($this->doesAlreadyExist(['key' => $key])) {
                $this->addFieldError('key', $this->modx->lexicon($this->objectType . '_err_ae', ['key' => $key]));
            }

            $this->oldKey = $oldKey;
        }
        $this->setProperty('key', $key);

        return parent::beforeSet();
    }

    /**
     * @return bool
     */
    public function afterSave()
    {
        if ($categories = json_decode($this->getProperty('categories', false), true)) {
            $enabled = $disabled = [];
            foreach ($categories as $id => $checked) {
                if ($checked) {
                    $enabled[] = $id;
                } else {
                    $disabled[] = $id;
                }
            }
            if ($enabled) {
                $this->object->setCategories($enabled);
            }
            if ($disabled) {
                // Delegate to OptionCategoryService
                $service = $this->modx->services->get('ms3_option_service');
                $categoryService = $service->getCategory();
                $categoryService->removeFromCategories($this->object->get('id'), $disabled);
            }
            $this->object->set('categories', $categories);
        }
        $this->updateAssignedCategory();

        // Delegate key update to OptionSyncService
        if ($this->oldKey) {
            $service = $this->modx->services->get('ms3_option_service');
            $syncService = $service->getSync();
            $syncService->updateOptionKey($this->oldKey, $this->object->get('key'));
        }

        return parent::afterSave();
    }

    /**
     * Update option settings for specific category assignment
     *
     * When option is updated from category context,
     * update the msCategoryOption link settings
     */
    public function updateAssignedCategory()
    {
        $categoryId = $this->getProperty('category_id');
        if ($categoryId) {
            /** @var msCategoryOption $ftCat */
            $ftCat = $this->modx->getObject(msCategoryOption::class, array(
                'option_id' => $this->object->get('id'),
                'category_id' => $categoryId,
                'active' => true,
            ));

            if ($ftCat) {
                $ftCat->fromArray($this->getProperties());
                $ftCat->save();
            }
        }
    }
}
