<?php

namespace MiniShop3\Controllers\Options\Types;

class Textarea extends msOptionType
{

    /**
    * @param $field
    *
    * @return string
    */
    public function getField($field)
    {
        return "{xtype:'textarea'}";
    }
}
