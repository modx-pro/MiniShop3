<?php

namespace MiniShop3\Processors\Order;

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

        foreach ($ids as $id) {
            $this->modx->error->reset();
            /** @var ProcessorResponse $response */
            $response = $this->modx->runProcessor(
                'MiniShop3\\Processors\\Order\\' . $method,
                array('id' => $id),
            );

            if ($response->isError()) {
                return $response->getResponse();
            }
        }

        return $this->success();
    }
}
