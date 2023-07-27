<?php

namespace MiniShop3\Processors\Category;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCategory;
use MODX\Revolution\Processors\Resource\Create as CreateProcessor;

class Create extends CreateProcessor
{
    public $classKey = msCategory::class;
    public $languageTopics = ['resource', 'minishop3:default'];
    public $permission = 'mscategory_save';
    public $beforeSaveEvent = 'OnBeforeDocFormSave';
    public $afterSaveEvent = 'OnDocFormSave';

    /**
     * @return string
     */
    public function prepareAlias()
    {
        $id_as_alias = $this->workingContext->getOption('ms3_category_id_as_alias');
        if ($id_as_alias) {
            $alias = 'empty-resource-alias';
            $this->setProperty('alias', $alias);
            return $alias;
        }

        return parent::prepareAlias();
    }


    /**
     * @return bool
     */
    public function beforeSave()
    {
        $this->object->set('isfolder', true);
        return parent::beforeSave();
    }


    /**
     * @return mixed
     * @throws
     */
    public function afterSave()
    {
        if ($this->object->get('alias') === 'empty-resource-alias') {
            $this->object->set('alias', $this->object->get('id'));
            $this->object->save();
        }

        if ($this->object->get('parent')) {
            $msCategoryParent = $this->modx->getObject($this->classKey, ['id' => $this->object->get('parent')]);

            if ($msCategoryParent) {
                /** @var MiniShop3 $ms3 */
                $ms3 = $this->modx->services->get('ms3');
                $processorConfig = [
                    'category_to' => $this->object->get('id'),
                    'category_from' => $this->object->get('parent')
                ];
                $this->modx->runProcessor('MiniShop3\\Processors\\Category\\Option\\Duplicate', $processorConfig);
            }
        }

        // Update resourceMap before OnDocSaveForm event
        $results = $this->modx->cacheManager->generateContext($this->object->get('context_key'));
        if (isset($results['resourceMap'])) {
            $this->modx->context->resourceMap = $results['resourceMap'];
        }
        if (isset($results['aliasMap'])) {
            $this->modx->context->aliasMap = $results['aliasMap'];
        }

        return parent::afterSave();
    }
}
