/**
 * UI handlers for product cards (cart state display)
 *
 * Manages product card state switching:
 * - Shows "Add to cart" button when product is NOT in cart
 * - Shows quantity controls (+/-) when product IS in cart
 *
 * Listens to cart updates and refreshes card states accordingly.
 *
 * Note: Quantity +/- buttons and inputs are handled by QuantityUI
 * to avoid duplication with CartUI.
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

    // Cart state: { productId: { entries: [...], totalCount: N } }
    this.cartState = {}
  }

  get selectors () {
    return this.config?.selectors || {}
  }

  /**
   * Initialize product card UI
   */
  async init () {
    // Load initial cart state
    await this.loadCartState()

    // Update all product cards with current state
    this.updateAllCards()

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
          totalCount: 0,
        }
      }

      this.cartState[productId].entries.push({
        key: item.product_key,
        count: item.count,
        options: item.options || {},
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
  }

  /**
   * Update all product cards on page
   */
  updateAllCards () {
    const cards = document.querySelectorAll(this.selectors.productCard)

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
      const countInput = changeForm.querySelector(this.selectors.qtyInput)
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
}
