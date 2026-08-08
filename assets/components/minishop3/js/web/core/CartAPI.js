/**
 * API for cart management
 *
 * Simple wrapper over cart REST endpoints.
 * All methods return Promise with server response.
 *
 * Server response format:
 * {
 *   success: true/false,
 *   message: "Message",
 *   data: {
 *     cart: [],           // Product array
 *     status: {},         // Cart totals (total_cost, total_count, etc.)
 *     render: {}          // HTML blocks for rendering (if requested)
 *   }
 * }
 *
 * @example
 * const cart = new CartAPI(apiClient)
 * const response = await cart.add(123, 2, { color: 'red' })
 * if (response.success) {
 *   console.log('Product added', response.data.cart)
 * }
 */
class CartAPI {
  /**
   * @param {ApiClient} apiClient - HTTP client
   */
  constructor (apiClient) {
    this.api = apiClient
  }

  /**
   * Get cart
   *
   * GET /api/v1/cart/get
   *
   * @param {Object} params - Additional parameters
   * @param {Object} params.render - Render configuration (selectors for HTML update)
   * @returns {Promise<Object>} - { success, message, data: { cart, status, render } }
   *
   * @example
   * const response = await cart.get()
   * console.log(response.data.cart)
   * console.log(response.data.status.total_cost)
   */
  async get (params = {}) {
    const endpoint = '/api/v1/cart/get'

    if (params.render) {
      // TODO: add render parameter support in backend
    }

    return this.api.get(endpoint)
  }

  /**
   * Add product to cart
   *
   * POST /api/v1/cart/add
   *
   * @param {number} id - Product ID
   * @param {number} count - Quantity (default 1)
   * @param {Object} options - Product options (color, size, etc.)
   * @param {Object} render - Render configuration
   * @returns {Promise<Object>}
   *
   * @example
   * await cart.add(123, 2, { color: 'red', size: 'L' })
   */
  async add (id, count = 1, options = {}, render = null) {
    const data = {
      id,
      count,
      options
    }

    if (render) {
      data.render = JSON.stringify(render)
    }

    return this.api.post('/api/v1/cart/add', data)
  }

  /**
   * Change product quantity
   *
   * POST /api/v1/cart/change
   *
   * @param {string} productKey - Unique product key in cart
   * @param {number} count - New quantity (0 = remove)
   * @param {Object} render - Render configuration
   * @returns {Promise<Object>}
   *
   * @example
   * await cart.change('ms5d41d8cd98f00b204e9800998ecf8427e', 3)
   */
  async change (productKey, count, render = null) {
    const data = {
      product_key: productKey,
      count
    }

    if (render) {
      data.render = JSON.stringify(render)
    }

    return this.api.post('/api/v1/cart/change', data)
  }

  /**
   * Change product options in cart
   *
   * POST /api/v1/cart/change-option
   *
   * @param {string} productKey - Unique product key in cart
   * @param {Object} options - Option map, e.g. { color: 'Синий' }
   * @param {Object} render - Render configuration
   * @returns {Promise<Object>}
   *
   * @example
   * await cart.changeOption('ms5d41d8cd98f00b204e9800998ecf8427e', { color: 'Розовый' })
   */
  async changeOption (productKey, options = {}, render = null) {
    const data = {
      product_key: productKey,
      options
    }

    if (render) {
      data.render = JSON.stringify(render)
    }

    return this.api.post('/api/v1/cart/change-option', data)
  }

  /**
   * Remove product from cart
   *
   * POST /api/v1/cart/remove
   *
   * @param {string} productKey - Unique product key
   * @param {Object} render - Render configuration
   * @returns {Promise<Object>}
   *
   * @example
   * await cart.remove('ms5d41d8cd98f00b204e9800998ecf8427e')
   */
  async remove (productKey, render = null) {
    const data = {
      product_key: productKey
    }

    if (render) {
      data.render = JSON.stringify(render)
    }

    return this.api.post('/api/v1/cart/remove', data)
  }

  /**
   * Clear cart (remove all products)
   *
   * POST /api/v1/cart/clean
   *
   * @param {Object} render - Render configuration
   * @returns {Promise<Object>}
   *
   * @example
   * await cart.clean()
   */
  async clean (render = null) {
    const data = {}

    if (render) {
      data.render = JSON.stringify(render)
    }

    return this.api.post('/api/v1/cart/clean', data)
  }
}
