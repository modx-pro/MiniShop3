/**
 * UI handlers for cart
 *
 * Manages cart-specific UI operations:
 * - Product addition (handleAdd)
 * - Product removal (handleRemove)
 * - Cart clearing (handleClean)
 * - SSR rendering (renderCart)
 * - Option selects
 *
 * Note: Quantity +/- buttons and inputs are handled by QuantityUI
 * to avoid duplication with ProductCardUI.
 *
 * Separation of concerns:
 * - CartUI: Cart operations (add, remove, clean, render)
 * - QuantityUI: Quantity controls (+/- buttons, inputs)
 * - CartAPI: Server requests
 * - Hooks: Extensibility
 * - Message: Notifications
 */
class CartUI {
  /**
   * @param {CartAPI} cartAPI - Cart API instance
   * @param {Object} hooks - Hook system
   * @param {Object} message - Message system
   * @param {Object} config - Configuration (ms3Config)
   */
  constructor (cartAPI, hooks, message, config) {
    this.cart = cartAPI
    this.hooks = hooks
    this.message = message
    this.config = config
  }

  get selectors () {
    return this.config?.selectors || {}
  }

  /**
   * Initialize UI handlers
   */
  init () {
    this.initOptionSelects()
  }

  /**
   * Product option selects: uses sel.cartOptions from config.
   * Delegated so SSR re-renders keep working without re-bind.
   */
  initOptionSelects () {
    if (this._optionSelectsBound) {
      return
    }
    this._optionSelectsBound = true

    const { cartOptions: cartOptionsSelector, form: formSelector } = this.selectors

    document.addEventListener('change', (e) => {
      const target = e.target
      if (!(target instanceof Element) || !cartOptionsSelector || !target.matches(cartOptionsSelector)) {
        return
      }

      const form = formSelector ? target.closest(formSelector) : null
      if (!form) {
        return
      }

      if (target instanceof HTMLSelectElement && String(target.value || '').trim() === '') {
        return
      }

      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit()
        return
      }

      form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    })
  }

  /**
   * Handle product option change in cart
   *
   * @param {string} productKey - Product key
   * @param {Object} options - Option map
   */
  async handleChangeOption (productKey, options = {}) {
    const key = productKey != null ? String(productKey).trim() : ''
    const optionMap = options && typeof options === 'object' && !Array.isArray(options)
      ? options
      : {}

    if (key === '' || Object.keys(optionMap).length === 0) {
      return
    }

    const hookData = { productKey: key, options: optionMap }
    await this.hooks.runHooks('beforeChangeOptionCart', hookData)

    if (hookData.cancel) {
      return
    }

    try {
      const renderTokens = this.getRenderTokens()
      const response = await this.cart.changeOption(key, optionMap, renderTokens)

      await this.hooks.runHooks('afterChangeOptionCart', { productKey: key, options: optionMap, response })

      if (response.success) {
        if (response.data && response.data.render) {
          this.renderCart(response.data.render)
        }

        this.dispatchCartUpdated(response.data)

        if (response.message) {
          this.message.success(response.message)
        }
      } else if (response.message) {
        this.message.error(response.message)
      }

      return response
    } catch (error) {
      console.error('[CartUI] handleChangeOption error:', error)
      this.message.error('Cart option update error')
    }
  }

  /**
   * Handle product quantity change (for form submit)
   *
   * Note: This is called from ms3.js form handler for cart/change action.
   * For +/- buttons and inputs, QuantityUI is used instead.
   *
   * @param {string} productKey - Product key
   * @param {number} count - New quantity
   */
  async handleChange (productKey, count) {
    // Delegate to remove if count <= 0
    if (count <= 0) {
      return this.handleRemove(productKey)
    }

    const hookData = { productKey, count }
    await this.hooks.runHooks('beforeChangeCart', hookData)

    if (hookData.cancel) {
      return
    }

    try {
      const renderTokens = this.getRenderTokens()
      const response = await this.cart.change(productKey, count, renderTokens)

      await this.hooks.runHooks('afterChangeCart', { productKey, count, response })

      if (response.success) {
        if (response.data && response.data.render) {
          this.renderCart(response.data.render)
        }

        this.dispatchCartUpdated(response.data)

        if (response.message) {
          this.message.success(response.message)
        }
      } else {
        if (response.message) {
          this.message.error(response.message)
        }
      }
    } catch (error) {
      console.error('[CartUI] handleChange error:', error)
      this.message.error('Cart update error')
    }
  }

  /**
   * Handle product addition
   *
   * @param {number} id - Product ID
   * @param {number} count - Quantity
   * @param {Object} options - Product options
   */
  async handleAdd (id, count = 1, options = {}) {
    const hookData = { id, count, options }
    await this.hooks.runHooks('beforeAddCart', hookData)

    if (hookData.cancel) {
      return
    }

    try {
      const renderTokens = this.getRenderTokens()
      const response = await this.cart.add(id, count, options, renderTokens)

      await this.hooks.runHooks('afterAddCart', { id, count, options, response })

      if (response.success) {
        if (response.data && response.data.render) {
          this.renderCart(response.data.render)
        }

        this.dispatchCartUpdated(response.data)

        if (response.message) {
          this.message.success(response.message)
        }
      } else {
        if (response.message) {
          this.message.error(response.message)
        }
      }
    } catch (error) {
      console.error('[CartUI] handleAdd error:', error)
      this.message.error('Product addition error')
    }
  }

  /**
   * Handle product removal
   *
   * @param {string} productKey - Product key
   */
  async handleRemove (productKey) {
    const hookData = { productKey }
    await this.hooks.runHooks('beforeRemoveCart', hookData)

    if (hookData.cancel) {
      return
    }

    try {
      const renderTokens = this.getRenderTokens()
      const response = await this.cart.remove(productKey, renderTokens)

      await this.hooks.runHooks('afterRemoveCart', { productKey, response })

      if (response.success) {
        if (response.data && response.data.render) {
          this.renderCart(response.data.render)
        }

        this.dispatchCartUpdated(response.data)

        if (response.message) {
          this.message.success(response.message)
        }
      } else {
        if (response.message) {
          this.message.error(response.message)
        }
      }
    } catch (error) {
      console.error('[CartUI] handleRemove error:', error)
      this.message.error('Product removal error')
    }
  }

  /**
   * Handle cart clearing
   */
  async handleClean () {
    const hookData = {}
    await this.hooks.runHooks('beforeCleanCart', hookData)

    if (hookData.cancel) {
      return
    }

    try {
      const renderTokens = this.getRenderTokens()
      const response = await this.cart.clean(renderTokens)

      await this.hooks.runHooks('afterCleanCart', { response })

      if (response.success) {
        if (response.data && response.data.render) {
          this.renderCart(response.data.render)
        }

        this.dispatchCartUpdated(response.data)

        if (response.message) {
          this.message.success(response.message)
        }
      } else {
        if (response.message) {
          this.message.error(response.message)
        }
      }
    } catch (error) {
      console.error('[CartUI] handleClean error:', error)
      this.message.error('Cart clearing error')
    }
  }

  /**
   * Get render tokens from config
   *
   * @returns {Array|null} Token array or null
   */
  getRenderTokens () {
    if (!this.config || !this.config.render || !this.config.render.cart) {
      return null
    }

    const cartRenderConfig = this.config.render.cart

    if (!Array.isArray(cartRenderConfig) || cartRenderConfig.length === 0) {
      return null
    }

    return cartRenderConfig.map(item => item.token).filter(Boolean)
  }

  /**
   * Render cart HTML blocks (SSR)
   *
   * Backend returns HTML by tokens:
   * {
   *   "token1": "<div>Cart HTML 1</div>",
   *   "token2": "<div>Cart HTML 2</div>"
   * }
   *
   * Map tokens to selectors from ms3Config.render.cart:
   * [{token: "token1", selector: "#headerMiniCart"}, ...]
   *
   * Note: After rendering, ms3:cart:updated event is dispatched.
   * QuantityUI listens to this event and reinitializes controls.
   *
   * @param {Object} renderData - Object {token: html}
   */
  renderCart (renderData) {
    if (!renderData || typeof renderData !== 'object') {
      return
    }

    if (!this.config || !this.config.render || !this.config.render.cart) {
      return
    }

    const cartRenderConfig = this.config.render.cart

    for (const token in renderData) {
      const html = renderData[token]
      const config = cartRenderConfig.find(item => item.token === token)
      const configuredSelector = config && config.selector ? String(config.selector) : ''
      const fallbackSelectors = [
        '#ms3oc-cart-live',
        '#msb-test-cart',
        '#msCart',
        '[data-ms-cart]',
        '.msCart'
      ]

      let element = null
      if (configuredSelector !== '') {
        element = document.querySelector(configuredSelector)
      }
      if (!element) {
        const found = []
        fallbackSelectors.forEach((selector) => {
          document.querySelectorAll(selector).forEach((node) => {
            if (!found.includes(node)) {
              found.push(node)
            }
          })
        })
        // Avoid writing one cart HTML into another when several roots exist.
        if (found.length === 1) {
          element = found[0]
        }
      }

      if (element) {
        element.innerHTML = html
      }
    }

    // Note: QuantityUI.reinit() will be called via ms3:cart:updated event listener
  }

  /**
   * Dispatch cart update event
   *
   * Allows external scripts to subscribe to cart changes:
   * document.addEventListener('ms3:cart:updated', (e) => {
   *   console.log('Cart updated', e.detail)
   * })
   *
   * @param {Object} data - Cart data
   */
  dispatchCartUpdated (data) {
    document.dispatchEvent(new CustomEvent('ms3:cart:updated', {
      detail: data
    }))
  }
}
