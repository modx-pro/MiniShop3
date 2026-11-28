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
  authForgotPassword: '#forgot-password-link',
}

/**
 * Get merged selectors (defaults + ms3Config.selectors overrides)
 * @returns {Object} Selectors object
 */
function getSelectors () {
  const custom = (typeof window !== 'undefined' && window.ms3Config && window.ms3Config.selectors) || {}
  return { ...defaultSelectors, ...custom }
}

/**
 * Fallback roots for cart SSR when the snippet call has no &selector.
 * Shared by CartUI and QuantityUI so both modules resolve the same target (#804).
 */
const cartRenderFallbackSelectors = [
  '#ms3oc-cart-live',
  '#msb-test-cart',
  '#msCart',
  '[data-ms-cart]',
  '.msCart',
]

/** Tokens already reported, so a multi-root page does not spam on every update. */
const warnedCartRenderTokens = new Set()

/**
 * Resolve the DOM root for one rendered cart token (#804).
 *
 * A configured selector wins; if it matches nothing, rendering is skipped —
 * a typo must not silently send this token's HTML to an unrelated cart.
 * Without it, the fallback roots are collected and used only when exactly one
 * root exists and no other token in the same batch has claimed it: writing
 * one cart's HTML into another is worse than skipping. Skipping is never
 * silent — the reason is logged once per token/matched-root set.
 *
 * @param {string} configuredSelector - Selector from the snippet call, if any
 * @param {string} token - Render token the HTML belongs to
 * @param {Set<Element>} [claimed] - Roots already used by this render batch
 * @returns {Element|null} Render target or null when it is ambiguous/missing
 */
function resolveCartRenderRoot (configuredSelector, token, claimed) {
  const selector = configuredSelector ? String(configuredSelector) : ''

  const warn = (target) => {
    const identities = (claimed && claimed.size ? Array.from(claimed) : []).map(
      node => node.id || node.className || node.tagName
    ).sort().join('|')
    const key = `${token}\u0000${selector}\u0000${target}\u0000${identities}`
    if (warnedCartRenderTokens.has(key)) {
      return
    }
    warnedCartRenderTokens.add(key)
    console.warn(
      `[MiniShop3] Cart HTML for "${token}" was not rendered: ${target}. ` +
      'Add &selector to the msCart or msOrderTotal snippet call to choose the render target.',
    )
  }

  if (selector !== '') {
    const configured = document.querySelector(selector)
    if (!configured) {
      warn(`configured selector "${selector}" matched nothing`)
      return null
    }
    if (claimed && claimed.has(configured)) {
      warn(`configured selector "${selector}" is already used by another token in this batch`)
      return null
    }
    if (claimed) {
      claimed.add(configured)
    }
    return configured
  }

  const found = []
  cartRenderFallbackSelectors.forEach((fallbackSelector) => {
    document.querySelectorAll(fallbackSelector).forEach((node) => {
      if (!found.includes(node)) {
        found.push(node)
      }
    })
  })

  // Avoid writing one cart HTML into another when several roots exist.
  if (found.length === 1) {
    const root = found[0]
    if (claimed && claimed.has(root)) {
      warn('the only default cart root is already used by another token in this batch')
      return null
    }
    if (claimed) {
      claimed.add(root)
    }
    return root
  }

  warn(`${found.length} default cart roots matched`)
  return null
}

// Expose for fallback when Selectors.js loads but getSelectors fails (e.g. in unit tests)
if (typeof window !== 'undefined') {
  window.Ms3DefaultSelectors = defaultSelectors
}
