<?php

namespace MiniShop3\Model;

use MODX\Revolution\modUser;
use xPDO\Om\xPDOSimpleObject;
use xPDO\xPDO;

/**
 * Class msCustomerProfile
 *
 * @property integer $id
 * @property float $account
 * @property float $spent
 * @property string $createdon
 * @property integer $referrer_id
 * @property string $referrer_code
 *
 * @package MiniShop3\Model
 */
class msCustomerProfile extends xPDOSimpleObject
{
    /**
     * @param xPDO $xpdo
     * @param string $className
     * @param mixed $criteria
     * @param bool $cacheFlag
     *
     * @return msCustomerProfile
     */
    public static function load(xPDO &$xpdo, $className, $criteria, $cacheFlag = true)
    {
        /** @var $instance msCustomerProfile */
        $instance = parent::load($xpdo, msCustomerProfile::class, $criteria, $cacheFlag);

        if (!is_object($instance) || !($instance instanceof $className)) {
            if (is_numeric($criteria) || (is_array($criteria) && !empty($criteria['id']))) {
                $id = is_numeric($criteria) ? $criteria : $criteria['id'];
                if ($xpdo->getCount(modUser::class, ['id' => $id])) {
                    $instance = $xpdo->newObject(msCustomerProfile::class);
                    $time = time();
                    $instance->set('id', $id);
                    $instance->fromArray([
                        'createdon' => $time,
                        'referrer_code' => md5($id . $time),
                    ]);
                    $instance->save();
                }
            }
        }

        return $instance;
    }
}
