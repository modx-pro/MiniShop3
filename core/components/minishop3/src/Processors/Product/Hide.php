<?php

namespace MiniShop3\Processors\Product;

use MiniShop3\Model\msProduct;

class Hide extends Update
{
    public $classKey = msProduct::class;


    /**
     * @return bool
     */
    public function beforeSet()
    {
        $this->workingContext = $this->modx->getContext(
            $this->getProperty(
                'context_key',
                $this->object->get('context_key') ? $this->object->get('context_key') : 'web'
            )
        );

        $this->properties = [
            'show_in_tree' => false,
        ];

        return true;
    }
}
