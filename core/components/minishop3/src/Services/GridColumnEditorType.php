<?php

declare(strict_types=1);

namespace MiniShop3\Services;

/**
 * Allowed values for msGridField JSON config key `editor_type` (category-products inline edit).
 */
final class GridColumnEditorType
{
    public const TEXT = 'text';

    public const NUMBER = 'number';

    public const SELECT = 'select';

    public const COMBO = 'combo';
}
