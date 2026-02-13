/**
 * HTTP client for MiniShop3 REST API
 *
 * Simple wrapper over fetch() for backend API interaction.
 * Automatically adds authorization token and handles JSON.
 * Handles token refresh on 401 errors.
 *
 * @example
 * const client = new ApiClient({
 *   baseUrl: '/assets/components/minishop3/connector.php',
 *   tokenManager: tokenManager
 * })
 *
 * const response = await client.get('/cart/get')
 */
class ApiClient {
  /**
   * @param {Object} config - Client configuration
   * @param {string} config.baseUrl - Base API URL (e.g., '/assets/components/minishop3/api.php')
   * @param {TokenManager} config.tokenManager - Token manager instance
   */
  constructor (config) {
    this.baseUrl = config.baseUrl || '/assets/components/minishop3/api.php'
    this.tokenManager = config.tokenManager
  }

  /**
   * Base method for executing HTTP requests
   *
   * @param {string} method - HTTP method (GET, POST, etc.)
   * @param {string} endpoint - API endpoint (e.g., '/cart/get')
   * @param {Object|null} data - Data to send (for POST/PATCH)
   * @param {boolean} isRetry - Internal flag for retry after token refresh
   * @returns {Promise<Object>} - Server response
   */
  async request (method, endpoint, data = null, isRetry = false) {
    const url = new URL(this.baseUrl, window.location.origin)

    url.searchParams.set('route', endpoint)

    const token = this.tokenManager.getToken()
    if (token) {
      url.searchParams.set('ms3_token', token)
    }

    const headers = {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }

    const options = {
      method,
      headers
    }

    if (data && (method === 'POST' || method === 'PATCH' || method === 'PUT')) {
      if (data instanceof FormData) {
        options.body = data
      } else {
        headers['Content-Type'] = 'application/json'
        options.body = JSON.stringify(data)
      }
    }

    const response = await fetch(url.toString(), options)
    const result = await response.json()

    // Handle token errors: clear invalid token, get new one, and retry
    if (!isRetry && response.status === 401 && this.isTokenError(result)) {
      console.log('[ApiClient] Token invalid, refreshing and retrying request')
      this.tokenManager.removeToken()
      await this.tokenManager.fetchNewToken()
      return this.request(method, endpoint, data, true)
    }

    return result
  }

  /**
   * Check if error is a token-related error
   *
   * @param {Object} result - API response
   * @returns {boolean}
   */
  isTokenError (result) {
    if (!result || result.success) {
      return false
    }
    const tokenErrors = ['ms3_err_token', 'ms3_err_token_invalid', 'ms3_err_token_expired']
    return tokenErrors.includes(result.message)
  }

  /**
   * GET request
   *
   * @param {string} endpoint - API endpoint
   * @returns {Promise<Object>}
   */
  get (endpoint) {
    return this.request('GET', endpoint)
  }

  /**
   * POST request
   *
   * @param {string} endpoint - API endpoint
   * @param {Object|FormData} data - Data to send
   * @returns {Promise<Object>}
   */
  post (endpoint, data) {
    return this.request('POST', endpoint, data)
  }

  /**
   * PUT request (full update)
   *
   * @param {string} endpoint - API endpoint
   * @param {Object} data - Data to send
   * @returns {Promise<Object>}
   */
  put (endpoint, data) {
    return this.request('PUT', endpoint, data)
  }

  /**
   * PATCH request (partial update)
   *
   * @param {string} endpoint - API endpoint
   * @param {Object} data - Data to send
   * @returns {Promise<Object>}
   */
  patch (endpoint, data) {
    return this.request('PATCH', endpoint, data)
  }

  /**
   * DELETE request
   *
   * @param {string} endpoint - API endpoint
   * @returns {Promise<Object>}
   */
  delete (endpoint) {
    return this.request('DELETE', endpoint)
  }
}
