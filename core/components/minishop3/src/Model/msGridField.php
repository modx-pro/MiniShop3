<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Class msGridField
 * @package MiniShop3\Model
 *
 * Model for storing grid column configuration (customers, orders, products, etc.)
 *
 * @property int $id
 * @property string $grid_key Grid key (customers, orders, products)
 * @property string $field_name Field name
 * @property string|null $label Direct label (overrides lexicon)
 * @property string|null $lexicon_key Lexicon key for label
 * @property bool $visible Column visibility
 * @property int $sort_order Display order
 * @property bool $sortable Whether column is sortable
 * @property bool $filterable Whether column is filterable
 * @property bool $frozen Whether column is frozen (left/right)
 * @property string|null $width Column width (e.g.: 150px, 20%)
 * @property string|null $min_width Minimum width
 * @property array|null $config Additional configuration (template, type, format)
 * @property bool $is_system System field (cannot be deleted)
 * @property bool $is_default Default field (from seed)
 * @property string $created_at
 * @property string $updated_at
 */
class msGridField extends xPDOSimpleObject
{
}
