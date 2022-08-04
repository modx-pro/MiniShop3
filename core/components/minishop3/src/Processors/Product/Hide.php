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
        $this->properties = [
            'show_in_tree' => false,
        ];

        return true;
    }
}
