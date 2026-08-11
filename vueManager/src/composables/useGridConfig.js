import { ref } from 'vue'

import request from '../request.js'

/**
 * Load mgr grid column config from `/api/mgr/grid-config/{gridId}` with fallback.
 *
 * @param {Object} options
 * @param {string} options.gridId
 * @param {() => Array} options.getFallbackColumns
 * @param {'columns'|'fields'} [options.responseKey='columns'] Prefer this key; also tries the other.
 * @param {(response: Object) => void} [options.onLoaded] Extra fields from response (e.g. direct_filter_keys)
 * @param {(error: Error) => void} [options.onError] Optional UI notify when fallback is used after failure
 * @returns {Object}
 */
export function useGridConfig(options = {}) {
  const {
    gridId,
    getFallbackColumns,
    responseKey = 'columns',
    onLoaded = null,
    onError = null,
  } = options

  if (!gridId) {
    throw new Error('useGridConfig: gridId is required')
  }
  if (typeof getFallbackColumns !== 'function') {
    throw new Error('useGridConfig: getFallbackColumns is required')
  }

  const columns = ref([])
  const loading = ref(false)
  const loaded = ref(false)

  function resolveColumns(response) {
    if (!response || typeof response !== 'object') {
      return null
    }

    const primary = response[responseKey]
    if (Array.isArray(primary)) {
      return primary
    }

    const altKey = responseKey === 'columns' ? 'fields' : 'columns'
    const alternate = response[altKey]
    return Array.isArray(alternate) ? alternate : null
  }

  async function loadGridConfig() {
    loading.value = true
    try {
      const response = await request.get(`/api/mgr/grid-config/${gridId}`)
      const resolved = resolveColumns(response)
      columns.value = resolved?.length ? resolved : getFallbackColumns()
      onLoaded?.(response)
    } catch (error) {
      console.error(`[useGridConfig] Failed to load grid-config/${gridId}:`, error)
      columns.value = getFallbackColumns()
      onLoaded?.({})
      onError?.(error)
    } finally {
      loading.value = false
      loaded.value = true
    }
  }

  return {
    columns,
    loading,
    loaded,
    loadGridConfig,
  }
}
