<?php

namespace MiniShop3\Controllers\Options\Types;

use MiniShop3\Model\msProductOption;

class ComboColors extends msOptionType
{
    public static $script = 'combobox-colors.grid.js';
    public static $xtype = 'ms3-grid-combobox-colors';

    /**
     * @param $field
     *
     * @return string
     */
    public function getField($field)
    {
        $storeData = [];
        foreach ($field['properties']['values'] as $line) {
            $storeData[] = [$line['value'], $line['name']];
        }
        $storeData = json_encode($storeData, 1);
        $tpl = '<tpl for="." ><div class="x-combo-list-item"><span><span style="margin-right:5px;display:inline-block;width: 1rem;height:1rem;border-radius:0.25rem;background-color:{name}"></span><b>{value}</b></span></div></tpl>';

        return "{
            xtype: 'ms3-combo-options',
            allowAddNewData: false,
            displayField : 'name',
            valueField : 'value',
            displayFieldTpl: '<span style=\"display:inline-block;width: 1rem;height:1rem;border-radius:0.25rem;background-color:{name}\" title=\"{value}\"><\/span>',
            pinList: true,
            tpl: new Ext.XTemplate('" . $tpl . "'),
            mode: 'local',
            store: new Ext.data.SimpleStore({
                fields: ['name','value'],
                data: {$storeData}
            })
        }";
    }

    /**
     * @param $criteria
     *
     * @return array
     */
    public function getValue($criteria)
    {
        $result = [];

        $c = $this->xpdo->newQuery(msProductOption::class, $criteria);
        $c->select('value');
        $c->where(['value:!=' => '']);
        if ($c->prepare() && $c->stmt->execute()) {
            if (!$result = $c->stmt->fetchAll(\PDO::FETCH_ASSOC)) {
                $result = [];
            }
        }

        return $result;
    }

    /**
     * @param $criteria
     *
     * @return array
     */
    public function getRowValue($criteria)
    {
        $result = [];

        $rows = $this->getValue($criteria);
        foreach ($rows as $row) {
            $result[] = $row['value'];
        }

        return $result;
    }
}
