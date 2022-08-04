<?php

namespace MiniShop3\Controllers\Options\Types;

class Numberfield extends msOptionType
{

    /**
    * @param $field
    *
    * @return string
    */
    public function getField($field)
    {
        return "{xtype:'numberfield'}";
    }
}
