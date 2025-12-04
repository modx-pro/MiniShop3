/**
 * API for order management
 *
 * Manages order data: fetching, updating, submitting.
 *
 * @example
 * const order = new OrderAPI(apiClient)
 * await order.add('receiver', 'John Doe')
 * await order.submit()
 */
class OrderAPI {
  /**
   * @param {ApiClient} apiClient - HTTP client
   */
  constructor (apiClient) {
    this.api = apiClient
  }

  /**
   * Add/update order field
   *
   * POST /api/v1/order/add
   *
   * @param {string} key - Field key (receiver, email, phone, etc.)
   * @param {string} value - Field value
   * @returns {Promise<Object>}
   *
   * @example
   * await order.add('receiver', 'John Doe')
   * await order.add('email', 'john@example.com')
   */
  async add (key, value) {
    return this.api.post('/api/v1/order/add', { key, value })
  }

  /**
   * Remove order field
   *
   * POST /api/v1/order/remove
   *
   * @param {string} key - Field key
   * @returns {Promise<Object>}
   */
  async remove (key) {
    return this.api.post('/api/v1/order/remove', { key })
  }

  /**
   * Clear order
   *
   * POST /api/v1/order/clean
   *
   * @returns {Promise<Object>}
   */
  async clean () {
    return this.api.post('/api/v1/order/clean')
  }

  /**
   * Submit order (final submission)
   *
   * POST /api/v1/order/submit
   *
   * @returns {Promise<Object>}
   */
  async submit () {
    return this.api.post('/api/v1/order/submit')
  }

  /**
   * Get current order
   *
   * GET /api/v1/order/get
   *
   * @returns {Promise<Object>}
   */
  async get () {
    return this.api.get('/api/v1/order/get')
  }
}
