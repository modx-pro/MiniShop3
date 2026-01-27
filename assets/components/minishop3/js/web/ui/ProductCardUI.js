/**
 * UI handlers for product cards (cart state display)
 *
 * Manages product card buttons:
 * - Shows "Add to cart" button when product is NOT in cart
 * - Shows quantity controls (+/-) when product IS in cart
 *
 * Listens to cart updates and refreshes card states accordingly.
 *
 * Usage in template:
 * <div class="ms3-product-card" data-product-id="123">
 *   <form class="ms3-add-to-cart ms3_form" data-cart-state="add">...</form>
 *   <form class="ms3-cart-controls ms3_form" data-cart-state="change">...</form>
 * </div>
 */
class ProductCardUI {
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

    // Cart state: { productId: { key: productKey, count: quantity } }
    this.cartState = {}
  }

  /**
   * Initialize product card UI
   */
  async init () {
    // Load initial cart state
    await this.loadCartState()

    // Update all product cards with current state
    this.updateAllCards()

    // Init quantity controls in product cards
    this.initQuantityButtons()
    this.initQuantityInputs()

    // Listen for cart updates
    document.addEventListener('ms3:cart:updated', (e) => {
      this.handleCartUpdated(e.detail)
    })
  }

  /**
   * Load cart state from server
   */
  async loadCartState () {
    try {
      const response = await this.cart.get()

      if (response.success && response.data && response.data.cart) {
        this.parseCartData(response.data.cart)
      }
    } catch (error) {
      console.error('[ProductCardUI] loadCartState error:', error)
    }
  }

  /**
   * Parse cart data into lookup map
   *
   * Note: One product_id can have multiple entries with different options.
   * We store total count for the product and all product_keys.
   *
   * Cart data can be either:
   * - Object: { "product_key": { product_id, count, ... }, ... }
   * - Array: [{ product_id, count, ... }, ...]
   *
   * @param {Object|Array} cartData - Cart data from server
   */
  parseCartData (cartData) {
    this.cartState = {}

    if (!cartData || typeof cartData !== 'object') {
      return
    }

    // Convert to array if it's an object (keyed by product_key)
    const items = Array.isArray(cartData) ? cartData : Object.values(cartData)

    for (const item of items) {
      const productId = item.product_id

      if (!productId) {
        continue
      }

      if (!this.cartState[productId]) {
        this.cartState[productId] = {
          entries: [],
          totalCount: 0
        }
      }

      this.cartState[productId].entries.push({
        key: item.product_key,
        count: item.count,
        options: item.options || {}
      })

      this.cartState[productId].totalCount += parseInt(item.count)
    }
  }

  /**
   * Handle cart update event
   *
   * @param {Object} data - Event detail with cart data
   */
  handleCartUpdated (data) {
    if (data && data.cart) {
      this.parseCartData(data.cart)
    }

    this.updateAllCards()

    // Reinit quantity buttons after DOM update
    setTimeout(() => {
      this.initQuantityButtons()
      this.initQuantityInputs()
    }, 50)
  }

  /**
   * Update all product cards on page
   */
  updateAllCards () {
    const cards = document.querySelectorAll('.ms3-product-card')

    cards.forEach(card => {
      this.updateCard(card)
    })
  }

  /**
   * Update single product card state
   *
   * @param {HTMLElement} card - Product card element
   */
  updateCard (card) {
    const productId = card.dataset.productId

    if (!productId) {
      return
    }

    const addForm = card.querySelector('[data-cart-state="add"]')
    const changeForm = card.querySelector('[data-cart-state="change"]')

    if (!addForm || !changeForm) {
      return
    }

    const inCart = this.isProductInCart(productId)

    if (inCart) {
      // Product is in cart - show quantity controls
      addForm.style.display = 'none'
      changeForm.style.display = 'flex'

      // Update quantity input
      const countInput = changeForm.querySelector('.qty-input')
      if (countInput) {
        countInput.value = this.cartState[productId].totalCount
      }

      // Set product_key (use first entry's key)
      const keyInput = changeForm.querySelector('[name="product_key"]')
      if (keyInput && this.cartState[productId].entries.length > 0) {
        keyInput.value = this.cartState[productId].entries[0].key
      }
    } else {
      // Product not in cart - show "Add to cart" button
      addForm.style.display = ''
      changeForm.style.display = 'none'
    }
  }

  /**
   * Check if product is in cart
   *
   * @param {string|number} productId - Product ID
   * @returns {boolean}
   */
  isProductInCart (productId) {
    return !!(this.cartState[productId] && this.cartState[productId].totalCount > 0)
  }

  /**
   * Get cart info for product
   *
   * @param {string|number} productId - Product ID
   * @returns {Object|null} Cart info or null
   */
  getCartInfo (productId) {
    return this.cartState[productId] || null
  }

  /**
   * Product quantity +/- buttons in product cards
   */
  initQuantityButtons () {
    const cards = document.querySelectorAll('.ms3-product-card')

    cards.forEach(card => {
      const changeForm = card.querySelector('[data-cart-state="change"]')
      if (!changeForm) return

      changeForm.querySelectorAll('.qty-btn').forEach(btn => {
        // Remove old listeners by cloning
        const newBtn = btn.cloneNode(true)
        btn.parentNode.replaceChild(newBtn, btn)

        newBtn.addEventListener('click', async (e) => {
          e.preventDefault()

          const input = changeForm.querySelector('.qty-input')
          const productKeyInput = changeForm.querySelector('[name="product_key"]')

          if (!input || !productKeyInput) return

          let qty = parseInt(input.value) || 0

          if (e.target.classList.contains('inc-qty')) {
            qty++
          }

          if (e.target.classList.contains('dec-qty')) {
            qty--
          }

          if (qty < 0) {
            qty = 0
          }

          input.value = qty

          await this.handleQuantityChange(productKeyInput.value, qty, card)
        })
      })
    })
  }

  /**
   * Quantity input fields in product cards
   */
  initQuantityInputs () {
    const cards = document.querySelectorAll('.ms3-product-card')

    cards.forEach(card => {
      const changeForm = card.querySelector('[data-cart-state="change"]')
      if (!changeForm) return

      const input = changeForm.querySelector('.qty-input')
      if (!input) return

      // Remove old listeners by cloning
      const newInput = input.cloneNode(true)
      input.parentNode.replaceChild(newInput, input)

      newInput.addEventListener('change', async (e) => {
        const productKeyInput = changeForm.querySelector('[name="product_key"]')
        if (!productKeyInput) return

        const qty = parseInt(e.target.value) || 0

        await this.handleQuantityChange(productKeyInput.value, qty, card)
      })
    })
  }

  /**
   * Handle quantity change from product card
   *
   * @param {string} productKey - Product key in cart
   * @param {number} count - New quantity
   * @param {HTMLElement} card - Product card element
   */
  async handleQuantityChange (productKey, count, card) {
    const hookData = { productKey, count }
    await this.hooks.runHooks('beforeChangeCart', hookData)

    if (hookData.cancel) {
      return
    }

    try {
      let response

      if (count <= 0) {
        // Remove from cart
        response = await this.cart.remove(productKey)
      } else {
        // Change quantity
        response = await this.cart.change(productKey, count)
      }

      await this.hooks.runHooks('afterChangeCart', { productKey, count, response })

      if (response.success) {
        // Update cart state
        if (response.data && response.data.cart) {
          this.parseCartData(response.data.cart)
        }

        // Update this specific card
        this.updateCard(card)

        // Dispatch event for other components (mini cart, etc.)
        document.dispatchEvent(new CustomEvent('ms3:cart:updated', {
          detail: response.data
        }))

        if (response.message) {
          this.message.success(response.message)
        }
      } else {
        if (response.message) {
          this.message.error(response.message)
        }
      }
    } catch (error) {
      console.error('[ProductCardUI] handleQuantityChange error:', error)
      this.message.error('Cart update error')
    }
  }
}
