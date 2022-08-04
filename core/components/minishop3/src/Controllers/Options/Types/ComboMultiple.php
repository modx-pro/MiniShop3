<?php

namespace MiniShop3\Controllers\Options\Types;

use MiniShop3\Model\msProductOption;

class ComboMultiple extends Combobox
{

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
            xtype: 'minishop-combo-options',
            allowAddNewData: false,
            pinList: true,
            mode: 'local',
            store: new Ext.data.SimpleStore({
                fields: ['value'],
                data: {$values}
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
