<?php

declare(strict_types=1);

namespace MiniShop3\Services\Option;

use xPDO\xPDO;

/**
 * Service for loading options data
 *
 * Extracted from msProductOption model to separate concerns
 * Handles all option loading operations including:
 * - Loading options for products (frontend)
 * - Building option fields configuration (admin forms)
 * - Getting available option keys
 */
class OptionLoaderService
{
    /** @var xPDO */
    protected $xpdo;

    /** @var CaptionOverlayResolver */
    protected $captionOverlayResolver;

    /** @var ProductOptionLoader */
    protected $productOptionLoader;

    /** @var AdminOptionFields */
    protected $adminOptionFields;

    public function __construct(
        xPDO $xpdo,
        ?CaptionOverlayResolver $captionOverlayResolver = null,
        ?ProductOptionLoader $productOptionLoader = null,
        ?AdminOptionFields $adminOptionFields = null
    ) {
        $this->xpdo = $xpdo;
        $this->captionOverlayResolver = $captionOverlayResolver ?? new CaptionOverlayResolver($xpdo);
        $this->productOptionLoader = $productOptionLoader
            ?? new ProductOptionLoader($xpdo, $this->captionOverlayResolver);
        $this->adminOptionFields = $adminOptionFields
            ?? new AdminOptionFields($xpdo, $this->captionOverlayResolver);
    }

    /**
     * Load options for single product (for frontend templates)
     *
     * Replaces: msProductOption::loadOptions()
     *
     * @param int $productId Product ID
     * @param bool $includeMetadata Include option group metadata (default: true for backward compatibility)
     * @return array Option data with keys like ['color' => ['Red'], 'color.caption' => 'Color']
     */
    public function loadForProduct(int $productId, bool $includeMetadata = true): array
    {
        return $this->productOptionLoader->loadForProduct($productId, $includeMetadata);
    }

    /**
     * Load options for multiple products (batch loading for catalog pages)
     *
     * NEW METHOD - prevents N+1 problem in catalog listings
     *
     * @param array $productIds Array of product IDs
     * @param bool $includeMetadata Include option group metadata
     * @return array Nested array: [product_id => option_data]
     */
    public function loadForProducts(array $productIds, bool $includeMetadata = false): array
    {
        return $this->productOptionLoader->loadForProducts($productIds, $includeMetadata);
    }

    /**
     * Get option fields configuration for product form (admin)
     *
     * Replaces: msProductOption::getOptionFields()
     * Already optimized: N+1 Problem solved with preloading
     *
     * @param int $productId Product ID
     * @param int|null $parentId Parent category ID (optional)
     * @return array Array of field configurations for ExtJS
     */
    public function getFieldsForProduct(int $productId, ?int $parentId = null): array
    {
        return $this->adminOptionFields->getFieldsForProduct($productId, $parentId);
    }

    /**
     * Get available option keys for product
     *
     * Replaces: msProductOption::getOptionKeys()
     *
     * @param int $productId Product ID
     * @param int|null $parentId Parent category ID (optional)
     * @return array Array of option keys
     */
    public function getOptionKeys(int $productId, ?int $parentId = null): array
    {
        return $this->adminOptionFields->getOptionKeys($productId, $parentId);
    }

    /**
     * Non-empty override wins; null or whitespace-only string inherits global.
     */
    public function mergeCaptionDescription(?string $override, string $global): string
    {
        return $this->captionOverlayResolver->mergeCaptionDescription($override, $global);
    }
}
