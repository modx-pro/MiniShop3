<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Class msVendor
 *
 * @property string $name
 * @property integer $resource_id
 * @property string $country
 * @property string $logo
 * @property string $address
 * @property string $phone
 * @property string $email
 * @property string $description
 * @property array $properties
 *
 * @package MiniShop3\Model
 */
class msVendor extends xPDOSimpleObject
{
    /**
     * @param array $ancestors
     *
     * @return bool
     */
    public function remove(array $ancestors = [])
    {
        $c = $this->xpdo->newQuery(msProductData::class);
        $c->command('UPDATE');
        $c->set([
            'vendor_id' => 0,
        ]);
        $c->where([
            'vendor_id' => $this->id,
        ]);
        $c->prepare();
        $c->stmt->execute();

        return parent::remove($ancestors);
    }
}
