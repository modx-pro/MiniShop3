/**
 * UI handlers for cart
 *
 * Class manages interactive cart elements:
 * - Quantity increment/decrement buttons
 * - Quantity input fields
 * - Product option selects
 * - Product removal buttons
 *
 * Separation of concerns:
 * - CartUI: UI logic (events, DOM)
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
    this.initQuantityButtons()
    this.initQuantityInputs()
    this.initOptionSelects()
  }

  /**
   * Product quantity +/- buttons
   */
  initQuantityButtons () {
    document.querySelectorAll('.qty-btn').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        const form = e.target.closest('.ms3_form')
        if (!form) return

        const input = form.querySelector('.qty-input')
        const productKeyInput = form.querySelector('[name="product_key"]')

        if (!input || !productKeyInput) return

        let qty = parseInt(input.value) || 0

        if (e.target.classList.contains('inc-qty')) {
          qty++
        }

        if (e.target.classList.contains('dec-qty') && qty > 0) {
          qty--
        }

        input.value = qty

        await this.handleChange(productKeyInput.value, qty)
      })
    })
  }

  /**
   * Quantity input fields
   */
  initQuantityInputs () {
    document.querySelectorAll('.qty-input').forEach(input => {
      input.addEventListener('change', async (e) => {
        const form = e.target.closest('.ms3_form')
        if (!form) return

        const productKeyInput = form.querySelector('[name="product_key"]')
        if (!productKeyInput) return

        const qty = parseInt(e.target.value) || 0

        if (qty === 0) return

        await this.handleChange(productKeyInput.value, qty)
      })
    })
  }

  /**
   * Product option selects (color, size, etc.)
   */
  initOptionSelects () {
    document.querySelectorAll('.ms3_cart_options').forEach(select => {
      select.addEventListener('change', async (e) => {
        const form = e.target.closest('.ms3_form')
        if (!form) return

        console.log('Option changed:', e.target.name, e.target.value)
      })
    })
  }

  /**
   * Handle product quantity change
   *
   * @param {string} productKey - Product key
   * @param {number} count - New quantity
   */
  async handleChange (productKey, count) {
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
      console.error('CartUI.handleChange error:', error)
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
      console.error('CartUI.handleRemove error:', error)
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
      console.error('CartUI.handleClean error:', error)
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

    const tokens = cartRenderConfig.map(item => item.token).filter(Boolean)
    return tokens
  }

  /**
   * Render cart HTML blocks
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

    setTimeout(() => {
      this.init()
    }, 100)
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
