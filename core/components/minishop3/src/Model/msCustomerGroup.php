<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Customer segment linked to a MODX user group for catalog resource-group ACL (#669).
 *
 * `user_group_id` is intentionally non-unique: several customer segments may share
 * one MODX modUserGroup principal (same resource-group ACL, different labels/CRM).
 *
 * @property int $id
 * @property string $name
 * @property int $user_group_id MODX modUserGroup id (principal for modAccessResourceGroup)
 * @property bool $active
 * @property string $created_at
 * @property string|null $updated_at
 *
 * @package MiniShop3\Model
 */
class msCustomerGroup extends xPDOSimpleObject
{
}
