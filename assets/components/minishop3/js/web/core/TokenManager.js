/**
 * Customer token manager (httpOnly cookie mode)
 *
 * Token is stored in httpOnly cookie by the server.
 * JS cannot read it directly — it's sent automatically with every request.
 * This class handles initialization and legacy localStorage cleanup.
 *
 * @example
 * const tokenManager = new TokenManager({ tokenName: 'ms3_token' })
 * await tokenManager.ensureToken()
 */
class TokenManager {
  /**
   * @param {Object} config - Configuration
   * @param {string} config.tokenName - Legacy key (for localStorage cleanup)
   */
  constructor (config) {
    this.tokenName = config.tokenName || 'ms3_token'
    this.apiClient = null
    this.tokenInitialized = false

    // Clean up legacy localStorage on construction
    this.cleanupLegacyStorage()
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
   * Get token — always returns null (httpOnly cookie, not accessible from JS)
   *
   * @returns {null}
   */
  getToken () {
    return null
  }

  /**
   * Get full token data — always returns null (httpOnly cookie)
   *
   * @returns {null}
   */
  getTokenData () {
    return null
  }

  /**
   * Set token — no-op (token is managed by server via httpOnly cookie)
   *
   * @param {string} _token - Unused
   * @param {number} _lifetime - Unused
   */
  setToken (_token, _lifetime) {
    // No-op: token is in httpOnly cookie, managed by server
  }

  /**
   * Remove token — cleans up legacy localStorage only
   */
  removeToken () {
    this.cleanupLegacyStorage()
  }

  /**
   * Ensure token cookie exists by requesting from server if needed
   *
   * @returns {Promise<void>}
   */
  async ensureToken () {
    if (this.tokenInitialized) {
      return
    }

    await this.fetchNewToken()
  }

  /**
   * Fetch new token from server (server sets httpOnly cookie)
   *
   * @returns {Promise<void>}
   */
  async fetchNewToken () {
    if (!this.apiClient) {
      console.error('TokenManager: ApiClient not set. Use setApiClient()')
      return
    }

    try {
      const url = this.apiClient.buildUrl('/api/v1/customer/token/get')

      const response = await fetch(url.toString(), {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })

      const result = await response.json()

      if (result.success) {
        this.tokenInitialized = true
      } else {
        console.error('TokenManager: Failed to get token', result)
      }
    } catch (error) {
      console.error('TokenManager: Error fetching token', error)
    }
  }

  /**
   * Remove legacy localStorage data
   */
  cleanupLegacyStorage () {
    try {
      localStorage.removeItem(this.tokenName)
    } catch (e) {
      // Ignore storage errors
    }
  }
}
