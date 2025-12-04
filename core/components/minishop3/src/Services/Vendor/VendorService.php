<?php

namespace MiniShop3\Services\Vendor;

use MiniShop3\Model\msProductData;
use MiniShop3\Model\msVendor;
use MODX\Revolution\modX;

/**
 * Service for working with vendors
 *
 * Handles business logic related to product vendors,
 * including deletion and managing product associations
 */
class VendorService
{
    /** @var modX */
    protected $modx;

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Remove vendor with resetting product associations
     *
     * When removing vendor, resets vendor_id field in all products
     * that were associated with this vendor to avoid
     * broken references in database
     *
     * @param msVendor $vendor
     * @param array $ancestors
     * @return bool
     */
    public function removeVendor(msVendor $vendor, array $ancestors = []): bool
    {
        $vendorId = $vendor->get('id');

        $query = $this->modx->newQuery(msProductData::class);
        $query->command('UPDATE');
        $query->set(['vendor_id' => 0]);
        $query->where(['vendor_id' => $vendorId]);

        if ($query->prepare() && $query->stmt->execute()) {
            $affectedRows = $query->stmt->rowCount();
            if ($affectedRows > 0) {
                $this->modx->log(
                    modX::LOG_LEVEL_INFO,
                    sprintf(
                        'VendorService: Reset vendor_id for %d products when deleting vendor ID=%d',
                        $affectedRows,
                        $vendorId
                    )
                );
            }
        }

        return true;
    }

    /**
     * Get vendor statistics
     *
     * Returns number of products associated with vendor
     *
     * @param msVendor $vendor
     * @return array ['total_products' => int]
     */
    public function getVendorStatistics(msVendor $vendor): array
    {
        $vendorId = $vendor->get('id');

        $totalProducts = $this->modx->getCount(msProductData::class, [
            'vendor_id' => $vendorId
        ]);

        return [
            'total_products' => $totalProducts,
        ];
    }

    /**
     * Check if vendor can be removed
     *
     * Checks whether vendor can be safely removed
     * Can be used to warn user
     *
     * @param msVendor $vendor
     * @return array ['can_remove' => bool, 'products_count' => int, 'warnings' => array]
     */
    public function canRemoveVendor(msVendor $vendor): array
    {
        $stats = $this->getVendorStatistics($vendor);
        $warnings = [];

        if ($stats['total_products'] > 0) {
            $warnings[] = sprintf(
                'Vendor "%s" has %d products. When deleting vendor their vendor_id will be reset.',
                $vendor->get('name'),
                $stats['total_products']
            );
        }

        return [
            'can_remove' => true,
            'products_count' => $stats['total_products'],
            'warnings' => $warnings,
        ];
    }
}
