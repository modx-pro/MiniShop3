/**
 * Composable for Gallery API calls via MODX connector.
 * All functions return Promise; no global state mutation.
 */

import { ref } from 'vue'

const GALLERY_ACTIONS = {
  GetList: 'MiniShop3\\Processors\\Gallery\\GetList',
  Sort: 'MiniShop3\\Processors\\Gallery\\Sort',
  Multiple: 'MiniShop3\\Processors\\Gallery\\Multiple',
  RemoveAll: 'MiniShop3\\Processors\\Gallery\\RemoveAll',
  GenerateAll: 'MiniShop3\\Processors\\Gallery\\GenerateAll',
  Update: 'MiniShop3\\Processors\\Gallery\\Update',
}
const PRODUCT_UPDATE_SOURCE = 'MiniShop3\\Processors\\Product\\UpdateSource'

function getConnectorUrl() {
  if (typeof ms3 !== 'undefined' && ms3?.config?.connector_url) {
    return ms3.config.connector_url
  }
  return '/assets/components/minishop3/connector.php'
}

function getModAuth() {
  if (typeof MODx !== 'undefined' && MODx?.siteId) {
    return MODx.siteId
  }
  return ''
}

/**
 * Call MODX connector with action and params.
 * @param {string} action - Processor class name
 * @param {Record<string, string|number>} params - Request params
 * @param {'GET'|'POST'} method
 * @returns {Promise<{ success: boolean, results?: any[], total?: number, object?: object, message?: string }>}
 */
async function connectorRequest(action, params, method = 'POST') {
  const baseUrl = getConnectorUrl()
  const modAuth = getModAuth()
  const allParams = {
    action,
    ctx: 'mgr',
    HTTP_MODAUTH: modAuth,
    ...params,
  }

  let url = baseUrl
  const options = { method, credentials: 'same-origin', headers: { Accept: 'application/json' } }

  if (method === 'GET') {
    const search = new URLSearchParams()
    Object.entries(allParams).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') search.set(k, String(v))
    })
    url = `${baseUrl}?${search.toString()}`
  } else {
    options.body = new URLSearchParams(
      Object.fromEntries(
        Object.entries(allParams).filter(([_, v]) => v !== undefined && v !== null && v !== '')
      )
    )
  }

  const response = await fetch(url, options)
  const text = await response.text()
  const trimmed = text.trim()

  let data
  try {
    data = JSON.parse(text)
  } catch (e) {
    const contentType = response.headers.get('Content-Type') || ''
    const isJsonDeclared = contentType.includes('application/json')
    const snippet = trimmed.slice(0, 200).replace(/\s+/g, ' ')
    const err = new Error(
      isJsonDeclared
        ? `Gallery API: invalid JSON (${e.message}). Check for PHP errors or HTML in connector response.`
        : `Gallery API: server returned ${response.status} (expected JSON). ${snippet ? `Response: ${snippet}…` : ''}`
    )
    err.status = response.status
    err.body = text.slice(0, 300)
    throw err
  }

  if (data.success === false) {
    const err = new Error(data.message || 'Request failed')
    err.response = data
    err.status = response.status
    throw err
  }
  return data
}

/**
 * @param {number} productId
 * @param {{ query?: string, start?: number, limit?: number }} opts
 * @returns {Promise<{ results: any[], total: number, thumb?: string }>}
 */
export async function fetchGalleryList(productId, opts = {}) {
  const { query = '', start = 0, limit = 20 } = opts
  const data = await connectorRequest(
    GALLERY_ACTIONS.GetList,
    {
      product_id: productId,
      parent_id: 0,
      type: 'image',
      query: String(query).trim(),
      start: Number(start),
      limit: Number(limit),
    },
    'GET'
  )
  return {
    results: data.results ?? data.data ?? [],
    total: data.total ?? 0,
    thumb: data.object?.thumb,
  }
}

/**
 * Изменение порядка файлов в галерее (drag-and-drop).
 * @param {number} productId
 * @param {number} sourceId - id перемещаемого файла
 * @param {number} targetId - id файла, относительно которого ставим (сосед)
 */
export async function sortFiles(productId, sourceId, targetId) {
  // Процессор Gallery\Sort ожидает целочисленные id
  const data = await connectorRequest(GALLERY_ACTIONS.Sort, {
    product_id: Number(productId),
    source_id: Number(sourceId),
    target_id: Number(targetId),
  })
  return { thumb: data.object?.thumb }
}

/**
 * @param {number[]} ids - file ids
 */
export async function deleteFiles(ids) {
  if (!ids?.length) return {}
  await connectorRequest(GALLERY_ACTIONS.Multiple, {
    method: 'Remove',
    ids: JSON.stringify(ids),
  })
  return {}
}

/**
 * @param {number} productId
 * @returns {Promise<{ thumb?: string }>}
 */
export async function deleteAll(productId) {
  const data = await connectorRequest(GALLERY_ACTIONS.RemoveAll, { product_id: productId })
  return { thumb: data.object?.thumb }
}

/**
 * @param {number[]} ids - file ids
 */
export async function regenerateThumbs(ids) {
  if (!ids?.length) return {}
  await connectorRequest(GALLERY_ACTIONS.Multiple, {
    method: 'Generate',
    ids: JSON.stringify(ids),
  })
  return {}
}

/**
 * @param {number} productId
 * @returns {Promise<{ thumb?: string }>}
 */
export async function regenerateAll(productId) {
  const data = await connectorRequest(GALLERY_ACTIONS.GenerateAll, { product_id: productId })
  return { thumb: data.object?.thumb }
}

/**
 * @param {number} id - file id
 * @param {{ file: string, name?: string, description?: string }} payload
 */
export async function updateFile(id, payload) {
  await connectorRequest(GALLERY_ACTIONS.Update, {
    id,
    file: payload.file ?? '',
    name: payload.name ?? '',
    description: payload.description ?? '',
  })
  return {}
}

/**
 * Update product media source and reload page.
 * @param {number} productId
 * @param {number} sourceId
 */
export async function updateProductSource(productId, sourceId) {
  await connectorRequest(PRODUCT_UPDATE_SOURCE, {
    id: productId,
    source_id: sourceId,
  })
  if (typeof location !== 'undefined' && location.reload) {
    location.reload()
  }
}

/**
 * Composable: gallery API + loading state.
 * Components use this to get API functions and isLoading.
 */
export function useGalleryApi() {
  const isLoading = ref(false)

  const withLoading = (fn) => {
    return async (...args) => {
      isLoading.value = true
      try {
        const result = await fn(...args)
        return result
      } finally {
        isLoading.value = false
      }
    }
  }

  return {
    isLoading,
    fetchGalleryList: withLoading(fetchGalleryList),
    sortFiles: withLoading(sortFiles),
    deleteFiles: withLoading(deleteFiles),
    deleteAll: withLoading(deleteAll),
    regenerateThumbs: withLoading(regenerateThumbs),
    regenerateAll: withLoading(regenerateAll),
    updateFile: withLoading(updateFile),
    updateProductSource: withLoading(updateProductSource),
  }
}
