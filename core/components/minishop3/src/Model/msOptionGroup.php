<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Class msOptionGroup
 *
 * Group for product options, replacing the legacy `msOption.modcategory_id` reference to modCategory.
 * Provides a dedicated, MS3-owned grouping mechanism: cleaner admin UX (no modCategory clutter),
 * easier sort ordering, and decoupling from the MODX category tree (#10).
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 * @property string $created_at
 * @property string|null $updated_at
 *
 * @property msOption[] $Options Options assigned to this group
 *
 * @package MiniShop3\Model
 */
class msOptionGroup extends xPDOSimpleObject
{
}
