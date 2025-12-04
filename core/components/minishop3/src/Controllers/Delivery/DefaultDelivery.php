<?php

namespace MiniShop3\Controllers\Delivery;

/**
 * Default delivery provider implementation
 *
 * Used for simple delivery methods without external API integration:
 * - Pickup
 * - Courier delivery with fixed cost
 * - Postal delivery with weight calculation
 *
 * Uses standard calculation logic from base Delivery class:
 * - Cost by weight (weight_price * order weight)
 * - Free delivery when threshold exceeded (free_delivery_amount)
 * - Fixed cost or percentage of order amount
 *
 * @package MiniShop3\Controllers\Delivery
 */
class DefaultDelivery extends Delivery
{
}
