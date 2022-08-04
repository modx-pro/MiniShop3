<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Class msOrderStatus
 *
 * @property string $name
 * @property string $description
 * @property string $color
 * @property integer $email_user
 * @property integer $email_manager
 * @property string $subject_user
 * @property string $subject_manager
 * @property integer $body_user
 * @property integer $body_manager
 * @property integer $active
 * @property integer $final
 * @property integer $fixed
 * @property integer $position
 * @property integer $editable
 *
 * @package MiniShop3\Model
 */
class msOrderStatus extends xPDOSimpleObject
{
}
