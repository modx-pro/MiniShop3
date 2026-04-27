/**
 * MiniShop3 - Main object
 *
 * Initializes all system components:
 * - TokenManager: Token management
 * - ApiClient: HTTP client
 * - API modules: CartAPI, OrderAPI, CustomerAPI
 * - UI modules: CartUI, OrderUI, CustomerUI, QuantityUI, ProductCardUI
 * - Utilities: Hooks, Message
 *
 * Configuration passed via window.ms3Config:
 * {
 *   actionUrl: '/assets/components/minishop3/connector.php',
 *   tokenName: 'ms3_token',
 *   render: { ... }
 * }
 *
 * Data attributes for logic (Issue #17): use data-ms3-* for JS selection; keep classes for styling.
 * - data-ms3-form / data-ms3-form="order"|"customer" — form
 * - data-ms3-error — field with validation error
 * - data-ms3-product-card — product card
 * - data-ms3-qty="input"|"inc"|"dec" — quantity control
 * - data-ms3-cart-options — cart options select
 */
/* global TokenManager, ApiClient, CartAPI, OrderAPI, CustomerAPI, CartUI, OrderUI, CustomerUI, AuthUI, QuantityUI, ProductCardUI, getSelectors */
const ms3 = {
  config: {},

  get selectors () {
    return this.config?.selectors || {}
  },

  tokenManager: null,
  apiClient: null,

  cartAPI: null,
  orderAPI: null,
  customerAPI: null,

  cartUI: null,
  orderUI: null,
  customerUI: null,
  authUI: null,
  quantityUI: null,
  productCardUI: null,

  hooks: null,
  message: null,
  confirm: null,

  /**
   * Async initialization
   */
  async init () {
    this.config = window.ms3Config || {}

    // Merge selectors from Selectors.js (overridable via ms3Config.selectors)
    const rawSelectors = typeof getSelectors === 'function'
      ? getSelectors()
      : (window.Ms3DefaultSelectors || {})
    const selectorDefaults = window.Ms3DefaultSelectors || {
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
    this.hooks = window.ms3Hooks || this.createFallbackHooks()
    this.message = window.ms3Message || this.createFallbackMessage()
    this.confirm = window.ms3Confirm || function (msg) { return Promise.resolve(window.confirm(msg)) }

    this.config = { ...this.config, selectors: { ...selectorDefaults, ...rawSelectors }, confirm: this.confirm }

    this.tokenManager = new TokenManager({
      tokenName: this.config.tokenName || 'ms3_token'
    })

    this.apiClient = new ApiClient({
      baseUrl: this.config.actionUrl || '/assets/components/minishop3/api.php',
      tokenManager: this.tokenManager
    })

    this.tokenManager.setApiClient(this.apiClient)

    await this.tokenManager.ensureToken()

    this.cartAPI = new CartAPI(this.apiClient)
    this.orderAPI = new OrderAPI(this.apiClient)
    this.customerAPI = new CustomerAPI(this.apiClient)

    this.cartUI = new CartUI(this.cartAPI, this.hooks, this.message, this.config)
    this.orderUI = new OrderUI(this.orderAPI, this.hooks, this.message, this.config)
    this.customerUI = new CustomerUI(this.customerAPI, this.hooks, this.message, this.config)
    this.authUI = new AuthUI(this.customerAPI, this.hooks, this.message, this.config)
    this.quantityUI = new QuantityUI(this.cartAPI, this.hooks, this.message, this.config)
    this.productCardUI = new ProductCardUI(this.cartAPI, this.hooks, this.message, this.config)

    this.cartUI.init()
    this.orderUI.init()
    this.customerUI.init()
    this.authUI.init()
    this.quantityUI.init()
    await this.productCardUI.init()

    // Reinit quantity controls after cart DOM updates
    document.addEventListener('ms3:cart:updated', () => {
      this.quantityUI.reinit()
    })

    this.initFormHandler()

    this.initLinkHandler()

    document.dispatchEvent(new Event('ms3:ready'))

    console.log('MiniShop3 initialized')
  },

  /**
   * Form submit handler (uses sel.form from config)
   *
   * Automatically calls appropriate API method based on ms3_action:
   * - cart/add → cartUI.handleAdd()
   * - order/submit → orderUI.handleSubmit()
   * - etc.
   */
  initFormHandler () {
    const formSelector = this.selectors.form

    document.addEventListener('submit', async (event) => {
      const form = event.target
      if (!form.matches(formSelector)) {
        return
      }

      event.preventDefault()

      const formData = new FormData(form)
      const action = formData.get('ms3_action')

      if (!action) {
        console.warn('ms3_action not specified in form')
        return
      }

      const [entity, method] = action.split('/')

      await this.handleFormSubmit(entity, method, formData)
    })
  },

  /**
   * Link click handler (uses sel.link, sel.form from config)
   *
   * Handles clicks on buttons/links with sel.link
   * inside forms matching sel.form. Triggers form submit.
   */
  initLinkHandler () {
    const linkSelector = this.selectors.link
    const formSelector = this.selectors.form

    document.addEventListener('click', async (event) => {
      const link = event.target.closest(linkSelector)
      if (!link) {
        return
      }

      const form = link.closest(formSelector)
      if (!form) {
        console.warn(`ms3_link must be inside a form matching: ${formSelector}`)
        return
      }

      event.preventDefault()

      const formData = new FormData(form)
      const action = formData.get('ms3_action')

      if (!action) {
        console.warn('ms3_action not specified in form')
        return
      }

      const [entity, method] = action.split('/')

      await this.handleFormSubmit(entity, method, formData)
    })
  },

  /**
   * Handle form submission
   *
   * @param {string} entity - Entity (cart, order, customer)
   * @param {string} method - Method (add, remove, submit, etc.)
   * @param {FormData} formData - Form data
   */
  async handleFormSubmit (entity, method, formData) {
    try {
      const hookData = { entity, method, formData }
      await this.hooks.runHooks('beforeFormSubmit', hookData)

      if (hookData.cancel) {
        return
      }

      const handlers = {
        cart: {
          add: () => {
            const id = parseInt(formData.get('id'))
            const count = parseInt(formData.get('count')) || 1
            const options = this.collectOptions(formData)
            return this.cartUI.handleAdd(id, count, options)
          },
          change: () => {
            const productKey = formData.get('product_key')
            const count = parseInt(formData.get('count')) || 0
            return this.cartUI.handleChange(productKey, count)
          },
          remove: () => {
            const productKey = formData.get('product_key')
            return this.cartUI.handleRemove(productKey)
          },
          clean: () => {
            return this.cartUI.handleClean()
          }
        },
        order: {
          submit: () => {
            return this.orderUI.handleSubmit()
          },
          clean: () => {
            return this.orderUI.handleClean()
          }
        },
        customer: {
          'update-profile': () => {
            return this.customerUI.handleProfileUpdate(formData)
          },
          'address-create': () => {
            return this.customerUI.handleAddressCreate(formData)
          },
          'address-update': () => {
            return this.customerUI.handleAddressUpdate(formData)
          }
        }
      }

      if (handlers[entity] && handlers[entity][method]) {
        await handlers[entity][method]()
      } else {
        console.warn(`Handler for ${entity}/${method} not found`)
      }

      await this.hooks.runHooks('afterFormSubmit', { entity, method, formData })
    } catch (error) {
      console.error('Form submit error:', error)
      this.message.error('Form submission error')
    }
  },

  /**
   * Collect options from FormData
   *
   * Supports two formats:
   * 1. JSON: name="options" value='{"color":"red","size":"L"}'
   * 2. HTML array: name="options[color]" value="red"
   *
   * @param {FormData} formData
   * @returns {Object}
   */
  collectOptions (formData) {
    // Try JSON format first: name="options" value='{"key":"value"}'
    const jsonOptions = formData.get('options')
    if (jsonOptions && typeof jsonOptions === 'string') {
      try {
        const parsed = JSON.parse(jsonOptions)
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed) && Object.keys(parsed).length > 0) {
          return parsed
        }
      } catch (e) {
        // Not valid JSON, continue to array format
      }
    }

    // Collect HTML array format: name="options[key]" value="value"
    const options = {}
    for (const [key, value] of formData.entries()) {
      const match = key.match(/^options\[([^\]]+)\]$/)
      if (match && value) {
        options[match[1]] = value
      }
    }

    return options
  },

  /**
   * Fallback for hooks (if hooks.js not included)
   */
  createFallbackHooks () {
    return {
      items: {},
      addHook (name, fn) {
        if (!this.items[name]) this.items[name] = []
        this.items[name].push(fn)
      },
      async runHooks (name, context) {
        if (!this.items[name]) return
        for (const fn of this.items[name]) {
          if (context.cancel) return false
          try {
            await fn(context)
          } catch (error) {
            console.error('Hook error:', name, error)
          }
        }
      }
    }
  },

  /**
   * Fallback for message (if message.js not included)
   */
  createFallbackMessage () {
    return {
      success (msg) {
        if (msg) alert(msg)
      },
      error (msg) {
        if (msg) alert(msg)
      }
    }
  },

  /**
   * Helper: check if string is valid JSON
   *
   * @param {string} str
   * @returns {boolean}
   */
  isJSON (str) {
    try {
      JSON.parse(str)
      return true
    } catch (e) {
      return false
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  ms3.init()
})

if (typeof module !== 'undefined' && module.exports) {
  module.exports = ms3
}
