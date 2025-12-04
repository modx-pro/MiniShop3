/**
 * Customer token manager
 *
 * Manages customer authorization token:
 * - Storage in localStorage
 * - Expiry validation
 * - Automatic token retrieval when missing/expired
 *
 * @example
 * const tokenManager = new TokenManager({ tokenName: 'ms3_token' })
 * await tokenManager.ensureToken()
 * const token = tokenManager.getToken()
 */
class TokenManager {
  /**
   * @param {Object} config - Configuration
   * @param {string} config.tokenName - Key for storing token in localStorage
   */
  constructor (config) {
    this.tokenName = config.tokenName || 'ms3_token'
    this.apiClient = null
  }

  /**
   * Set ApiClient (for fetching new token from server)
   *
   * @param {ApiClient} apiClient
   */
  setApiClient (apiClient) {
    this.apiClient = apiClient
  }

  /**
   * Get token from localStorage
   *
   * @returns {string|null} - Token or null if missing/expired
   */
  getToken () {
    const tokenData = this.getTokenData()
    return tokenData ? tokenData.token : null
  }

  /**
   * Get full token data (token + expiry)
   *
   * @returns {Object|null} - { token: string, expiry: number } or null
   */
  getTokenData () {
    const stored = localStorage.getItem(this.tokenName)
    if (!stored) {
      return null
    }

    try {
      const data = JSON.parse(stored)
      const now = Date.now()

      if (now > data.expiry) {
        this.removeToken()
        return null
      }

      return data
    } catch (e) {
      this.removeToken()
      return null
    }
  }

  /**
   * Save token to localStorage
   *
   * @param {string} token - Token
   * @param {number} lifetime - Token lifetime in seconds
   */
  setToken (token, lifetime) {
    const data = {
      token,
      expiry: Date.now() + (lifetime * 1000)
    }
    localStorage.setItem(this.tokenName, JSON.stringify(data))
  }

  /**
   * Remove token from localStorage
   */
  removeToken () {
    localStorage.removeItem(this.tokenName)
  }

  /**
   * Check for valid token, fetch new if needed
   *
   * @returns {Promise<void>}
   */
  async ensureToken () {
    if (this.getToken()) {
      return
    }

    await this.fetchNewToken()
  }

  /**
   * Fetch new token from server
   *
   * @returns {Promise<void>}
   */
  async fetchNewToken () {
    if (!this.apiClient) {
      console.error('TokenManager: ApiClient not set. Use setApiClient()')
      return
    }

    try {
      const url = new URL(this.apiClient.baseUrl, window.location.origin)
      url.searchParams.set('route', '/api/v1/customer/token/get')

      const response = await fetch(url.toString(), {
        method: 'GET',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })

      const result = await response.json()

      if (result.success && result.data) {
        this.setToken(result.data.token, result.data.lifetime)
      } else {
        console.error('TokenManager: Failed to get token', result)
      }
    } catch (error) {
      console.error('TokenManager: Error fetching token', error)
    }
  }

  /**
   * Refresh existing token (extend lifetime)
   *
   * @returns {Promise<void>}
   */
  async refreshToken () {
    if (!this.apiClient) {
      console.error('TokenManager: ApiClient not set')
      return
    }

    try {
      const response = await this.apiClient.post('/customer/token/update')

      if (response.success && response.data) {
        this.setToken(response.data.token, response.data.lifetime)
      }
    } catch (error) {
      console.error('TokenManager: Error refreshing token', error)
    }
  }
}
