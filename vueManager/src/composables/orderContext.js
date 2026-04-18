/**
 * Vue inject key for OrderView child tab components (OrderInfoTab, etc.).
 * Symbol avoids collisions with string keys from other providers.
 */
export const ORDER_CONTEXT_KEY = Symbol('ms3.orderContext')
