/**
 * Extract payload from MODX connector JSON envelope.
 *
 * Connector responses use `{ success, message, object?, data? }`. Callers expect
 * the inner payload (e.g. `{ results, total }`), not the envelope with `success`.
 *
 * @param {unknown} responseData - Parsed JSON body from connector.php
 * @returns {unknown} Unwrapped payload, or the original value when not an envelope
 *
 * Uses `!= null` so falsy scalars in `object`/`data` (0, false, "") are valid payloads.
 */
export function unwrapResponsePayload(responseData) {
  if (responseData === null || typeof responseData !== 'object' || Array.isArray(responseData)) {
    return responseData
  }

  if ('object' in responseData && responseData.object != null) {
    return responseData.object
  }

  if ('data' in responseData && responseData.data != null) {
    return responseData.data
  }

  return responseData
}

/**
 * API Request class for working with MiniShop3 API through MODX connector
 *
 * Features:
 * - Uses MODX connector.php for all requests
 * - Automatically adds HTTP_MODAUTH token for security
 * - Supports all HTTP methods (GET, POST, PUT, DELETE, PATCH)
 * - Error handling with detailed information
 */
class Request {
  constructor() {
    this.headers = {}
    this.init()
  }

  /**
   * Initialize: get configuration from MODX
   */
  init() {
    this.setHeaders()
  }

  /**
   * Get connector URL (dynamically)
   */
  getConnectorUrl() {
    if (typeof ms3 !== 'undefined' && ms3?.config?.connector_url) {
      return ms3.config.connector_url
    }
    return '/assets/components/minishop3/connector.php'
  }

  /**
   * Get MODAUTH token (dynamically)
   */
  getModAuthToken() {
    // Prefer the token injected server-side into ms3.config: it is present in the initial
    // inline <script>, before the Vue module runs, so early requests never race an unready
    // MODx.siteId global on Ext-less pages (#544). Fall back to MODx.siteId for any page
    // that does not ship ms3.config.token.
    if (typeof ms3 !== 'undefined' && ms3?.config?.token) {
      return ms3.config.token
    }
    if (typeof MODx !== 'undefined' && MODx?.siteId) {
      return MODx.siteId
    }
    return null
  }

  /**
   * Set default headers
   */
  setHeaders() {
    this.headers = {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    }
  }

  /**
   * Build URL for connector request
   *
   * @param {string} route - API route (e.g.: /api/mgr/products)
   * @param {Object} params - Additional GET parameters
   * @returns {string} - Full URL
   */
  buildUrl(route, params = {}) {
    const url = new URL(this.getConnectorUrl(), window.location.origin)

    url.searchParams.set('action', 'MiniShop3\\Processors\\Api\\Index')
    url.searchParams.set('route', route)

    const modAuthToken = this.getModAuthToken()
    if (modAuthToken) {
      url.searchParams.set('HTTP_MODAUTH', modAuthToken)
    }

    Object.entries(params).forEach(([key, value]) => {
      if (value !== null && value !== undefined) {
        url.searchParams.set(key, value)
      }
    })

    return url.toString()
  }

  /**
   * Main method for executing requests
   *
   * @param {string} method - HTTP method
   * @param {string} route - API route
   * @param {Object} data - Data to send
   * @param {Object} options - Additional options
   * @returns {Promise<Object>} - API response
   */
  async request(method, route, data = null, options = {}) {
    try {
      const fetchOptions = {
        method,
        headers: { ...this.headers, ...options.headers },
        credentials: 'same-origin',
      }

      if (options.signal) {
        fetchOptions.signal = options.signal
      }

      let url

      if (method === 'GET' && data) {
        url = this.buildUrl(route, data)
      } else {
        url = this.buildUrl(route)

        if (data) {
          fetchOptions.body = JSON.stringify(data)
        }
      }

      const response = await fetch(url, fetchOptions)

      const responseData = await response.json()

      if (responseData.success === false) {
        throw new RequestError(
          responseData.message || 'Request failed',
          response.status,
          responseData
        )
      }

      if (!response.ok) {
        throw new RequestError(
          responseData.message || `HTTP error! status: ${response.status}`,
          response.status,
          responseData
        )
      }

      return unwrapResponsePayload(responseData)
    } catch (error) {
      if (error?.name === 'AbortError') {
        throw error
      }
      if (error instanceof RequestError) {
        throw error
      }

      if (error?.name === 'AbortError') {
        throw error
      }

      throw new RequestError(error.message || 'Network error', 0, { originalError: error })
    }
  }

  /**
   * GET request
   */
  async get(route, params = null, options = {}) {
    return this.request('GET', route, params, options)
  }

  /**
   * POST request
   */
  async post(route, data = null, options = {}) {
    return this.request('POST', route, data, options)
  }

  /**
   * PUT request
   */
  async put(route, data = null, options = {}) {
    return this.request('PUT', route, data, options)
  }

  /**
   * DELETE request
   */
  async delete(route, data = null, options = {}) {
    return this.request('DELETE', route, data, options)
  }

  /**
   * PATCH request
   */
  async patch(route, data = null, options = {}) {
    return this.request('PATCH', route, data, options)
  }

  /**
   * Upload file via FormData
   *
   * @param {string} route - API route
   * @param {File} file - File object to upload
   * @param {Object} additionalData - Additional form data
   * @param {Object} options - Additional options
   * @returns {Promise<Object>} - API response
   */
  async upload(route, file, additionalData = {}, options = {}) {
    try {
      const formData = new FormData()
      formData.append('file', file)

      // Add additional data to FormData
      Object.entries(additionalData).forEach(([key, value]) => {
        if (value !== null && value !== undefined) {
          formData.append(key, value)
        }
      })

      const url = this.buildUrl(route)

      const fetchOptions = {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        // Don't set Content-Type header - browser will set it with boundary
        headers: {
          Accept: 'application/json',
          ...options.headers,
        },
      }

      const response = await fetch(url, fetchOptions)
      const responseData = await response.json()

      if (responseData.success === false) {
        throw new RequestError(
          responseData.message || 'Upload failed',
          response.status,
          responseData
        )
      }

      if (!response.ok) {
        throw new RequestError(
          responseData.message || `HTTP error! status: ${response.status}`,
          response.status,
          responseData
        )
      }

      return unwrapResponsePayload(responseData)
    } catch (error) {
      if (error instanceof RequestError) {
        throw error
      }

      throw new RequestError(error.message || 'Upload error', 0, { originalError: error })
    }
  }
}

/**
 * Custom error class for API requests
 */
class RequestError extends Error {
  constructor(message, statusCode, data = {}) {
    super(message)
    this.name = 'RequestError'
    this.statusCode = statusCode
    this.data = data
  }

  /**
   * Check if error is unauthorized
   */
  isUnauthorized() {
    return this.statusCode === 401
  }

  /**
   * Check if error is forbidden
   */
  isForbidden() {
    return this.statusCode === 403
  }

  /**
   * Check if error is validation error
   */
  isValidationError() {
    return this.statusCode === 422
  }
}
const request = new Request()

export default request
export { Request, RequestError }
