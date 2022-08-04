<?php

namespace MiniShop3\Processors\Category\Option;

use MODX\Revolution\Processors\Processor;
use MODX\Revolution\modX;

class UpdateFromGrid extends Update
{
    /**
     * @param modX $modx
     * @param string $className
     * @param array $properties
     *
     * @return Processor
     */
    public static function getInstance(modX $modx, $className, $properties = [])
    {
        return new UpdateFromGrid($modx, $properties);
    }


    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        $data = $this->getProperty('data');
        if (empty($data)) {
            return $this->modx->lexicon('invalid_data');
        }

        $data = json_decode($data, true);
        if (empty($data)) {
            return $this->modx->lexicon('invalid_data');
        }

        $this->setProperties($data);
        $this->unsetProperty('data');

        return parent::initialize();
    }
}
