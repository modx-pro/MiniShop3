<?php

namespace MiniShop3\Processors\Product\ProductLink;

use MiniShop3\MiniShop3;
use MODX\Revolution\Processors\ModelProcessor;
use MODX\Revolution\Processors\ProcessorResponse;

class Multiple extends ModelProcessor
{
    /**
     * @return array|string
     */
    public function process()
    {
        $method = $this->getProperty('method', false);
        if (!$method) {
            return $this->failure();
        }
        $method = ucfirst($method);
        $data = json_decode($this->getProperty('ids'), true);
        if (empty($data)) {
            return $this->success();
        }

        $this->modx->runProcessor('MiniShop3\\Processors\\Product\\ProductLink\\' . $method, $data[0]);

        return $this->success();
    }
}
