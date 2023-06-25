<?php

namespace MiniShop3\Processors\Product;

use MiniShop3\Model\msProduct;
use MODX\Revolution\Processors\ModelProcessor;

class Multiple extends ModelProcessor
{
    public $classKey = msProduct::class;
    /**
     * @return array|string]
     *
     * @throws
     */
    public function process()
    {
        $method = $this->getProperty('method', false);
        if (!$method) {
            return $this->failure();
        }
        $method = ucfirst($method);
        $ids = json_decode($this->getProperty('ids'), true);
        if (empty($ids)) {
            return $this->success();
        }

        foreach ($ids as $id) {
            $this->modx->runProcessor('MiniShop3\\Processors\\Product\\' . $method, [
                'id' => $id,
            ]);
        }

        return $this->success();
    }
}
