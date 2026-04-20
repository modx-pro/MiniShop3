<?php

namespace MiniShop3\Model;

use MiniShop3\Controllers\Options\Types\msOptionType;
use MiniShop3\MiniShop3;
use xPDO\Om\xPDOSimpleObject;
use xPDO\xPDO;

/**
 * Class msOption
 *
 * @property string $key
 * @property string $caption
 * @property string $description
 * @property string $measure_unit
 * @property integer $modcategory_id
 * @property string $type
 * @property array $properties
 *
 * @property msCategoryOption[] $OptionCategories
 * @property msProductOption[] $OptionProducts
 *
 * @package MiniShop3\Model
 */
class msOption extends xPDOSimpleObject
{
    /** @var MiniShop3 $ms3 */
    public $ms3;

    /**
     * msOption constructor.
     *
     * @param xPDO $xpdo
     */
    public function __construct(xPDO $xpdo)
    {
        parent::__construct($xpdo);
        if ($this->xpdo->services->has('ms3')) {
            $this->ms3 = $this->xpdo->services->get('ms3');
        }
    }

    /**
     * @return string
     */
    public function getInputProperties()
    {
        if ($this->get('type') === 'number') {
            return '<input type="text" value="" name="option' . $this->get('id') . '">';
        }

        return '';
    }

    /**
     * Assign option to multiple categories
     *
     * Delegates to OptionService::assignOptionToCategories()
     * REFACTORED: 52-line method reduced to service delegation
     *
     * @param array $categories Array of category IDs
     * @return array Array of successfully assigned category IDs
     */
    public function setCategories($categories)
    {
        // Delegate to OptionService
        $service = $this->xpdo->services->get('ms3_option_service');
        return $service->assignOptionToCategories($this->get('id'), $categories);
    }

    /**
     * @param $product_id
     *
     * @return mixed
     */
    public function getValue($product_id)
    {
        /** @var msOptionType $type */
        $type = $this->ms3->options->getOptionType($this);

        if ($type) {
            $criteria = [
                'product_id' => $product_id,
                'key' => $this->get('key'),
            ];
            return $type->getValue($criteria);
        } else {
            return null;
        }
    }

    /**
     * @param $product_id
     *
     * @return mixed
     */
    public function getRowValue($product_id)
    {
        /** @var msOptionType $type */
        $type = $this->ms3->options->getOptionType($this);

        if ($type) {
            $criteria = [
                'product_id' => $product_id,
                'key' => $this->get('key'),
            ];
            return $type->getRowValue($criteria);
        } else {
            return null;
        }
    }

    /**
     * @param $field
     *
     * @return mixed|null
     */
    public function getManagerField($field)
    {
        /** @var msOptionType $type */
        $type = $this->ms3->options->getOptionType($this);

        if ($type) {
            return $type->getField($field);
        } else {
            return null;
        }
    }

    /**
     * Declarative schema for Vue renderer (see msOptionType::getSchema()).
     *
     * @param array $field
     * @return array|null
     */
    public function getSchema(array $field): ?array
    {
        /** @var msOptionType $type */
        $type = $this->ms3->options->getOptionType($this);

        return $type ? $type->getSchema($field) : null;
    }
}
