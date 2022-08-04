<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Class msOrderProduct
 *
 * @property integer $product_id
 * @property integer $order_id
 * @property string $name
 * @property integer $count
 * @property float $price
 * @property float $weight
 * @property float $cost
 * @property array $options
 * @property array $properties
 *
 * @package MiniShop3\Model
 */
class msOrderProduct extends xPDOSimpleObject
{
}
