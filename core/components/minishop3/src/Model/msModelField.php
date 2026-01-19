<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Class msModelField
 *
 * Configurable field for models (msOrder, msOrderAddress, msOrderProduct)
 *
 * @package MiniShop3\Model
 *
 * @property int $id
 * @property string $model Model class name
 * @property string $name Field name in model
 * @property string|null $label Lexicon key or display label
 * @property string $xtype Widget type
 * @property bool $visible Show/hide field
 * @property bool $required Field is required
 * @property int $sort_order Sort order
 * @property int|null $section_id FK to msModelFieldSection
 * @property int $width Field width (1-12 grid columns)
 * @property string|null $placeholder Input placeholder
 * @property string|null $description Field description/help text
 * @property array|null $config Additional JSON config
 *
 * @property msModelFieldSection|null $Section Related section object
 */
class msModelField extends xPDOSimpleObject
{
    public const MODEL_ORDER = 'msOrder';
    public const MODEL_ORDER_ADDRESS = 'msOrderAddress';
    public const MODEL_ORDER_PRODUCT = 'msOrderProduct';
    public const MODEL_VENDOR = 'msVendor';

    /**
     * Get available model types
     */
    public static function getAvailableModels(): array
    {
        return [
            self::MODEL_ORDER,
            self::MODEL_ORDER_ADDRESS,
            self::MODEL_ORDER_PRODUCT,
            self::MODEL_VENDOR,
        ];
    }
}
