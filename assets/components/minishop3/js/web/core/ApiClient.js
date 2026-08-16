/**
 * HTTP client for MiniShop3 REST API
 *
 * Simple wrapper over fetch() for backend API interaction.
 * Token is sent automatically via httpOnly cookie (credentials: 'same-origin').
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
    // Page MODX context for API lexicon (#541)
    this.ctx = config.ctx || 'web'
  }

  /**
   * Build API URL with route and page context (`ctx`).
   *
   * @param {string} endpoint - API endpoint (e.g., '/cart/get')
   * @returns {URL}
   */
  buildUrl (endpoint) {
    const url = new URL(this.baseUrl, window.location.origin)
    url.searchParams.set('route', endpoint)
    url.searchParams.set('ctx', this.ctx)
    return url
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
    const url = this.buildUrl(endpoint)

    const headers = {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }

    const options = {
      method,
      headers,
      credentials: 'same-origin'
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

    // Handle token errors: request new token from server and retry
    if (!isRetry && response.status === 401 && this.isTokenError(result)) {
      console.log('[ApiClient] Token invalid, refreshing and retrying request')
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
   * Web API returns payload in `data`; legacy processors used `object`.
   *
   * @param {Object|null|undefined} result - API response
   * @returns {Object|null}
   */
  static getPayload (result) {
    if (!result) {
      return null
    }
    return result.data ?? result.object ?? null
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
