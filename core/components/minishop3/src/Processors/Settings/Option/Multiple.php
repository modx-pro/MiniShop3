<?php

namespace MiniShop3\Processors\Settings\Option;

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
        $methodRaw = $this->getProperty('method', false);
        if (!$methodRaw) {
            return $this->failure();
        }

        if (strcasecmp($methodRaw, 'assign') === 0) {
            return $this->processAssign();
        }

        $ids = json_decode($this->getProperty('ids'), true);
        if (!is_array($ids) || $ids === []) {
            return $this->success();
        }

        $method = ucfirst($methodRaw);
        foreach ($ids as $id) {
            $response = $this->modx->runProcessor('MiniShop3\\Processors\\Settings\\Option\\' . $method, [
                'id' => $id,
            ]);
            $failure = $this->failureFromProcessorResponse($response);
            if ($failure !== null) {
                return $failure;
            }
        }

        return $this->success();
    }

    /**
     * @return array|string
     */
    protected function processAssign()
    {
        /** @var MiniShop3 $ms3 */
        $ms3 = $this->modx->services->get('ms3');
        $categories = json_decode($this->getProperty('categories', '[]'), true);
        $options = json_decode($this->getProperty('options', '[]'), true);
        if (!is_array($categories) || !is_array($options) || $categories === [] || $options === []) {
            return $this->success();
        }

        foreach ($options as $option) {
            foreach ($categories as $category) {
                $response = $ms3->utils->runProcessor('MiniShop3\\Processors\\Settings\\Option\\Assign', [
                    'option_id' => (int) $option,
                    'category_id' => (int) $category,
                ]);
                $failure = $this->failureFromProcessorResponse($response);
                if ($failure !== null) {
                    return $failure;
                }
            }
        }

        return $this->success();
    }

    /**
     * @param mixed $response
     *
     * @return array|string|null null if OK
     */
    protected function failureFromProcessorResponse($response)
    {
        if (!$response instanceof ProcessorResponse) {
            return $this->failure();
        }
        if ($response->isError()) {
            return $response->getResponse();
        }

        return null;
    }
}
