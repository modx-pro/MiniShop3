/**
 * Default selectors for MiniShop3 UI
 * Overridable via ms3Config.selectors
 * Priority: data-* attributes, fallback: CSS classes (backward compatibility)
 *
 * @see https://github.com/modx-pro/MiniShop3/issues/18
 */
const defaultSelectors = {
  form: '[data-ms3-form], .ms3_form',
  formOrder: '[data-ms3-form="order"], .ms3_order_form',
  formCustomer: '[data-ms3-form="customer"], .ms3_customer_form',
  cartOptions: '[data-ms3-cart-options], .ms3_cart_options',
  qtyInput: '[data-ms3-qty="input"], .qty-input',
  qtyInc: '[data-ms3-qty="inc"], .inc-qty',
  qtyDec: '[data-ms3-qty="dec"], .dec-qty',
  productCard: '[data-ms3-product-card], .ms3-product-card',
  fieldError: '[data-ms3-error], .ms3_field_error',
  orderCost: '#ms3_order_cost',
  orderCartCost: '#ms3_order_cart_cost',
  orderDeliveryCost: '#ms3_order_delivery_cost',
  link: '.ms3_link',
  orderCancel: '.ms3-order-cancel',
  addressSetDefault: '.set-default-address',
  addressDelete: '.delete-address',
  resendVerificationEmail:
    '#resend-verification-email, [data-ms3-resend-verification]',
  authLoginForm: '#ms3-login-form',
  authRegisterForm: '#ms3-register-form',
  authForgotPassword: '#forgot-password-link'
}

/**
 * Get merged selectors (defaults + ms3Config.selectors overrides)
 * @returns {Object} Selectors object
 */
function getSelectors () {
  const custom = (typeof window !== 'undefined' && window.ms3Config && window.ms3Config.selectors) || {}
  return { ...defaultSelectors, ...custom }
}

// Expose for fallback when Selectors.js loads but getSelectors fails (e.g. in unit tests)
if (typeof window !== 'undefined') {
  window.Ms3DefaultSelectors = defaultSelectors
}
