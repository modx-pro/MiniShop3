<?php

namespace MiniShop3\Processors\Product;

use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MODX\Revolution\Processors\ModelProcessor;

class Autocomplete extends ModelProcessor
{
    /**
     * @return array|string
     */
    public function process()
    {
        $name = trim($this->getProperty('name'));
        $query = trim($this->getProperty('query'));

        if (!$name) {
            return $this->failure('ms3_product_autocomplete_err_noname');
        }

        $res = [];
        $c = $this->modx->newQuery(msProduct::class, ['class_key' => 'msProduct']);
        $c->leftJoin(msProductData::class, 'Data', 'Data.id = msProduct.id');
        $c->sortby($name, 'ASC');
        $c->select($name);
        $c->groupby($name);
        if (!empty($query)) {
            $c->where("$name LIKE '%{$query}%'");
        }
        $found = 0;
        if ($c->prepare() && $c->stmt->execute()) {
            $res = $c->stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($res as $k => $v) {
                if ($v[$name] == '') {
                    unset($res[$k]);
                } elseif ($v[$name] == $query) {
                    $found = 1;
                }
            }
        }
        if (!$found && !empty($query)) {
            $res = array_merge_recursive([[$name => $query]], $res);
        }

        return $this->outputArray($res);
    }
}
