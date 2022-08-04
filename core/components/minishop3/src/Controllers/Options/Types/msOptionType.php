<?php

namespace MiniShop3\Controllers\Options\Types;

use MiniShop3\Model\msOption;
use MiniShop3\Model\msProductOption;
use xPDO\xPDO;

abstract class msOptionType
{
    /** @var msOption $option */
    public $option;
    /** @var xPDO $xpdo */
    public $xpdo;
    /** @var array $config */
    public $config = [];
    public static $script = null;
    public static $xtype = null;

    /**
     * msOptionType constructor.
     *
     * @param msOption $option
     * @param array $config
     */
    public function __construct(msOption $option, array $config = [])
    {
        $this->option = $option;
        $this->xpdo = $option->xpdo;
        $this->config = array_merge($this->config, $config);
    }

    /**
     * @param $criteria
     *
     * @return mixed|null
     *
     * @TODO Maybe vulnerable
     */
    public function getValue($criteria)
    {
        /** @var msProductOption $value */
        $value = $this->xpdo->getObject(msProductOption::class, $criteria);
        return ($value) ? $value->get('value') : null;
    }

    /**
     * @param $criteria
     *
     * @return mixed|null
     */
    public function getRowValue($criteria)
    {
        return $this->getValue($criteria);
    }

    /**
     * @param $field
     *
     * @return mixed
     */
    abstract public function getField($field);
}