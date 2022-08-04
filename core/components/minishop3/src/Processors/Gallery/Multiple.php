<?php

namespace MiniShop3\Processors\Gallery;

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
        $ids = json_decode($this->getProperty('ids'), true);
        if (empty($ids)) {
            return $this->success();
        }

        /** @var MiniShop3 $ms3 */
        $ms3 = $this->modx->services->get('ms3');

        foreach ($ids as $id) {
            /** @var ProcessorResponse $response */
            $ms3->utils->runProcessor('MiniShop3\\Processors\\Gallery\\' . $method, ['id' => $id]);
            if ($response->isError()) {
                return $response->getResponse();
            }
        }

        return !empty($response)
            ? $response->getResponse()
            : $this->success();
    }
}
