<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Class msCustomer
 *
 * @property integer $id
 * @property integer $user_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $phone
 * @property string $token
 * @property string $password
 * @property string $email_verified_at
 * @property boolean $is_active
 * @property boolean $is_blocked
 * @property integer $failed_login_attempts
 * @property string $blocked_until
 * @property string $created_at
 * @property string $updated_at
 * @property string $last_login_at
 * @property integer $orders_count
 * @property float $total_spent
 * @property string $last_order_at
 * @property string $privacy_accepted_at
 * @property string $privacy_ip
 *
 * @package MiniShop3\Model
 */
class msCustomer extends xPDOSimpleObject
{
}
