import { useLexicon } from '@vuetools/useLexicon'
import { useToast } from 'primevue/usetoast'

import { createResourceList } from './resourceListCore.js'

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

  return createResourceList({
    fetchPage,
    defaultRows,
    defaultSortField,
    defaultSortOrder,
    itemsKey,
    onLoadError: showLoadError,
  })
}
