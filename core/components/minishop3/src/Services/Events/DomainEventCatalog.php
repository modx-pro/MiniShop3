<?php

declare(strict_types=1);

namespace MiniShop3\Services\Events;

/**
 * Semantic domain event types and their msOn* plugin bindings.
 *
 * MODX plugins keep firing msOn* via EventGate / Utils::invokeEvent unchanged.
 * This catalog maps commerce mutations to stable event_type strings for outbound
 * webhooks (addon replaces ms3_webhook_dispatcher). Read-time hooks (msOnGet*)
 * never produce semantic emits.
 *
 * Emitted in core today:
 * - {@see ORDER_STATUS_CHANGED} after successful OrderStatusService::change() when status_id changed.
 *
 * Reserved (documented bindings only — not emitted until a future issue wires them):
 * - order.created — msOnCreateOrder, msOnMgrCreateOrder (OrderSubmitHandler, OrderFinalizeService)
 * - order.saved — msOnSaveOrder (msOrder::save)
 * - order.removed — msOnRemoveOrder (msOrder::remove)
 * - order.product.created — msOnCreateOrderProduct (OrderDraftManager, ManagerOrderProductsService)
 * - order.product.updated — msOnUpdateOrderProduct (ManagerOrderProductsService)
 * - order.product.removed — msOnRemoveOrderProduct (ManagerOrderProductsService)
 * - cart.item.added — msOnAddToCart (CartMutationHandler)
 * - cart.item.changed — msOnChangeInCart (CartMutationHandler)
 * - cart.item.options_changed — msOnChangeOptionInCart (CartMutationHandler)
 * - cart.item.removed — msOnRemoveFromCart (CartMutationHandler)
 * - cart.emptied — msOnEmptyCart (Cart controller, OrderDraftManager msOnEmptyOrder is draft clear)
 * - customer.created — msOnCreateCustomer (CustomerFieldManager)
 * - customer.address.added — msOnAddCustomerAddress (CustomerAddressManager)
 * - shipment.created — msOnCreateShipment (ShipmentLifecycleService)
 * - shipment.status_changed — msOnChangeShipmentStatus (ShipmentLifecycleService)
 * - shipment.tracking_updated — msOnUpdateShipmentTracking (ShipmentLifecycleService)
 * - inventory.reserved — msOnInventoryReserve (ProductStockInventory)
 * - inventory.committed — msOnInventoryCommit (ProductStockInventory)
 * - inventory.released — msOnInventoryRelease (ProductStockInventory)
 * - import.completed — msOnAfterImport (ProductImportService)
 * - notification.sent — msOnAfterSendNotification (NotificationManager)
 *
 * msOn* with no reserved semantic binding yet (before/veto, read, or manager-only):
 * msOnBefore*, msOnGet*, msOnValidate*, msOnError*, msOnProductsLoad, msOnProductPrepare,
 * msOnGetPublicSeo, msOnRegisterNotificationChannels, msOnManagerCustomCssJs, msOnImportRow,
 * msOnBeforeImport, vendor CRUD events, msOnBeforeUpdateOrder / msOnUpdateOrder (declared, no emit site).
 * msOnSubmitOrder is a veto point inside OrderSubmitHandler::submit before the order is processed,
 * not an after-success hook, so it has no semantic event_type.
 */
final class DomainEventCatalog
{
    public const ORDER_STATUS_CHANGED = 'order.status_changed';

    public const ORDER_CREATED = 'order.created';
    public const ORDER_SAVED = 'order.saved';
    public const ORDER_REMOVED = 'order.removed';
    public const ORDER_PRODUCT_CREATED = 'order.product.created';
    public const ORDER_PRODUCT_UPDATED = 'order.product.updated';
    public const ORDER_PRODUCT_REMOVED = 'order.product.removed';
    public const CART_ITEM_ADDED = 'cart.item.added';
    public const CART_ITEM_CHANGED = 'cart.item.changed';
    public const CART_ITEM_OPTIONS_CHANGED = 'cart.item.options_changed';
    public const CART_ITEM_REMOVED = 'cart.item.removed';
    public const CART_EMPTIED = 'cart.emptied';
    public const CUSTOMER_CREATED = 'customer.created';
    public const CUSTOMER_ADDRESS_ADDED = 'customer.address.added';
    public const SHIPMENT_CREATED = 'shipment.created';
    public const SHIPMENT_STATUS_CHANGED = 'shipment.status_changed';
    public const SHIPMENT_TRACKING_UPDATED = 'shipment.tracking_updated';
    public const INVENTORY_RESERVED = 'inventory.reserved';
    public const INVENTORY_COMMITTED = 'inventory.committed';
    public const INVENTORY_RELEASED = 'inventory.released';
    public const IMPORT_COMPLETED = 'import.completed';
    public const NOTIFICATION_SENT = 'notification.sent';

    /**
     * Semantic event_type => msOn* after/success plugin events on the same mutation.
     *
     * @return array<string, list<string>>
     */
    public static function bindings(): array
    {
        return [
            self::ORDER_STATUS_CHANGED => ['msOnChangeOrderStatus'],
            self::ORDER_CREATED => ['msOnCreateOrder', 'msOnMgrCreateOrder'],
            self::ORDER_SAVED => ['msOnSaveOrder'],
            self::ORDER_REMOVED => ['msOnRemoveOrder'],
            self::ORDER_PRODUCT_CREATED => ['msOnCreateOrderProduct'],
            self::ORDER_PRODUCT_UPDATED => ['msOnUpdateOrderProduct'],
            self::ORDER_PRODUCT_REMOVED => ['msOnRemoveOrderProduct'],
            self::CART_ITEM_ADDED => ['msOnAddToCart'],
            self::CART_ITEM_CHANGED => ['msOnChangeInCart'],
            self::CART_ITEM_OPTIONS_CHANGED => ['msOnChangeOptionInCart'],
            self::CART_ITEM_REMOVED => ['msOnRemoveFromCart'],
            self::CART_EMPTIED => ['msOnEmptyCart'],
            self::CUSTOMER_CREATED => ['msOnCreateCustomer'],
            self::CUSTOMER_ADDRESS_ADDED => ['msOnAddCustomerAddress'],
            self::SHIPMENT_CREATED => ['msOnCreateShipment'],
            self::SHIPMENT_STATUS_CHANGED => ['msOnChangeShipmentStatus'],
            self::SHIPMENT_TRACKING_UPDATED => ['msOnUpdateShipmentTracking'],
            self::INVENTORY_RESERVED => ['msOnInventoryReserve'],
            self::INVENTORY_COMMITTED => ['msOnInventoryCommit'],
            self::INVENTORY_RELEASED => ['msOnInventoryRelease'],
            self::IMPORT_COMPLETED => ['msOnAfterImport'],
            self::NOTIFICATION_SENT => ['msOnAfterSendNotification'],
        ];
    }
}
