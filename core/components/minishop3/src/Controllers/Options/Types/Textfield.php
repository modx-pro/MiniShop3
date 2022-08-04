<?php

namespace MiniShop3\Controllers\Options\Types;

class Textfield extends msOptionType
{

    /**
    * @param $field
    *
    * @return string
    */
    public function getField($field)
    {
        return "{xtype:'textfield'}";
    }
}

return 'msTextfieldType';
