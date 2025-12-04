<?php

namespace MiniShop3\Model;

use MODX\Revolution\modCategory;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;
use xPDO\xPDO;

/**
 * Class msProductOption
 *
 * @property integer $product_id
 * @property string $key
 * @property string $value
 *
 * @package MiniShop3\Model
 */
class msProductOption extends xPDOObject
{
    /**
     * Load options for product (frontend templates)
     *
     * Delegates to OptionService::loadOptionsForProduct()
     *
     * @param xPDO $xpdo
     * @param int $product_id
     *
     * @return array Option data with keys like ['color' => ['Red'], 'color.caption' => 'Color']
     */
    public static function loadOptions(xPDO $xpdo, $product_id)
    {
        // Delegate to OptionService
        $service = $xpdo->services->get('ms3_option_service');
        return $service->loadOptionsForProduct((int)$product_id, true);
    }

    /**
     * Save option values for product
     *
     * Delegates to OptionService::saveProductOptions()
     * REFACTORED: 66-line method reduced to service delegation
     *
     * @param int $product_id Product ID
     * @param array $options Options to save ['color' => 'Red', 'size' => ['L']]
     * @param bool $removeOther Remove options not in $options array
     * @return bool Success status
     */
    public function saveProductOptions($product_id, $options, $removeOther = true)
    {
        // Delegate to OptionService
        $service = $this->xpdo->services->get('ms3_option_service');
        return $service->saveProductOptions((int)$product_id, $options, $removeOther);
    }

    /**
     * Get option fields configuration for product form (admin)
     *
     * Delegates to OptionService::getOptionFieldsForProduct()
     *
     * @param int $product_id Product ID
     * @param int|null $parent_id Parent category ID
     * @return array Array of field configurations for ExtJS
     */
    public function getOptionFields($product_id, $parent_id = null)
    {
        // Delegate to OptionService
        $service = $this->xpdo->services->get('ms3_option_service');
        return $service->getOptionFieldsForProduct((int)$product_id, $parent_id);
    }

    /**
     * Get available option keys for product
     *
     * Delegates to OptionService::getAvailableOptionKeys()
     *
     * @param int $product_id Product ID
     * @param int|null $parent_id Parent category ID
     * @return array Array of option keys
     */
    public function getOptionKeys($product_id, $parent_id = null)
    {
        // Delegate to OptionService
        $service = $this->xpdo->services->get('ms3_option_service');
        $result = $service->getAvailableOptionKeys((int)$product_id, $parent_id);

        return $result;
    }

    /**
     * Get current option values for product
     *
     * Delegates to OptionService::getProductOptionValues()
     *
     * @param int $product_id Product ID
     * @param array $keys Optional: filter by specific keys
     * @return array Option values ['key' => [values]]
     */
    public function getForProduct($product_id, $keys = [])
    {
        // Delegate to OptionService
        $service = $this->xpdo->services->get('ms3_option_service');
        return $service->getProductOptionValues((int)$product_id, $keys);
    }

}
