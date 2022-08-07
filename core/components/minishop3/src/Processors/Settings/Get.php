<?php

namespace MiniShop3\Processors\Settings;

use Error;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msProduct;
use MODX\Revolution\Processors\ModelProcessor;

class Get extends ModelProcessor
{
    /**
     * @return string
     */
    public function process()
    {
        $type = $this->getProperty('type');
        $interface = 'ms' . ucfirst($type) . 'Interface';
        $handler = 'ms' . ucfirst($type) . 'Handler';

        $declared = get_declared_classes();
        /** @var MiniShop3 $ms3 */
        $ms3 = $this->modx->services->get('ms3');
        $ms3->loadCustomClasses($type);

        $declared = array_diff(get_declared_classes(), $declared);
        $available = [];
        foreach ($declared as $class) {
            if ($class == $handler || strpos($class, 'Exception') !== false) {
                continue;
            }
            try {
                $object = in_array($type, ['payment', 'delivery'])
                    ? new $class($this->modx->newObject(msProduct::class))
                    : new $class($ms3);

                if (!empty($object) && is_a($object, $interface)) {
                    $available[] = [
                        'type' => $type,
                        'class' => $class,
                    ];
                }
            } catch (Error $e) {
                // nothing
            }
        }

        return $this->outputArray($available);
    }
}
