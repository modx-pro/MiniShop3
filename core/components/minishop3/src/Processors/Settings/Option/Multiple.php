<?php

namespace MiniShop3\Processors\Settings\Option;

use MiniShop3\MiniShop3;
use MODX\Revolution\Processors\ModelProcessor;
use MODX\Revolution\Processors\ProcessorResponse;

class Multiple extends ModelProcessor
{
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

        /** @var MiniShop3 $ms3 */
        $ms3 = $this->modx->services->get('ms3');

        if ($method == 'assign') {
            $categories = json_decode($this->getProperty('categories'), true);
            $options = json_decode($this->getProperty('options'), true);
            if ($categories && $options) {
                foreach ($options as $option) {
                    foreach ($categories as $category) {
                        $ms3->utils->runProcessor('MiniShop3\\Processors\\Settings\\Delivery\\Assign', [
                            'option_id' => $option,
                            'category_id' => $category,
                        ]);
                    }
                }
            }
        } elseif ($ids = json_decode($this->getProperty('ids'), true)) {
            foreach ($ids as $id) {
                /** @var ProcessorResponse $response */
                $ms3->utils->runProcessor('MiniShop3\\Processors\\Settings\\Option\\' . $method, $id);
                if ($response->isError()) {
                    return $response->getResponse();
                }
            }
        }

        return $this->success();
    }
}
