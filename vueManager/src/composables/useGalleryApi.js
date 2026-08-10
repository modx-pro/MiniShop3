import { ref } from 'vue'

/**
 * Get connector URL
 */
function getConnectorUrl() {
  if (typeof ms3 !== 'undefined' && ms3?.config?.connector_url) {
    return ms3.config.connector_url
  }
  return '/assets/components/minishop3/connector.php'
}

/**
 * Get MODAUTH token
 */
function getModAuthToken() {
  return window.MODx?.siteId || ''
}

/**
 * Execute connector request
 *
 * @param {string} action - Full processor class name
 * @param {Object} params - Request parameters
 * @param {string} method - HTTP method (GET or POST)
 * @returns {Promise<Object>} - Parsed response
 */
async function connectorRequest(action, params = {}, method = 'POST') {
  const connectorUrl = getConnectorUrl()
  const token = getModAuthToken()

  // Filter out undefined/null but keep empty strings (for clearing description)
  const filtered = {}
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null) {
      filtered[key] = value
    }
  }

  let url
  let fetchOptions

  if (method === 'GET') {
    const query = new URLSearchParams({
      action,
      HTTP_MODAUTH: token,
      ...filtered,
    })
    url = `${connectorUrl}?${query.toString()}`
    fetchOptions = {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    }
  } else {
    const query = new URLSearchParams({ action, HTTP_MODAUTH: token })
    url = `${connectorUrl}?${query.toString()}`
    const body = new URLSearchParams(filtered)
    fetchOptions = {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: body.toString(),
    }
  }

  const response = await fetch(url, fetchOptions)
  const data = await response.json()

  if (!data.success) {
    throw new Error(data.message || 'Request failed')
  }

  return data
}

/**
 * Gallery API composable
 * Provides all API methods for gallery operations
 */
export function useGalleryApi() {
  const isLoading = ref(false)

  /**
   * Fetch gallery file list
   * @param {number} productId
   * @param {Object} options - { query, start, limit }
   * @returns {Promise<{results: Array, total: number, thumb: string}>}
   */
  async function fetchGalleryList(productId, { query = '', start = 0, limit = 20 } = {}) {
    isLoading.value = true
    try {
      const data = await connectorRequest(
        'MiniShop3\\Processors\\Gallery\\GetList',
        {
          product_id: productId,
          parent: 0,
          type: 'image',
          query: query || undefined,
          start,
          limit,
        },
        'GET'
      )
      return {
        results: data.results || [],
        total: data.total || 0,
        thumb: data.object?.thumb || '',
      }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Sort files (drag-drop reorder)
   * @param {number} productId
   * @param {number} sourceFileId - ID of file being moved
   * @param {number} targetFileId - ID of file at target position
   * @returns {Promise<{thumb: string}>}
   */
  async function sortFiles(productId, sourceFileId, targetFileId) {
    const data = await connectorRequest('MiniShop3\\Processors\\Gallery\\Sort', {
      product_id: productId,
      source_id: sourceFileId,
      target_id: targetFileId,
    })
    return { thumb: data.object?.thumb || '' }
  }

  /**
   * Delete files by IDs
   * @param {number[]} ids
   * @returns {Promise<{thumb: string}>}
   */
  async function deleteFiles(ids) {
    const data = await connectorRequest('MiniShop3\\Processors\\Gallery\\Multiple', {
      method: 'Remove',
      ids: JSON.stringify(ids),
    })
    return { thumb: data.object?.thumb || '' }
  }

  /**
   * Delete all files for product
   * @param {number} productId
   * @returns {Promise<{thumb: string}>}
   */
  async function deleteAll(productId) {
    const data = await connectorRequest('MiniShop3\\Processors\\Gallery\\RemoveAll', {
      product_id: productId,
    })
    return { thumb: data.object?.thumb || '' }
  }

  /**
   * Regenerate thumbnails for specific files
   * @param {number[]} ids
   * @returns {Promise<void>}
   */
  async function regenerateThumbs(ids) {
    await connectorRequest('MiniShop3\\Processors\\Gallery\\Multiple', {
      method: 'Generate',
      ids: JSON.stringify(ids),
    })
  }

  /**
   * Regenerate all thumbnails for product
   * @param {number} productId
   * @returns {Promise<{thumb: string}>}
   */
  async function regenerateAll(productId) {
    const data = await connectorRequest('MiniShop3\\Processors\\Gallery\\GenerateAll', {
      product_id: productId,
    })
    return { thumb: data.object?.thumb || '' }
  }

  /**
   * Update file properties
   * @param {number} id - File ID
   * @param {Object} fields - { file, name, description }
   * @returns {Promise<void>}
   */
  async function updateFile(id, fields) {
    await connectorRequest('MiniShop3\\Processors\\Gallery\\Update', {
      id,
      ...fields,
    })
  }

  /**
   * Mark gallery file as product preview without changing sort order (#130).
   * @param {number} productId
   * @param {number} fileId
   * @returns {Promise<{thumb: string}>}
   */
  async function setPreview(productId, fileId) {
    const data = await connectorRequest('MiniShop3\\Processors\\Gallery\\SetPreview', {
      product_id: productId,
      id: fileId,
    })
    return { thumb: data.object?.thumb || '' }
  }

  /**
   * Change product media source
   * @param {number} productId
   * @param {number} sourceId
   * @returns {Promise<void>}
   */
  async function updateProductSource(productId, sourceId) {
    await connectorRequest('MiniShop3\\Processors\\Product\\UpdateSource', {
      id: productId,
      source_id: sourceId,
    })
    // Reload page after source change (same behavior as ExtJS)
    window.location.reload()
  }

  return {
    isLoading,
    fetchGalleryList,
    sortFiles,
    deleteFiles,
    deleteAll,
    regenerateThumbs,
    regenerateAll,
    updateFile,
    setPreview,
    updateProductSource,
  }
}
