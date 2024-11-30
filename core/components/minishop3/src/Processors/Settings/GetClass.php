<?php

namespace MiniShop3\Processors\Settings;

use MiniShop3\MiniShop3;
use MODX\Revolution\Processors\Processor;

class GetClass extends Processor
{
    /**
     * @return string
     */
    public function process()
    {
        $type = $this->getProperty('type');

        /** @var MiniShop3 $ms3 */
        $ms3 = $this->modx->services->get('ms3');
        $registeredServices = $ms3->services->get($type);

        $result = [];
        foreach ($registeredServices as $class) {
            $result[] = [
                'name' => $class,
                'class' => $class
            ];
        }

        return $this->outputArray($result);
    }
}
