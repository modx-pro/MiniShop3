import { ref } from 'vue'

import { parsePageResponse } from '../utils/resourceListResponse.js'

/**
 * Core list + pagination state (no PrimeVue / lexicon inject).
 * Used by useResourceList and unit-tested for AbortController/seq races.
 *
 * @param {Object} options
 * @param {Function} options.fetchPage
 * @param {number} [options.defaultRows=20]
 * @param {string|null} [options.defaultSortField=null]
 * @param {number} [options.defaultSortOrder=-1]
 * @param {string} [options.itemsKey='results']
 * @param {(error: Error) => void} [options.onLoadError]
 * @returns {Object}
 */
export function createResourceList(options = {}) {
  const {
    fetchPage,
    defaultRows = 20,
    defaultSortField = null,
    defaultSortOrder = -1,
    itemsKey = 'results',
    onLoadError = null,
  } = options

  if (typeof fetchPage !== 'function') {
    throw new Error('createResourceList: fetchPage is required')
  }

  const loading = ref(false)
  const items = ref([])
  const total = ref(0)
  const first = ref(0)
  const rows = ref(defaultRows)
  const sortField = ref(defaultSortField)
  const sortOrder = ref(defaultSortOrder)

  let loadSeq = 0
  let abortController = null

  async function load() {
    const seq = ++loadSeq
    abortController?.abort()
    abortController = typeof AbortController !== 'undefined' ? new AbortController() : null

    loading.value = true
    try {
      const response = await fetchPage({
        first: first.value,
        rows: rows.value,
        sortField: sortField.value,
        sortOrder: sortOrder.value,
        signal: abortController?.signal,
      })

      if (seq !== loadSeq) {
        return
      }

      const parsed = parsePageResponse(response, itemsKey)
      if (parsed.malformed) {
        console.error('[useResourceList] Unexpected response shape:', response)
      }
      items.value = parsed.items
      total.value = parsed.total
    } catch (error) {
      if (error?.name === 'AbortError' || seq !== loadSeq) {
        return
      }
      console.error('[useResourceList] load failed:', error)
      onLoadError?.(error)
      items.value = []
      total.value = 0
    } finally {
      if (seq === loadSeq) {
        loading.value = false
      }
    }
  }

  function onPage(event) {
    first.value = event.first
    rows.value = event.rows
    return load()
  }

  function onSort(event) {
    sortField.value = event.sortField ?? defaultSortField
    sortOrder.value = event.sortOrder ?? defaultSortOrder
    first.value = 0
    return load()
  }

  function resetPageAndLoad() {
    first.value = 0
    return load()
  }

  return {
    loading,
    items,
    total,
    first,
    rows,
    sortField,
    sortOrder,
    load,
    onPage,
    onSort,
    resetPageAndLoad,
  }
}
