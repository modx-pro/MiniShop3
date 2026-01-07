/**
 * MiniShop3 - Main object
 *
 * Initializes all system components:
 * - TokenManager: Token management
 * - ApiClient: HTTP client
 * - API modules: CartAPI, OrderAPI, CustomerAPI
 * - UI modules: CartUI, OrderUI, CustomerUI
 * - Utilities: Hooks, Message
 *
 * Configuration passed via window.ms3Config:
 * {
 *   actionUrl: '/assets/components/minishop3/connector.php',
 *   tokenName: 'ms3_token',
 *   render: { ... }
 * }
 */
const ms3 = {
  config: {},

  tokenManager: null,
  apiClient: null,

  cartAPI: null,
  orderAPI: null,
  customerAPI: null,

  cartUI: null,
  orderUI: null,
  customerUI: null,
  productCardUI: null,

  hooks: null,
  message: null,

  /**
   * Async initialization
   */
  async init () {
    this.config = window.ms3Config || {}

    this.hooks = window.ms3Hooks || this.createFallbackHooks()
    this.message = window.ms3Message || this.createFallbackMessage()

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
    this.productCardUI = new ProductCardUI(this.cartAPI, this.hooks, this.message, this.config)

    this.cartUI.init()
    this.orderUI.init()
    this.customerUI.init()
    await this.productCardUI.init()

    this.initFormHandler()

    this.initLinkHandler()

    document.dispatchEvent(new Event('ms3:ready'))

    console.log('MiniShop3 initialized')
  },

  /**
   * .ms3_form submit handler
   *
   * Automatically calls appropriate API method based on ms3_action:
   * - cart/add → cartUI.handleAdd()
   * - order/submit → orderUI.handleSubmit()
   * - etc.
   */
  initFormHandler () {
    document.addEventListener('submit', async (event) => {
      if (!event.target.classList.contains('ms3_form')) {
        return
      }

      event.preventDefault()

      const form = event.target
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
   * .ms3_link click handler
   *
   * Handles clicks on buttons/links with .ms3_link class
   * inside .ms3_form forms. Triggers form submit.
   */
  initLinkHandler () {
    document.addEventListener('click', async (event) => {
      const link = event.target.closest('.ms3_link')
      if (!link) {
        return
      }

      const form = link.closest('.ms3_form')
      if (!form) {
        console.warn('.ms3_link must be inside .ms3_form')
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
            const options = this.parseOptions(formData.get('options'))
            return this.cartUI.handleAdd(id, count, options)
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
   * Parse options from string/JSON
   *
   * @param {string|Object} options
   * @returns {Object}
   */
  parseOptions (options) {
    if (!options) return {}

    if (typeof options === 'string') {
      try {
        return JSON.parse(options)
      } catch (e) {
        return {}
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
