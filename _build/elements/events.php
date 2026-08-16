<?php

return [
    // Cart events
    'msOnBeforeGetCart',
    'msOnGetCart',
    'msOnBeforeGetCartCost',
    'msOnGetCartCost',
    'msOnBeforeAddToCart',
    'msOnAddToCart',
    'msOnBeforeChangeInCart',
    'msOnChangeInCart',
    'msOnBeforeChangeOptionsInCart',
    'msOnChangeOptionInCart',
    'msOnBeforeRemoveFromCart',
    'msOnRemoveFromCart',
    'msOnBeforeEmptyCart',
    'msOnEmptyCart',
    'msOnGetStatusCart',

    // Order events
    'msOnBeforeAddToOrder',
    'msOnAddToOrder',
    'msOnBeforeValidateOrderValue',
    'msOnValidateOrderValue',
    'msOnErrorValidateOrderValue',
    'msOnBeforeRemoveFromOrder',
    'msOnRemoveFromOrder',
    'msOnBeforeEmptyOrder',
    'msOnEmptyOrder',
    'msOnBeforeGetOrderCost',
    'msOnGetOrderCost',
    'msOnSubmitOrder',
    'msOnBeforeChangeOrderStatus',
    'msOnChangeOrderStatus',
    'msOnBeforeCreateOrder',
    'msOnCreateOrder',
    'msOnBeforeMgrCreateOrder',
    'msOnMgrCreateOrder',
    'msOnBeforeUpdateOrder',
    'msOnUpdateOrder',
    'msOnBeforeSaveOrder',
    'msOnSaveOrder',
    'msOnBeforeRemoveOrder',
    'msOnRemoveOrder',

    // Order product events
    'msOnBeforeCreateOrderProduct',
    'msOnCreateOrderProduct',
    'msOnBeforeUpdateOrderProduct',
    'msOnUpdateOrderProduct',
    'msOnBeforeRemoveOrderProduct',
    'msOnRemoveOrderProduct',

    // Order user events
    'msOnBeforeGetOrderUser',
    'msOnGetOrderUser',

    // Order customer events
    'msOnBeforeGetOrderCustomer',
    'msOnGetOrderCustomer',

    // Customer events
    'msOnBeforeAddToCustomer',
    'msOnAddToCustomer',
    'msOnBeforeValidateCustomerValue',
    'msOnValidateCustomerValue',
    'msOnErrorValidateCustomerValue',
    'msOnBeforeCreateCustomer',
    'msOnCreateCustomer',
    'msOnBeforeUpdateCustomer',
    'msOnUpdateCustomer',
    'msOnBeforeAddCustomerAddress',
    'msOnAddCustomerAddress',

    // Delivery & Payment cost events
    'msOnBeforeGetDeliveryCost',
    'msOnGetDeliveryCost',
    'msOnBeforeGetPaymentCost',
    'msOnGetPaymentCost',

    // Product events
    'msOnGetProductPrice',
    'msOnGetProductWeight',
    'msOnGetProductFields',

    // msProducts snippet events (for extending with external packages)
    'msOnProductsLoad',    // After loading products, for bulk data loading
    'msOnProductPrepare',  // Before rendering each product, for enriching data

    // Vendor events
    'msOnBeforeVendorCreate',
    'msOnVendorCreate',
    'msOnBeforeVendorUpdate',
    'msOnVendorUpdate',
    'msOnBeforeVendorDelete',
    'msOnVendorDelete',

    // Import events
    'msOnBeforeImport',
    'msOnAfterImport',
    'msOnImportRow',

    // Notification events
    'msOnBeforeSendNotification',
    'msOnAfterSendNotification',
    'msOnRegisterNotificationChannels',

    // Manager events
    'msOnManagerCustomCssJs',
];
