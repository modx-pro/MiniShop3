/**
 * Universal quantity control handler
 *
 * Handles +/- buttons and quantity inputs across all contexts:
 * - Product cards in catalog
 * - Cart page
 * - Mini cart
 * - Any custom implementation
 *
 * Single source of truth for quantity changes. Eliminates duplication
 * between CartUI and ProductCardUI.
 *
 * Usage in template:
 * <form class="ms3_form">
 *   <input type="hidden" name="product_key" value="ms...">
 *   <button type="button" class="qty-btn dec-qty">-</button>
 *   <input type="number" class="qty-input" name="count" value="1">
 *   <button type="button" class="qty-btn inc-qty">+</button>
 * </form>
 */
class QuantityUI {
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
   * Initialize quantity controls
   */
  init () {
    this.initButtons()
    this.initInputs()
  }

  /**
   * Reinitialize after DOM update
   * Clones elements to remove old handlers, then attaches new ones
   */
  reinit () {
    this.init()
  }

  /**
   * Initialize quantity buttons: uses sel.qtyInc, sel.qtyDec from config
   */
  initButtons () {
    const sel = this.config?.selectors || {}
    const qtyIncSel = sel.qtyInc || '[data-ms3-qty="inc"], .inc-qty'
    const qtyDecSel = sel.qtyDec || '[data-ms3-qty="dec"], .dec-qty'
    const buttons = document.querySelectorAll([qtyIncSel, qtyDecSel].join(', '))

    buttons.forEach(btn => {
      const newBtn = btn.cloneNode(true)
      btn.parentNode.replaceChild(newBtn, btn)
      newBtn.addEventListener('click', (e) => this.handleButtonClick(e))
    })
  }

  /**
   * Initialize quantity inputs: uses sel.qtyInput from config
   */
  initInputs () {
    const sel = this.config?.selectors || {}
    const qtyInputSel = sel.qtyInput || '[data-ms3-qty="input"], .qty-input'
    const inputs = document.querySelectorAll(qtyInputSel)

    inputs.forEach(input => {
      const newInput = input.cloneNode(true)
      input.parentNode.replaceChild(newInput, input)
      newInput.addEventListener('change', (e) => this.handleInputChange(e))
    })
  }

  /**
   * Handle +/- button click
   *
   * @param {Event} e - Click event
   */
  async handleButtonClick (e) {
    e.preventDefault()

    const sel = this.config?.selectors || {}
    const formSel = sel.form || '[data-ms3-form], .ms3_form'
    const qtyInputSel = sel.qtyInput || '[data-ms3-qty="input"], .qty-input'

    const form = e.target.closest(formSel)
    if (!form) return

    const input = form.querySelector(qtyInputSel)
    if (!input) return

    let qty = parseInt(input.value) || 0

    const isInc = e.target.getAttribute('data-ms3-qty') === 'inc' || e.target.classList.contains('inc-qty')
    const isDec = e.target.getAttribute('data-ms3-qty') === 'dec' || e.target.classList.contains('dec-qty')

    if (isInc) qty++
    if (isDec) qty--

    qty = Math.max(0, qty)
    input.value = qty

    await this.updateQuantity(form, qty)
  }

  /**
   * Handle quantity input change
   *
   * @param {Event} e - Change event
   */
  async handleInputChange (e) {
    const sel = this.config?.selectors || {}
    const formSel = sel.form || '[data-ms3-form], .ms3_form'

    const form = e.target.closest(formSel)
    if (!form) return

    const qty = Math.max(0, parseInt(e.target.value) || 0)
    e.target.value = qty

    await this.updateQuantity(form, qty)
  }

  /**
   * Update quantity - single entry point for all quantity changes
   *
   * Automatically chooses between change() and remove() based on count.
   *
   * @param {HTMLFormElement} form - Form element
   * @param {number} count - New quantity
   */
  async updateQuantity (form, count) {
    const productKeyInput = form.querySelector('[name="product_key"]')
    if (!productKeyInput || !productKeyInput.value) return

    const productKey = productKeyInput.value

    const hookData = { productKey, count, form }
    await this.hooks.runHooks('beforeQuantityChange', hookData)

    if (hookData.cancel) {
      return
    }

    // Allow hooks to modify count
    count = hookData.count

    try {
      let response
      const renderTokens = this.getRenderTokens()

      if (count <= 0) {
        // Remove from cart
        response = await this.cart.remove(productKey, renderTokens)
      } else {
        // Change quantity
        response = await this.cart.change(productKey, count, renderTokens)
      }

      await this.hooks.runHooks('afterQuantityChange', {
        productKey,
        count,
        response,
        form
      })

      if (response.success) {
        // Handle SSR render if provided
        if (response.data && response.data.render) {
          this.renderCart(response.data.render)
        }

        // Dispatch update event for other components
        this.dispatchUpdate(response.data)

        if (response.message) {
          this.message.success(response.message)
        }
      } else {
        if (response.message) {
          this.message.error(response.message)
        }
      }
    } catch (error) {
      console.error('[QuantityUI] updateQuantity error:', error)
      this.message.error('Cart update error')
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

    // Reinit after DOM update
    setTimeout(() => {
      this.reinit()
    }, 50)
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
  dispatchUpdate (data) {
    document.dispatchEvent(new CustomEvent('ms3:cart:updated', {
      detail: data
    }))
  }
}
