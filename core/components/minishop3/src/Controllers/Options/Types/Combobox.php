<?php

namespace MiniShop3\Controllers\Options\Types;

class Combobox extends msOptionType
{
    public static $script = 'combobox.grid.js';
    public static $xtype = 'ms3-grid-combobox-options';

    /**
     * @param $field
     *
     * @return string
     */
    public function getField($field)
    {
        if (isset($field['properties']['values'])) {
            $values = json_encode(array_chunk($field['properties']['values'], 1));
        } else {
            $values = '[]';
        }

        return "{
            xtype: 'modx-combo',
            fields: ['value'],
            displayField: 'value',
            valueField: 'value',
            mode: 'local',
            store: new Ext.data.SimpleStore({
                fields: ['value'],
                data: {$values}
            })
        }";
    }
}
