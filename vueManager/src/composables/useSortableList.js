import { useLexicon } from '@vuetools/useLexicon'
import { useToast } from 'primevue/usetoast'
import { ref } from 'vue'

import request from '../request.js'

/**
 * Drag → POST/PUT sort → reload for mgr sortable lists.
 *
 * @param {Object} options
 * @param {import('vue').Ref<Array>} options.items
 * @param {string} options.sortUrl
 * @param {'post'|'put'} [options.method='post']
 * @param {() => void|Promise<void>} [options.reload]
 * @param {string|(() => string)} [options.successMessage]
 * @param {(items: Array) => Array} [options.getPayload] Default: `{ ids: items.map(i => i.id) }`
 * @param {() => void|Promise<void>} [options.onSuccess]
 * @param {(error: Error) => void} [options.onError]
 * @returns {Object}
 */
export function useSortableList(options = {}) {
  const {
    items,
    sortUrl,
    method = 'post',
    reload = null,
    successMessage = null,
    getPayload = list => ({ ids: list.map(row => row.id) }),
    onSuccess = null,
    onError = null,
  } = options

  if (!items) {
    throw new Error('useSortableList: items ref is required')
  }
  if (!sortUrl) {
    throw new Error('useSortableList: sortUrl is required')
  }

  const requestMethod = method === 'put' ? 'put' : 'post'

  const toast = useToast()
  const { _ } = useLexicon()
  const sorting = ref(false)

  function notify(severity, detail, life) {
    toast.add({
      severity,
      summary: _(severity === 'success' ? 'success' : 'error'),
      detail: detail || _('error_saving_data'),
      life,
    })
  }

  async function onDragEnd() {
    sorting.value = true
    try {
      await request[requestMethod](sortUrl, getPayload(items.value))

      if (successMessage) {
        const detail = typeof successMessage === 'function' ? successMessage() : successMessage
        notify('success', detail, 2000)
      }
      if (onSuccess) {
        await onSuccess()
      }
    } catch (error) {
      console.error('[useSortableList] sort failed:', error)
      if (onError) {
        onError(error)
      } else {
        notify('error', error?.message, 5000)
      }
      if (reload) {
        await reload()
      }
    } finally {
      sorting.value = false
    }
  }

  return {
    sorting,
    onDragEnd,
  }
}
