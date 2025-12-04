<?php

namespace MiniShop3\Model;

use MiniShop3\Services\Vendor\VendorService;
use xPDO\Om\xPDOSimpleObject;

/**
 * Class msVendor
 *
 * @property integer $position
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
    /** @var VendorService|null */
    protected $vendorService;

    /**
     * @param array $ancestors
     *
     * @return bool
     */
    public function remove(array $ancestors = [])
    {
        $this->getVendorService()->removeVendor($this, $ancestors);
        return parent::remove($ancestors);
    }

    /**
     * Get vendor service (lazy loading)
     *
     * @return VendorService
     */
    protected function getVendorService(): VendorService
    {
        if ($this->vendorService === null) {
            if ($this->xpdo->services->has('ms3_vendor_service')) {
                $this->vendorService = $this->xpdo->services->get('ms3_vendor_service');
            } else {
                $this->vendorService = new VendorService($this->xpdo);
            }
        }

        return $this->vendorService;
    }
}
