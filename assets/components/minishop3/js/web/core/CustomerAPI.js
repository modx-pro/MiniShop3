/**
 * API for customer data management
 *
 * Manages customer profile: contact details, addresses.
 *
 * @example
 * const customer = new CustomerAPI(apiClient)
 * await customer.add('email', 'user@example.com')
 * await customer.changeAddress('address_hash', 'addr_123')
 */
class CustomerAPI {
  /**
   * @param {ApiClient} apiClient - HTTP client
   */
  constructor (apiClient) {
    this.api = apiClient
  }

  /**
   * Add/update customer field
   *
   * POST /api/v1/customer/add
   *
   * @param {string} key - Field key (first_name, last_name, email, phone)
   * @param {string} value - Field value
   * @returns {Promise<Object>}
   *
   * @example
   * await customer.add('email', 'user@example.com')
   * await customer.add('phone', '+7 900 123-45-67')
   */
  async add (key, value) {
    return this.api.post('/api/v1/customer/add', { key, value })
  }

  /**
   * Change delivery address
   *
   * POST /api/v1/customer/changeAddress
   *
   * @param {string} key - Key (usually 'address_hash')
   * @param {string} value - Address hash
   * @returns {Promise<Object>}
   */
  async changeAddress (key, value) {
    return this.api.post('/api/v1/customer/changeAddress', { key, value })
  }

  /**
   * Update customer profile
   *
   * PUT /api/v1/customer/profile
   *
   * @param {Object} data - Profile data (first_name, last_name, email, phone)
   * @returns {Promise<Object>}
   *
   * @example
   * await customer.updateProfile({
   *   first_name: 'John',
   *   last_name: 'Doe',
   *   email: 'john@example.com',
   *   phone: '+79991234567'
   * })
   */
  async updateProfile (data) {
    return this.api.put('/api/v1/customer/profile', data)
  }

  /**
   * Create new address
   *
   * POST /api/v1/customer/addresses
   *
   * @param {Object} data - Address data
   * @returns {Promise<Object>}
   *
   * @example
   * await customer.createAddress({
   *   name: 'Home address',
   *   city: 'Moscow',
   *   street: 'Tverskaya',
   *   building: '1'
   * })
   */
  async createAddress (data) {
    return this.api.post('/api/v1/customer/addresses', data)
  }

  /**
   * Update address
   *
   * PUT /api/v1/customer/addresses/{id}
   *
   * @param {number} id - Address ID
   * @param {Object} data - Address data
   * @returns {Promise<Object>}
   */
  async updateAddress (id, data) {
    return this.api.put(`/api/v1/customer/addresses/${id}`, data)
  }

  /**
   * Delete address
   *
   * DELETE /api/v1/customer/addresses/{id}
   *
   * @param {number} id - Address ID
   * @returns {Promise<Object>}
   */
  async deleteAddress (id) {
    return this.api.delete(`/api/v1/customer/addresses/${id}`)
  }

  /**
   * Login customer
   *
   * POST /api/v1/customer/login
   *
   * @param {string} email - Email
   * @param {string} password - Password
   * @returns {Promise<Object>}
   */
  async login (email, password) {
    return this.api.post('/api/v1/customer/login', { email, password })
  }

  /**
   * Register customer
   *
   * POST /api/v1/customer/register
   *
   * @param {Object} data - Registration data
   * @returns {Promise<Object>}
   */
  async register (data) {
    return this.api.post('/api/v1/customer/register', data)
  }

  /**
   * Set default address
   *
   * PUT /api/v1/customer/addresses/{id}/set-default
   *
   * @param {number} id - Address ID
   * @returns {Promise<Object>}
   */
  async setDefaultAddress (id) {
    return this.api.put(`/api/v1/customer/addresses/${id}/set-default`)
  }

  /**
   * List current customer orders
   *
   * GET /api/v1/customer/orders?limit=&offset=&status=
   *
   * @param {Object} [params]
   * @param {number} [params.limit]
   * @param {number} [params.offset]
   * @param {number} [params.status]
   * @returns {Promise<Object>}
   */
  async getOrders (params = {}) {
    const query = new URLSearchParams()
    for (const key of ['limit', 'offset', 'status']) {
      if (params[key] != null) {
        query.set(key, String(params[key]))
      }
    }
    const qs = query.toString()
    return this.api.get(`/api/v1/customer/orders${qs ? `?${qs}` : ''}`)
  }

  /**
   * Get one order owned by the current customer
   *
   * GET /api/v1/customer/orders/{orderId}
   *
   * @param {number} orderId - Order ID
   * @returns {Promise<Object>}
   */
  async getOrder (orderId) {
    return this.api.get(`/api/v1/customer/orders/${orderId}`)
  }

  /**
   * Cancel order
   *
   * POST /api/v1/customer/orders/{orderId}/cancel
   *
   * @param {number} orderId - Order ID
   * @returns {Promise<Object>}
   */
  async cancelOrder (orderId) {
    return this.api.post(`/api/v1/customer/orders/${orderId}/cancel`)
  }

  /**
   * Resend email verification (cabinet; requires customer session)
   *
   * POST /api/v1/customer/email/resend-verification
   *
   * @returns {Promise<Object>}
   */
  async resendVerificationEmail () {
    return this.api.post('/api/v1/customer/email/resend-verification', {})
  }
}
