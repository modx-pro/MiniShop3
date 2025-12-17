<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Class msModelFieldSection
 *
 * Sections for grouping model fields (msOrder, msOrderAddress, msOrderProduct)
 *
 * @package MiniShop3\Model
 *
 * @property int $id
 * @property string $model Model class name
 * @property string $section_key Unique section key within model
 * @property string|null $label Direct label text
 * @property string|null $lexicon_key Lexicon key for translation
 * @property bool $hidden Hidden from display
 * @property int $sort_order Sort order
 * @property bool $is_default Is default/system section
 *
 * @property msModelField[] $Fields Related fields in this section
 */
class msModelFieldSection extends xPDOSimpleObject
{
    public const MODEL_ORDER = 'msOrder';
    public const MODEL_ORDER_ADDRESS = 'msOrderAddress';
    public const MODEL_ORDER_PRODUCT = 'msOrderProduct';

    /**
     * Get available model types
     */
    public static function getAvailableModels(): array
    {
        return [
            self::MODEL_ORDER,
            self::MODEL_ORDER_ADDRESS,
            self::MODEL_ORDER_PRODUCT,
        ];
    }
}
