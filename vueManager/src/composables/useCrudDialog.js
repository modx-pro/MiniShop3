import { useLexicon } from '@vuetools/useLexicon'
import { useToast } from 'primevue/usetoast'
import { ref } from 'vue'

/**
 * CRUD dialog state for mgr grids (open/create/edit/saving + toast helpers).
 * Does not own form templates or validation schemas.
 *
 * @param {Object} [options]
 * @param {() => Object} [options.createDefaults] Factory for new-record draft
 * @returns {Object}
 */
export function useCrudDialog(options = {}) {
  const { createDefaults = () => ({}) } = options

  const toast = useToast()
  const { _ } = useLexicon()

  const visible = ref(false)
  const isNew = ref(false)
  const saving = ref(false)
  const item = ref(null)

  function addToast(severity, summaryKey, detail, life) {
    toast.add({
      severity,
      summary: _(summaryKey),
      detail,
      life,
    })
  }

  function openCreate(overrides = {}) {
    item.value = { ...createDefaults(), ...overrides }
    isNew.value = true
    visible.value = true
  }

  function openEdit(record) {
    item.value = { ...record }
    isNew.value = false
    visible.value = true
  }

  function close() {
    visible.value = false
  }

  function toastSuccess(detail, life = 3000) {
    addToast('success', 'success', detail, life)
  }

  function toastError(detail, life = 5000) {
    addToast('error', 'error', detail || _('error_saving_data'), life)
  }

  function toastWarn(detail, life = 3000) {
    addToast('warn', 'warning', detail, life)
  }

  /**
   * Run async save body with saving flag + optional auto-close on success.
   * Caller owns API calls and validation; returns true if body completed without throw.
   *
   * @param {() => Promise<void>} saveFn
   * @param {{ closeOnSuccess?: boolean }} [opts]
   */
  async function runSave(saveFn, opts = {}) {
    const { closeOnSuccess = true } = opts
    saving.value = true
    try {
      await saveFn()
      if (closeOnSuccess) {
        close()
      }
      return true
    } catch (error) {
      console.error('[useCrudDialog] save failed:', error)
      toastError(error?.message || _('error_saving_data'))
      return false
    } finally {
      saving.value = false
    }
  }

  return {
    visible,
    isNew,
    saving,
    item,
    openCreate,
    openEdit,
    close,
    runSave,
    toastSuccess,
    toastError,
    toastWarn,
  }
}
