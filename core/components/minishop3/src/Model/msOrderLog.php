<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Class msOrderLog
 *
 * @property integer $user_id
 * @property integer $order_id
 * @property string $timestamp
 * @property string $action
 * @property string $entry
 * @property array $ip
 *
 * @package MiniShop3\Model
 */
class msOrderLog extends xPDOSimpleObject
{
}
