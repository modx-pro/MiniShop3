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

    public static function isMultiValueType(?string $type): bool
    {
        return $type !== null && in_array(strtolower($type), ['combomultiple', 'combocolors', 'combooptions'], true);
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

    /**
     * Declarative schema for Vue renderer (replaces ExtJS JS-string from getField()).
     *
     * Returns: [
     *   'type'  => short type key (e.g. 'textfield', 'comboBoolean', 'comboMultiple'),
     *   'props' => type-specific props (e.g. values for combobox, colors for comboColors)
     * ]
     *
     * @param array $field Full option row (includes properties, value, required, etc.)
     * @return array
     */
    public function getSchema(array $field): array
    {
        return [
            'type' => lcfirst(substr(static::class, strrpos(static::class, '\\') + 1)),
            'props' => $field['properties'] ?? [],
        ];
    }
}
