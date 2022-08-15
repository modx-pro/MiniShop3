<?php

namespace MiniShop3\Processors\Product;

use MiniShop3\Model\msProduct;
use MODX\Revolution\Processors\Model\GetProcessor;

class Get extends GetProcessor
{
    public $classKey = msProduct::class;
    public $languageTopics = ['minishop3:default'];
}
