<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOObject;

/**
 * Class msResourceSeo
 *
 * Native SEO overrides for msProduct and msCategory resources (#790). One row per
 * resource (PK = resource_id). Nullable string columns; empty values fall back to
 * the public SEO builder defaults (pagetitle/longtitle, introtext/description, …).
 *
 * @property int $resource_id
 * @property string|null $title
 * @property string|null $description
 * @property string|null $canonical
 * @property string|null $robots
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_image
 *
 * @package MiniShop3\Model
 */
class msResourceSeo extends xPDOObject
{
}
