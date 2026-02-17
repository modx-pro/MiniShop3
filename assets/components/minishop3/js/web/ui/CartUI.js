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

  /**
   * Initialize UI handlers
   */
  init () {
    this.initOptionSelects()
  }

  /**
   * Product option selects: [data-ms3-cart-options] or .ms3_cart_options (fallback)
   */
  initOptionSelects () {
    const byData = document.querySelectorAll('[data-ms3-cart-options]')
    const byClass = document.querySelectorAll('.ms3_cart_options')
    const seen = new Set()
    const selects = []
    ;[...byData, ...byClass].forEach(select => {
      if (!seen.has(select)) {
        seen.add(select)
        selects.push(select)
      }
    })
    selects.forEach(select => {
      select.addEventListener('change', async (e) => {
        const form = e.target.closest('[data-ms3-form]') || e.target.closest('.ms3_form')
        if (!form) return

        console.log('Option changed:', e.target.name, e.target.value)
      })
    })
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
      dispatchMs3Loading('ms3:cart:changing', {
        entity: 'cart',
        action: 'change',
        form: null,
        data: { productKey, count },
        response: null
      })
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
      dispatchMs3Loading('ms3:cart:changed', {
        entity: 'cart',
        action: 'change',
        form: null,
        data: { productKey, count },
        response
      })
    } catch (error) {
      console.error('[CartUI] handleChange error:', error)
      this.message.error('Cart update error')
      dispatchMs3Loading('ms3:cart:changed', {
        entity: 'cart',
        action: 'change',
        form: null,
        data: { productKey, count },
        response: { success: false }
      })
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
      dispatchMs3Loading('ms3:cart:adding', {
        entity: 'cart',
        action: 'add',
        form: null,
        data: { id, count, options },
        response: null
      })
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
      dispatchMs3Loading('ms3:cart:added', {
        entity: 'cart',
        action: 'add',
        form: null,
        data: { id, count, options },
        response
      })
    } catch (error) {
      console.error('[CartUI] handleAdd error:', error)
      this.message.error('Product addition error')
      dispatchMs3Loading('ms3:cart:added', {
        entity: 'cart',
        action: 'add',
        form: null,
        data: { id, count, options },
        response: { success: false }
      })
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
      dispatchMs3Loading('ms3:cart:removing', {
        entity: 'cart',
        action: 'remove',
        form: null,
        data: { productKey },
        response: null
      })
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
      dispatchMs3Loading('ms3:cart:removed', {
        entity: 'cart',
        action: 'remove',
        form: null,
        data: { productKey },
        response
      })
    } catch (error) {
      console.error('[CartUI] handleRemove error:', error)
      this.message.error('Product removal error')
      dispatchMs3Loading('ms3:cart:removed', {
        entity: 'cart',
        action: 'remove',
        form: null,
        data: { productKey },
        response: { success: false }
      })
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
      dispatchMs3Loading('ms3:cart:cleaning', {
        entity: 'cart',
        action: 'clean',
        form: null,
        data: {},
        response: null
      })
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
      dispatchMs3Loading('ms3:cart:cleaned', {
        entity: 'cart',
        action: 'clean',
        form: null,
        data: {},
        response
      })
    } catch (error) {
      console.error('[CartUI] handleClean error:', error)
      this.message.error('Cart clearing error')
      dispatchMs3Loading('ms3:cart:cleaned', {
        entity: 'cart',
        action: 'clean',
        form: null,
        data: {},
        response: { success: false }
      })
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

      if (!config || !config.selector) {
        continue
      }

      const element = document.querySelector(config.selector)

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
