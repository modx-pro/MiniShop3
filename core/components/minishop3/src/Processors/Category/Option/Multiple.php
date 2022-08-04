<?php

namespace MiniShop3\Processors\Category\Option;

use MiniShop3\MiniShop3;
use MODX\Revolution\Processors\ProcessorResponse;
use MODX\Revolution\Processors\ModelProcessor;

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
        $ids = json_decode($this->getProperty('ids'), true);
        if (empty($ids)) {
            return $this->success();
        }

        /** @var MiniShop3 $ms3 */
        $ms3 = $this->modx->services->get('ms3');

        foreach ($ids as $key) {
            /** @var ProcessorResponse $response */
            $ms3->utils->runProcessor('MiniShop3\\Processors\\Category\\Option\\' . $method, $key);
            if ($response->isError()) {
                return $response->getResponse();
            }
        }

        return $this->success();
    }
}
