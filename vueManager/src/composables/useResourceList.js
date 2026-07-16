import { useLexicon } from '@vuetools/useLexicon'
import { useToast } from 'primevue/usetoast'
import { ref } from 'vue'

import { parsePageResponse } from '../utils/resourceListResponse.js'

/**
 * Thin list + pagination (+ optional sort) for mgr grids.
 * Stale responses are ignored via load sequence (#385); AbortController cancels in-flight fetch when supported.
 *
 * @param {Object} options
 * @param {(ctx: { first: number, rows: number, sortField: string|null, sortOrder: number, signal: AbortSignal }) => Promise<{ results?: Array, total?: number }|Array>} options.fetchPage
 * @param {number} [options.defaultRows=20]
 * @param {string|null} [options.defaultSortField=null]
 * @param {number} [options.defaultSortOrder=-1]
 * @param {(error: Error) => void} [options.onError]
 * @param {string} [options.itemsKey='results']
 * @returns {Object}
 */
export function useResourceList(options = {}) {
  const {
    fetchPage,
    defaultRows = 20,
    defaultSortField = null,
    defaultSortOrder = -1,
    onError = null,
    itemsKey = 'results',
  } = options

  if (typeof fetchPage !== 'function') {
    throw new Error('useResourceList: fetchPage is required')
  }

  const toast = useToast()
  const { _ } = useLexicon()

  const loading = ref(false)
  const items = ref([])
  const total = ref(0)
  const first = ref(0)
  const rows = ref(defaultRows)
  const sortField = ref(defaultSortField)
  const sortOrder = ref(defaultSortOrder)

  let loadSeq = 0
  let abortController = null

  function showLoadError(error) {
    if (onError) {
      onError(error)
      return
    }
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error?.message || _('error_loading_data'),
      life: 5000,
    })
  }

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
      showLoadError(error)
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
